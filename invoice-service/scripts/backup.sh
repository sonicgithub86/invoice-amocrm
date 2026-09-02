#!/usr/bin/env bash

set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

backup_root="${1:-${BACKUP_ROOT:-/var/backups/invoice-service}}"

require_command docker
require_command sha256sum
require_release_image
assert_invoice_scope

umask 077
timestamp="$(date -u '+%Y%m%dT%H%M%SZ')"
backup_dir="${backup_root%/}/${timestamp}"
mkdir -p "$backup_dir"

compose exec -T db sh -c 'exec pg_dump --format=custom --no-owner --no-acl --username="$POSTGRES_USER" "$POSTGRES_DB"' \
    > "${backup_dir}/postgres.dump"

compose run --rm --no-deps -T --entrypoint tar worker \
    -C /var/lib/invoice-service/documents -czf - . \
    > "${backup_dir}/documents.tar.gz"

compose run --rm --no-deps -T --entrypoint sh worker -c \
    'cd /var/lib/invoice-service/documents && find . -type f -print0 | sort -z | xargs -0 -r sha256sum' \
    > "${backup_dir}/documents.files.sha256"

(
    cd "$backup_dir"
    sha256sum postgres.dump documents.tar.gz documents.files.sha256 > MANIFEST.sha256
)

printf 'Invoice backup: PASS (%s)\n' "$backup_dir"
printf 'Copy this directory to encrypted off-host storage before activation.\n'
