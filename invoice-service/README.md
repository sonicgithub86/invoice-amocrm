# amoCRM licence invoice service

This is an independent PHP 8.3 service for invoice offers by amoCRM licence rows.

It is intentionally isolated from the workspace root `.env` and any existing VPS service.

`web` and `worker` use an egress-capable Compose network for amoCRM OAuth and API calls, plus a private internal network for PostgreSQL. PostgreSQL has no published port and joins only the private network.

## Local setup

1. Copy `deploy/invoice-service.env.example` to `deploy/invoice-service.env` and `deploy/postgres.env.example` to `deploy/postgres.env`; replace every placeholder. `DATABASE_PASSWORD` and `POSTGRES_PASSWORD` must be the same database password.
2. Run `docker compose up --build -d db web worker`.
3. Run `docker compose exec web php bin/console migrate`.
4. Configure the external amoCRM integration and pipeline webhook URLs described in `docs/AMOCRM_SETUP.md`.

Never commit `deploy/invoice-service.env`, OAuth credentials, or generated PDFs.

The service is intentionally not a private amoCRM widget: managers work with the resulting PDF in the deal's Media section. It does not send invoices to the customer automatically.
