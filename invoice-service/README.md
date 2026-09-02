# amoCRM licence invoice service

This is an independent PHP 8.3 service for invoice offers by amoCRM licence rows.

It is intentionally isolated from the workspace root `.env` and any existing VPS service.

## Local setup

1. Copy `deploy/invoice-service.env.example` to `deploy/invoice-service.env` and replace every placeholder.
2. Run `docker compose up --build -d db web worker`.
3. Run `docker compose exec web php bin/console migrate`.
4. Configure the external amoCRM integration and pipeline webhook URLs described in `docs/AMOCRM_SETUP.md`.

Never commit `deploy/invoice-service.env`, OAuth credentials, or generated PDFs.
