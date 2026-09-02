# amoCRM setup

This service uses an **external amoCRM integration** with OAuth. It does not require a private JavaScript integration or a Documents 2.0 widget.

## Before connection

1. In `deploy/invoice-service.env`, set `APP_BASE_URL` to the public HTTPS address of this service, including `https://` and without a trailing slash. Create the separate `deploy/postgres.env` from its example and use the same password in `DATABASE_PASSWORD` and `POSTGRES_PASSWORD`.
2. Create an external integration in amoCRM. Set its redirect URL to `https://your-subdomain.example/oauth/callback`; copy its client ID and secret into `AMO_CLIENT_ID` and `AMO_CLIENT_SECRET`.
3. Create a checkbox field in the **Товары** catalogue named `Лицензия amoCRM`. Put its numeric field ID into `AMO_PRODUCT_LICENSE_FIELD_ID`.
4. Open the three existing amoCRM licence product cards and tick that checkbox. Do not tick it for services.
5. Generate independent secrets for `DATABASE_PASSWORD`, `OPERATOR_ACCESS_TOKEN`, and `AMO_CREDENTIAL_KEY_V1`. The credential key is base64-encoded 32-byte random material.

The company requisite IDs already created in the account are built into the service defaults. The legal name is read from custom field `2262597`, not the short company name. KPP is optional; the remaining legal and bank fields are mandatory.

## First deployment and OAuth connection

1. Start the service: `docker compose up --build -d db web worker`.
2. Apply schema: `docker compose exec web php bin/console migrate`.
3. Obtain an OAuth URL from the VPS shell: `docker compose exec web php bin/console oauth-url`.
4. Open the printed URL in a browser, approve access for the amoCRM account, then return to the callback page. It returns the two unique pipeline URLs once: `automatic` and `rerun`. Save them in a password manager until configured.

OAuth access and refresh tokens are encrypted in PostgreSQL with `AMO_CREDENTIAL_KEY_V1`; the key itself is not stored in the database. Run `docker compose exec worker php bin/console refresh-oauth` from the VPS scheduler before tokens expire; the amoCRM client also persists a token refresh performed during an API call.

## Digital Pipeline

Configure two `API → Send webhook` actions in the required pipeline:

- at the automatic generation stage, use the `automatic` URL;
- at a separate manual stage named `Перегенерировать счёт`, use the `rerun` URL.

The pipeline action must send the deal ID. The receiver accepts `lead_id` and the common amoCRM lead webhook structures (`leads[status][0][id]`, `leads[add][0][id]`, or `leads[update][0][id]`). It returns `202` immediately; PDF generation runs in the worker.

Every URL contains a random, account-bound secret. Do not place it in public documentation, notes, browser history exports, or logs.

## What managers see

- No company, incomplete mandatory requisites, or no licence rows: no PDF is issued, any previous current invoice is invalidated, and a note identifies the missing data.
- Same source data: the existing current invoice stays current and no number is consumed.
- Changed source data: a new number in the form `ЛЦ-АМ-{deal ID}-{global 6-digit counter}` is reserved, a new PDF is attached in Media, and the old file remains as history.
- The service adds a note naming the current invoice; managers send the PDF to clients manually.

## Routine checks

- Health: `curl -fsS https://your-subdomain.example/healthz`
- Logs: `docker compose logs -f web worker`
- Static checks before deployment: `composer qa`

Never use a root workspace `.env` for this service. Compose reads `deploy/invoice-service.env` for the web and worker containers and `deploy/postgres.env` for PostgreSQL.
