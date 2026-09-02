# Recovery and rollback

Recovery always protects the existing `amo-integrator` and Amnezia workloads. Invoice database and document volumes are retained unless an operator explicitly performs a separately reviewed data-destruction procedure.

## Backup contents

`scripts/backup.sh` creates a timestamped owner-only directory containing:

- `postgres.dump` — logical PostgreSQL custom-format dump without ownership or ACL records;
- `documents.tar.gz` — invoice document volume;
- `documents.files.sha256` — per-document checksums;
- `MANIFEST.sha256` — backup artifact checksums.

Copy the complete directory to encrypted off-host storage. A backup remaining only on the VPS is not a recovery point.

## Disposable restore drill

Provide the immutable release image and the owner-only PostgreSQL environment file, then run:

```bash
INVOICE_SERVICE_IMAGE=invoice-service:release-YYYYMMDD-GIT_SHA \
POSTGRES_ENV_FILE=/opt/invoice-service/secrets/postgres.env \
./scripts/restore-drill.sh /path/to/backup/TIMESTAMP
```

The script verifies the manifest, creates uniquely named `invoice-service-restore-*` volumes and a network-isolated PostgreSQL container, restores the dump and documents, compares checksums, and removes only those disposable resources. It never mounts or removes the production invoice volumes.

## Closed-stack rollback

Before public routing exists, disable any test trigger and run:

```bash
INVOICE_TRIGGER_DISABLED=1 ./scripts/rollback.sh --closed-stack
```

The script stops and removes only the four `invoice-service` containers and verifies that `invoice-service-postgres` and `invoice-service-documents` still exist.

## Public rollback

1. Disable both amoCRM pipeline webhook actions so no new jobs enter.
2. Export `INVOICE_TRIGGER_DISABLED=1`.
3. Identify the exact pre-activation Nginx backup and current bind-mounted target.
4. Run:

```bash
INVOICE_TRIGGER_DISABLED=1 \
EDGE_CONFIG_BACKUP=/var/www/amo-integrator/docker/nginx/ssl.conf.before-invoice-TIMESTAMP \
EDGE_CONFIG_TARGET=/var/www/amo-integrator/docker/nginx/ssl.conf \
./scripts/rollback.sh
```

The script restores the edge file, runs `nginx -t`, gracefully reloads the existing Nginx process, stops only the invoice containers, and preserves both invoice volumes. Afterward verify `develop.sonic.expert`, the eight protected containers, and MySQL health against the recorded baseline.

Disconnecting `amo-integrator-web-1` from `invoice-edge` is optional after the prior configuration is restored; do it only after confirming the invoice upstream is no longer referenced.

## Full rebuild from backup

1. Recreate the fixed networks and volumes through the production Compose configuration.
2. Start only PostgreSQL.
3. Restore `postgres.dump` with `pg_restore` into the empty invoice database.
4. Restore `documents.tar.gz` into `invoice-service-documents` and verify `documents.files.sha256`.
5. Start web, worker, and refresher using the exact recorded immutable image.
6. Run `verify-deploy.sh --internal` before connecting public traffic.

Never restore into the existing MySQL volume or attach invoice recovery helpers to `amo-integrator_default`.
