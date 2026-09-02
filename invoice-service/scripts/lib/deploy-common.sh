#!/usr/bin/env bash

set -Eeuo pipefail

readonly DEPLOY_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SERVICE_ROOT="$(cd "${DEPLOY_LIB_DIR}/../.." && pwd)"
readonly COMPOSE_PROJECT_NAME="invoice-service"

compose_overlay="${COMPOSE_OVERLAY:-${SERVICE_ROOT}/deploy/compose.vps.yaml}"
invoice_env="${INVOICE_SERVICE_ENV_FILE:-${SERVICE_ROOT}/deploy/invoice-service.env}"
postgres_env="${POSTGRES_ENV_FILE:-${SERVICE_ROOT}/deploy/postgres.env}"
release_image="${INVOICE_SERVICE_IMAGE:-}"

deploy_fail() {
    printf 'Deployment command failed: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || deploy_fail "required command is unavailable: $1"
}

require_release_image() {
    [[ -n "$release_image" ]] || deploy_fail 'INVOICE_SERVICE_IMAGE is required'
    [[ "$release_image" != *':local' && "$release_image" != *':latest' ]] \
        || deploy_fail 'INVOICE_SERVICE_IMAGE must use an immutable release tag or digest'
}

compose() {
    INVOICE_SERVICE_ENV_FILE="$invoice_env" \
    POSTGRES_ENV_FILE="$postgres_env" \
    INVOICE_SERVICE_IMAGE="$release_image" \
        docker compose \
            --project-name "$COMPOSE_PROJECT_NAME" \
            --project-directory "$SERVICE_ROOT" \
            -f "${SERVICE_ROOT}/compose.yaml" \
            -f "$compose_overlay" \
            "$@"
}

assert_invoice_scope() {
    local services
    services="$(compose config --services | LC_ALL=C sort)"
    [[ "$services" == $'db\nrefresher\nweb\nworker' ]] \
        || deploy_fail 'Compose configuration selects an unexpected service set'

    local volumes
    volumes="$(compose config --volumes | LC_ALL=C sort)"
    [[ "$volumes" == $'invoice-service-documents\ninvoice-service-postgres' ]] \
        || deploy_fail 'Compose configuration selects an unexpected volume set'
}

container_state() {
    docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} {{.RestartCount}}' "$1"
}
