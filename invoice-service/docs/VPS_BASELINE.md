# VPS baseline

Baseline captured on 2026-09-02 before the invoice service was launched.

## Host

- Ubuntu 24.04.4 LTS, KVM, 2 vCPU.
- RAM: 1.9 GiB; swap: 1.9 GiB.
- `/dev/sda`: 30 GiB.
- `/dev/sda2`: ext4, expanded online to 30 GiB.
- After expansion: 18 GiB used, 12 GiB available, 61% utilization.
- Docker Engine 29.4.3 and Docker Compose 5.1.3 on the VPS.

The partition start remained sector `4096`; only its end moved. The pre-change partition table is stored on the VPS at `/root/sda-partition-table.before-grow-20260902.txt`.

## Protected workloads

The following containers were running after the disk expansion without a restart:

- `amo-integrator-web-1`
- `amo-integrator-app-1`
- `amo-integrator-worker-1`
- `amo-integrator-scheduler-1`
- `amo-integrator-db-1` (`healthy`)
- `amo-integrator-certbot-1`
- `amnezia-wireguard`
- `amnezia-awg`

The existing public edge is `amo-integrator-web-1`. Its host bind mount is:

```text
/var/www/amo-integrator/docker/nginx/ssl.conf -> /etc/nginx/conf.d/default.conf (read-only)
```

It currently joins only `amo-integrator_default`, owns host ports 80/443/8080, and uses Docker DNS resolver `127.0.0.11` for variable upstreams. The deployment must not recreate this container.

## Capacity observations

- Docker/containerd: approximately 9.2 GiB.
- Existing MySQL volume: approximately 4.24 GB.
- systemd journal: approximately 725 MiB.
- Docker build cache marked reclaimable: approximately 1.49 GB.

No cleanup is required for the invoice launch because the expanded filesystem satisfies the 8 GiB preflight gate. Global Docker prune commands are prohibited on this shared host.
