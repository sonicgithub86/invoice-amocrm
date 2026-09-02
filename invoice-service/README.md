# amoCRM licence invoice service

This is an independent PHP 8.3 service for invoice offers by amoCRM licence rows.

It is intentionally isolated from the workspace root `.env` and any existing VPS service.

`web`, `worker`, and the OAuth `refresher` use an egress-capable Compose network for amoCRM OAuth and API calls, plus a private internal network for PostgreSQL. PostgreSQL has no published port and joins only the private network. In production, only `web` additionally joins the external `invoice-edge` network; no invoice container publishes a host port.

## Local setup

1. Copy `deploy/invoice-service.env.example` to `deploy/invoice-service.env` and `deploy/postgres.env.example` to `deploy/postgres.env`; replace every placeholder. `DATABASE_PASSWORD` and `POSTGRES_PASSWORD` must be the same database password.
2. Run `docker compose -f compose.yaml -f compose.local.yaml up --build -d db`.
3. Run `docker compose -f compose.yaml -f compose.local.yaml run --rm web php bin/console migrate`.
4. Run `docker compose -f compose.yaml -f compose.local.yaml up --build -d web worker refresher`.
5. Verify the stack with the local overlay and a non-floating image tag:

   ```bash
   COMPOSE_OVERLAY="$PWD/compose.local.yaml" \
   INVOICE_SERVICE_IMAGE=invoice-service:dev-test \
   ./scripts/verify-deploy.sh --internal
   ```

6. Configure the external amoCRM integration and pipeline webhook URLs described in `docs/AMOCRM_SETUP.md`.

Never commit `deploy/invoice-service.env`, OAuth credentials, or generated PDFs.

Production preparation, launch gates, backup, and rollback are documented in `docs/OPERATIONS.md` and `docs/RECOVERY.md`.

The service is intentionally not a private amoCRM widget: managers work with the resulting PDF in the deal's Media section. It does not send invoices to the customer automatically.
