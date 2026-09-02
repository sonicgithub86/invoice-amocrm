#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

closed_stack=false
edge_backup="${EDGE_CONFIG_BACKUP:-}"
edge_target="${EDGE_CONFIG_TARGET:-}"
nginx_container="${EDGE_NGINX_CONTAINER:-amo-integrator-web-1}"

while (($# > 0)); do
    case "$1" in
        --closed-stack) closed_stack=true; shift ;;
        *) deploy_fail "unknown argument: $1" ;;
    esac
done

[[ "${INVOICE_TRIGGER_DISABLED:-}" == 1 ]] \
    || deploy_fail 'set INVOICE_TRIGGER_DISABLED=1 after disabling amoCRM webhook actions'
require_command docker
require_release_image
assert_invoice_scope

if [[ "$closed_stack" == false ]]; then
    [[ -f "$edge_backup" && -n "$edge_target" ]] \
        || deploy_fail 'EDGE_CONFIG_BACKUP and EDGE_CONFIG_TARGET are required after public activation'
    install -m 0644 "$edge_backup" "$edge_target"
    docker exec "$nginx_container" nginx -t
    docker exec "$nginx_container" nginx -s reload
fi

compose stop web worker refresher db
compose rm -f web worker refresher db

docker volume inspect invoice-service-postgres invoice-service-documents >/dev/null
printf 'Invoice rollback: PASS (data volumes preserved)\n'
