#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SERVICE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

invoice_env="${INVOICE_SERVICE_ENV_FILE:-${SERVICE_ROOT}/deploy/invoice-service.env}"
postgres_env="${POSTGRES_ENV_FILE:-${SERVICE_ROOT}/deploy/postgres.env}"
check_env_only=false
evidence_dir="${EVIDENCE_DIR:-/var/lib/invoice-service-deploy/evidence}"
protected_url="${PROTECTED_PUBLIC_URL:-https://develop.sonic.expert/}"
protected_containers="${PROTECTED_CONTAINERS:-amo-integrator-web-1 amo-integrator-app-1 amo-integrator-worker-1 amo-integrator-scheduler-1 amo-integrator-db-1 amo-integrator-certbot-1 amnezia-wireguard amnezia-awg}"

fail() {
    printf 'Preflight failed: %s\n' "$1" >&2
    exit 1
}

usage() {
    printf 'Usage: %s [--check-env-only] [--invoice-env PATH] [--postgres-env PATH]\n' "$0"
}

while (($# > 0)); do
    case "$1" in
        --check-env-only)
            check_env_only=true
            shift
            ;;
        --invoice-env)
            (($# >= 2)) || fail '--invoice-env requires a path'
            invoice_env="$2"
            shift 2
            ;;
        --postgres-env)
            (($# >= 2)) || fail '--postgres-env requires a path'
            postgres_env="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            fail "unknown argument: $1"
            ;;
    esac
done

file_mode() {
    local path="$1"
    if stat -c '%a' "$path" >/dev/null 2>&1; then
        stat -c '%a' "$path"
    else
        stat -f '%Lp' "$path"
    fi
}

read_env_value() {
    local path="$1"
    local key="$2"
    awk -v wanted="$key" '
        /^[[:space:]]*#/ { next }
        index($0, "=") == 0 { next }
        {
            current = substr($0, 1, index($0, "=") - 1)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", current)
            if (current == wanted) {
                print substr($0, index($0, "=") + 1)
            }
        }
    ' "$path"
}

validate_secret_file() {
    local path="$1"
    [[ -f "$path" ]] || fail "required environment file is missing: $path"
    local mode
    mode="$(file_mode "$path")"
    case "$mode" in
        600|400) ;;
        *) fail "environment file permissions must be owner-only (600 or 400): $path" ;;
    esac
}

require_env_key() {
    local path="$1"
    local key="$2"
    local value
    value="$(read_env_value "$path" "$key")"
    [[ -n "$value" ]] || fail "missing required environment key: $key"
    [[ "$value" != *$'\n'* ]] || fail "environment key must be defined exactly once: $key"

    local normalized
    normalized="$(printf '%s' "$value" | tr '[:upper:]' '[:lower:]')"
    case "$normalized" in
        replace-*|changeme*|change-me*|example*|todo*|your-*|'<replace-'*)
            fail "placeholder value is not allowed for environment key: $key"
            ;;
    esac
}

validate_secret_file "$invoice_env"
validate_secret_file "$postgres_env"

invoice_keys=(
    APP_BASE_URL DATABASE_URL DATABASE_USER DATABASE_PASSWORD AMO_CLIENT_ID
    AMO_CLIENT_SECRET AMO_CREDENTIAL_KEY_V1 OPERATOR_ACCESS_TOKEN
    AMO_REDIRECT_PATH AMO_PRODUCT_LICENSE_FIELD_ID INVOICE_DOCUMENTS_DIRECTORY
)
postgres_keys=(POSTGRES_DB POSTGRES_USER POSTGRES_PASSWORD)

for key in "${invoice_keys[@]}"; do
    require_env_key "$invoice_env" "$key"
done
for key in "${postgres_keys[@]}"; do
    require_env_key "$postgres_env" "$key"
done

