#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SERVICE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

image="${1:-}"
[[ -n "$image" ]] || {
    printf 'Usage: %s IMAGE_TAG\n' "$0" >&2
    exit 1
}
[[ "$image" != *':latest' && "$image" != *':local' ]] || {
    printf 'Release image must not use latest or local tags.\n' >&2
    exit 1
}

command -v docker >/dev/null 2>&1 || {
    printf 'Docker is required.\n' >&2
    exit 1
}
docker buildx version >/dev/null 2>&1 || {
    printf 'Docker Buildx is required.\n' >&2
    exit 1
}

docker buildx build \
    --platform linux/amd64 \
    --provenance=false \
    --sbom=false \
    --tag "$image" \
    --load \
    "$SERVICE_ROOT"

architecture="$(docker image inspect --format '{{.Architecture}}' "$image")"
[[ "$architecture" == amd64 ]] || {
    printf 'Built image architecture is %s, expected amd64.\n' "$architecture" >&2
    exit 1
}

COMPOSE_OVERLAY="${SERVICE_ROOT}/compose.local.yaml" \
INVOICE_SERVICE_ENV_FILE="${SERVICE_ROOT}/deploy/invoice-service.env.example" \
POSTGRES_ENV_FILE="${SERVICE_ROOT}/deploy/postgres.env.example" \
INVOICE_SERVICE_IMAGE="$image" \
    "${SCRIPT_DIR}/smoke-render.sh"

image_id="$(docker image inspect --format '{{.Id}}' "$image")"
printf 'Release image: PASS tag=%s architecture=amd64 id=%s\n' "$image" "$image_id"
