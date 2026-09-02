#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

mode="internal"
baseline_file="${BASELINE_FILE:-}"
skip_render=false

while (($# > 0)); do
    case "$1" in
        --internal) mode="internal"; shift ;;
        --public) mode="public"; shift ;;
        --baseline)
            (($# >= 2)) || deploy_fail '--baseline requires a path'
            baseline_file="$2"
            shift 2
            ;;
        --skip-render) skip_render=true; shift ;;
        *) deploy_fail "unknown argument: $1" ;;
    esac
done

require_command curl
require_command docker
require_release_image
assert_invoice_scope

for service in web worker refresher db; do
    container="invoice-service-${service}"
    state="$(container_state "$container" 2>/dev/null)" || deploy_fail "container is unavailable: $container"
    [[ "$state" == running\ healthy\ * ]] || deploy_fail "container is not healthy: $container"
    [[ -z "$(docker port "$container" 2>/dev/null)" ]] || deploy_fail "container publishes a host port: $container"
done

web_networks="$(docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}} {{end}}' invoice-service-web)"
[[ " $web_networks " == *' invoice-edge '* ]] || deploy_fail 'web container is not attached to invoice-edge'
for container in invoice-service-worker invoice-service-refresher invoice-service-db; do
    networks="$(docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}} {{end}}' "$container")"
    [[ " $networks " != *' invoice-edge '* ]] || deploy_fail "private container is attached to invoice-edge: $container"
done

compose exec -T worker php bin/console readiness
compose exec -T web php -r '$body = @file_get_contents("http://127.0.0.1/healthz"); exit($body === false ? 1 : 0);'

if [[ "$skip_render" == false ]]; then
    "${SCRIPT_DIR}/smoke-render.sh"
fi

if [[ -n "$baseline_file" ]]; then
    [[ -r "$baseline_file" ]] || deploy_fail 'protected baseline file is unavailable'
    while IFS= read -r baseline; do
        [[ "$baseline" == /*' running='* ]] || continue
        container="${baseline%% *}"
        container="${container#/}"
        expected_restarts="$(printf '%s' "$baseline" | sed -n 's/.*restart_count=\([0-9][0-9]*\).*/\1/p')"
        current="$(docker inspect --format '{{.State.Running}} {{.RestartCount}}' "$container" 2>/dev/null)" \
            || deploy_fail "protected container is unavailable: $container"
        [[ "$current" == "true $expected_restarts" ]] || deploy_fail "protected container baseline changed: $container"
    done < "$baseline_file"
fi

if [[ "$mode" == public ]]; then
    public_base_url="${PUBLIC_BASE_URL:-}"
    [[ "$public_base_url" == https://* ]] || deploy_fail 'PUBLIC_BASE_URL must be an HTTPS URL'
    curl -fsS -o /dev/null --max-time 15 "${public_base_url%/}/healthz" \
        || deploy_fail 'public invoice health endpoint is unavailable'
fi

printf 'Deployment verification (%s): PASS\n' "$mode"