app_base_url="$(read_env_value "$invoice_env" APP_BASE_URL)"
[[ "$app_base_url" == https://* ]] || fail 'APP_BASE_URL must use HTTPS'
[[ "$app_base_url" != */ ]] || fail 'APP_BASE_URL must not end with a slash'

field_id="$(read_env_value "$invoice_env" AMO_PRODUCT_LICENSE_FIELD_ID)"
[[ "$field_id" =~ ^[0-9]+$ ]] || fail 'AMO_PRODUCT_LICENSE_FIELD_ID must be numeric'

credential_key="$(read_env_value "$invoice_env" AMO_CREDENTIAL_KEY_V1)"
[[ "$credential_key" =~ ^[A-Za-z0-9+/]{43}=$ ]] || fail 'AMO_CREDENTIAL_KEY_V1 must be base64-encoded 32-byte material'

database_password="$(read_env_value "$invoice_env" DATABASE_PASSWORD)"
postgres_password="$(read_env_value "$postgres_env" POSTGRES_PASSWORD)"
[[ "$database_password" == "$postgres_password" ]] || fail 'DATABASE_PASSWORD and POSTGRES_PASSWORD do not match'
(( ${#database_password} >= 16 )) || fail 'DATABASE_PASSWORD must contain at least 16 characters'

operator_token="$(read_env_value "$invoice_env" OPERATOR_ACCESS_TOKEN)"
(( ${#operator_token} >= 24 )) || fail 'OPERATOR_ACCESS_TOKEN must contain at least 24 characters'

printf 'Environment files: PASS\n'

if [[ "$check_env_only" == true ]]; then
    exit 0
fi

for command in awk blockdev curl df docker findmnt stat uname; do
    command -v "$command" >/dev/null 2>&1 || fail "required command is unavailable: $command"
done

architecture="$(uname -m)"
case "$architecture" in
    x86_64|amd64) ;;
    *) fail "unsupported VPS architecture: $architecture (expected amd64)" ;;
esac

docker info >/dev/null 2>&1 || fail 'Docker daemon is unavailable'
docker compose version >/dev/null 2>&1 || fail 'Docker Compose plugin is unavailable'

filesystem_type="$(findmnt -n -o FSTYPE /)"
[[ "$filesystem_type" == ext4 ]] || fail "root filesystem must be ext4, found: $filesystem_type"

filesystem_device="$(findmnt -n -o SOURCE /)"
disk_size_bytes="$(blockdev --getsize64 "$filesystem_device")"
disk_available_bytes="$(df -B1 --output=avail / | awk 'NR == 2 {gsub(/[[:space:]]/, "", $1); print $1}')"
(( disk_size_bytes >= 30 * 1024 * 1024 * 1024 - 64 * 1024 * 1024 )) || fail 'root filesystem is smaller than the allocated 30 GiB disk'
(( disk_available_bytes >= 8 * 1024 * 1024 * 1024 )) || fail 'root filesystem has less than 8 GiB available'

[[ -r /proc/meminfo ]] || fail '/proc/meminfo is unavailable'
memory_available_kib="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)"
[[ "$memory_available_kib" =~ ^[0-9]+$ ]] || fail 'available memory could not be measured'
(( memory_available_kib >= 512 * 1024 )) || fail 'host has less than 512 MiB available memory'

image="${INVOICE_SERVICE_IMAGE:-}"
[[ -n "$image" ]] || fail 'INVOICE_SERVICE_IMAGE is required'
[[ "$image" != *':local' && "$image" != *':latest' ]] || fail 'INVOICE_SERVICE_IMAGE must be an immutable release tag or digest'
[[ "$image" == *@sha256:* || "$image" == *:* ]] || fail 'INVOICE_SERVICE_IMAGE must contain a release tag or digest'

umask 077
mkdir -p "$evidence_dir"
timestamp="$(date -u '+%Y%m%dT%H%M%SZ')"
baseline_tmp="${evidence_dir}/baseline-${timestamp}.tmp"
baseline_file="${evidence_dir}/baseline-${timestamp}.txt"
: > "$baseline_tmp"

for container in $protected_containers; do
    state="$(docker inspect --format '{{.Name}} running={{.State.Running}} restart_count={{.RestartCount}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container" 2>/dev/null)" \
        || fail "protected container is unavailable: $container"
    [[ "$state" == *'running=true'* ]] || fail "protected container is not running: $container"
    printf '%s\n' "$state" >> "$baseline_tmp"
done

public_status="$(curl -fsS -o /dev/null --max-time 15 -w '%{http_code} %{url_effective}' "$protected_url")" \
    || fail 'protected public endpoint is unavailable'
printf 'public_endpoint=%s\n' "$public_status" >> "$baseline_tmp"
printf 'root_size_bytes=%s root_available_bytes=%s mem_available_kib=%s\n' "$disk_size_bytes" "$disk_available_bytes" "$memory_available_kib" >> "$baseline_tmp"
mv "$baseline_tmp" "$baseline_file"

printf 'Host capacity: PASS (%s GiB available)\n' "$((disk_available_bytes / 1024 / 1024 / 1024))"
printf 'Protected workload baseline: PASS (%s)\n' "$baseline_file"
printf 'VPS preflight: PASS\n'
