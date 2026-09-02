#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

backup_dir="${1:-}"
[[ -n "$backup_dir" ]] || deploy_fail 'usage: restore-drill.sh BACKUP_DIRECTORY'
[[ -d "$backup_dir" ]] || deploy_fail 'backup directory is unavailable'

require_command docker
require_command sha256sum
require_release_image
assert_invoice_scope

(
    cd "$backup_dir"
    sha256sum -c MANIFEST.sha256
)

suffix="$(date -u '+%Y%m%d%H%M%S')-$$"
db_container="invoice-service-restore-db-${suffix}"
db_volume="invoice-service-restore-postgres-${suffix}"
documents_volume="invoice-service-restore-documents-${suffix}"

cleanup() {
    docker rm -f "$db_container" >/dev/null 2>&1 || true
    docker volume rm "$db_volume" "$documents_volume" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker volume create "$db_volume" >/dev/null
docker volume create "$documents_volume" >/dev/null
docker run -d \
    --name "$db_container" \
    --network none \
    --env-file "$postgres_env" \
    --mount "source=${db_volume},target=/var/lib/postgresql/data" \
    postgres:16-alpine >/dev/null

ready=false
for _ in $(seq 1 30); do
    if docker exec "$db_container" sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 1
done
[[ "$ready" == true ]] || deploy_fail 'disposable PostgreSQL did not become ready'

docker exec -i "$db_container" sh -c \
    'exec pg_restore --no-owner --no-acl --username="$POSTGRES_USER" --dbname="$POSTGRES_DB"' \
    < "${backup_dir}/postgres.dump"

migration_count="$(docker exec "$db_container" sh -c \
    'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --tuples-only --no-align --command="SELECT count(*) FROM schema_migrations"')"
[[ "$migration_count" =~ ^[1-9][0-9]*$ ]] || deploy_fail 'restored database has no migration history'

docker run --rm --network none \
    --mount "source=${documents_volume},target=/restore" \
    --mount "type=bind,source=${backup_dir},target=/backup,readonly" \
    --entrypoint tar "$release_image" -xzf /backup/documents.tar.gz -C /restore

restored_checksums="$(mktemp)"
trap 'rm -f "$restored_checksums"; cleanup' EXIT
docker run --rm --network none \
    --mount "source=${documents_volume},target=/restore,readonly" \
    --entrypoint sh "$release_image" -c \
    'cd /restore && find . -type f -print0 | sort -z | xargs -0 -r sha256sum' \
    > "$restored_checksums"
cmp "${backup_dir}/documents.files.sha256" "$restored_checksums" >/dev/null \
    || deploy_fail 'restored document checksums do not match the backup'

printf 'Restore drill: PASS (database migrations=%s, document checksums verified)\n' "$migration_count"
