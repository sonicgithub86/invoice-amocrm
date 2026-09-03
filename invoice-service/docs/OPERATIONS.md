# Production operations

This runbook deploys `invoice-service` next to the existing `amo-integrator` project. Commands in this document are scoped to the fixed Compose project and must never be replaced with global Docker cleanup commands.

## 1. Build and verify the release off-host

Run from `invoice-service/` on the operator machine. Use a unique, non-floating tag:

```bash
composer qa
./scripts/build-release.sh invoice-service:release-YYYYMMDD-GIT_SHA
docker image inspect --format '{{.Architecture}} {{.Id}}' invoice-service:release-YYYYMMDD-GIT_SHA
```

The build script targets `linux/amd64`, loads the result locally, and runs the production-image two-page PDF probe. Do not build on the production VPS unless a separate low-traffic exception is approved.

For transfer without a registry, save the image after all local checks pass:

```bash
docker save invoice-service:release-YYYYMMDD-GIT_SHA | gzip > invoice-service-release.tar.gz
sha256sum invoice-service-release.tar.gz > invoice-service-release.tar.gz.sha256
```

Copy the archive and checksum to the VPS, verify the checksum there, then use `docker load`. Loading is the first image mutation on the VPS and happens only after preflight passes.

## 2. Prepare owner-only configuration

On the VPS, keep application secrets outside Git:

```bash
install -d -m 0700 /opt/invoice-service/secrets
install -m 0600 deploy/invoice-service.env.example /opt/invoice-service/secrets/invoice-service.env
install -m 0600 deploy/postgres.env.example /opt/invoice-service/secrets/postgres.env
```

Replace every placeholder. `DATABASE_PASSWORD` and `POSTGRES_PASSWORD` must match. Do not source either secret file in an interactive shell and do not run Compose with `--verbose`.

Create an operator-only release selector from `deploy/release.env.example`, then load it without printing values:

```bash
set -a
. /opt/invoice-service/release.env
set +a
```

## 3. Run the host gate

Before image transfer or stack creation:

```bash
./scripts/preflight-vps.sh
```

Pass conditions include:

- amd64 architecture, Docker and Compose available;
- ext4 root filesystem expanded to 30 GiB;
- at least 8 GiB free and 512 MiB `MemAvailable`;
- owner-only, non-placeholder environment files;
- all eight protected containers running;
- `develop.sonic.expert` reachable;
- immutable invoice image selector configured.

The command stores only non-secret baseline evidence under `/var/lib/invoice-service-deploy/evidence`. Record its filename as `BASELINE_FILE` for later comparisons.

## 4. Closed-stack launch — future VPS step

Do not run this section until the operator explicitly starts the VPS launch.

Create the dedicated edge network without connecting the existing Nginx container yet:

```bash
docker network inspect invoice-edge >/dev/null 2>&1 || docker network create invoice-edge
```

Validate scope before startup:

```bash
docker compose --project-name invoice-service \
  -f compose.yaml -f deploy/compose.vps.yaml config --services
```

The only services must be `db`, `web`, `worker`, and `refresher`.

Start PostgreSQL, migrate once, then start the application services:

```bash
docker compose --project-name invoice-service -f compose.yaml -f deploy/compose.vps.yaml up -d db
docker compose --project-name invoice-service -f compose.yaml -f deploy/compose.vps.yaml run --rm web php bin/console migrate
docker compose --project-name invoice-service -f compose.yaml -f deploy/compose.vps.yaml up -d web worker refresher
BASELINE_FILE=/var/lib/invoice-service-deploy/evidence/baseline-TIMESTAMP.txt ./scripts/verify-deploy.sh --internal
./scripts/backup.sh
```

Stop at this point if readiness, render, backup, free disk, memory headroom, or the protected baseline fails. Do not add DNS or Nginx routing to diagnose a closed-stack failure.

## 5. DNS, TLS, and edge activation — future VPS step

Prerequisites:

- `invoice.sonic.expert` resolves publicly to `45.9.116.135`;
- the closed stack and first backup pass;
- the previous `/var/www/amo-integrator/docker/nginx/ssl.conf` is copied to a timestamped owner-only backup.

Issue the certificate with the existing Certbot webroot. Never stop Nginx for certificate issuance. Then:

1. Connect only `amo-integrator-web-1` to `invoice-edge` using `docker network connect`.
2. Build a complete candidate configuration by appending `deploy/nginx/invoice-service.conf.example` to a copy of the existing config.
3. Validate the candidate in an isolated Nginx container with the same mounts and both required networks.
4. Replace the host bind-mounted file atomically.
5. Run `docker exec amo-integrator-web-1 nginx -t`.
6. Run `docker exec amo-integrator-web-1 nginx -s reload` only after the test passes.
7. Run `PUBLIC_BASE_URL=https://invoice.sonic.expert ./scripts/verify-deploy.sh --public` and compare `develop.sonic.expert` with `BASELINE_FILE`.

Never run `docker compose up`, `down`, or `restart` in `/var/www/amo-integrator` as part of this deployment.

## 6. amoCRM release gates

Follow `AMOCRM_SETUP.md`. Configure only the manual `Перегенерировать счёт` webhook first. Validate one controlled eligible deal, incomplete requisites, duplicate delivery, and changed data. Observe worker/refresher logs, host memory, free disk, and the protected baseline before enabling the automatic stage.

## Routine commands

```bash
./scripts/verify-deploy.sh --internal --skip-render
./scripts/backup.sh
docker compose --project-name invoice-service -f compose.yaml -f deploy/compose.vps.yaml ps
docker compose --project-name invoice-service -f compose.yaml -f deploy/compose.vps.yaml logs --tail=200 web worker refresher
```

Do not print environment variables, OAuth URLs, webhook capability URLs, or generated invoice contents into deployment evidence.
