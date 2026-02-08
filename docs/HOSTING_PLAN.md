# Hosting Plan (EC2 + CyberPanel, Single Host)

## Goals
- Run Laravel app, Horizon, Redis, MySQL, and Go workers on a single EC2 instance.
- Use CyberPanel for web hosting, SSL, and domain management.
- Keep worker processes managed by systemd (or Supervisor) to avoid web panel coupling.

## Target Stack (Single Host)
- Web server: OpenLiteSpeed (managed by CyberPanel)
- PHP: PHP-FPM (managed by CyberPanel)
- App: Laravel (this repo)
- Queue: Redis (local service)
- Queue UI: Horizon (Laravel, `/horizon`)
- Scheduler: `php artisan schedule:work`
- Go workers: binary + systemd service
- Optional: Go dashboard service (separate port, reverse-proxied)
- Database: MySQL (local service)

## Process Management
Run long-lived processes as systemd services:
- `php artisan horizon`
- `php artisan schedule:work`
- Go worker binary
- Optional Go dashboard binary

Required queue observability jobs:
- Scheduler must run continuously so `horizon:snapshot` executes every 5 minutes.
- Scheduler must run continuously so `ops:queue-health` executes every minute.

## Networking & URLs
- Laravel app: main domain (CyberPanel vhost)
- Horizon: `/horizon` (Laravel route)
- Optional Go dashboard: separate subdomain (reverse proxy to `localhost:9090`)

## Redis & Queue Settings
- Use local Redis (`REDIS_HOST=127.0.0.1`)
- Set `QUEUE_CONNECTION=redis`
- Set `CACHE_STORE=redis`
- Configure queue health alerts if needed (`QUEUE_HEALTH_ENABLED=true`, `QUEUE_HEALTH_ALERTS_ENABLED=true`).
- Optional alert channels: `QUEUE_HEALTH_ALERT_EMAIL`, `QUEUE_HEALTH_SLACK_WEBHOOK_URL`.

## Queue Incident Checklist (first checks)
1. `php artisan horizon:status`
2. `php artisan horizon:supervisors`
3. `php artisan ops:queue-health --json`
4. Confirm Redis is reachable and queue metrics are updating.

## Queue Recovery Sequence
1. `php artisan horizon:terminate`
2. Let systemd/Supervisor restart `php artisan horizon`
3. Verify all segmented supervisors are back (`supervisor-default`, `supervisor-prepare`, `supervisor-parse`, `supervisor-finalize`, `supervisor-imports`, `supervisor-cache-writeback`).
4. Re-run `php artisan ops:queue-health --json` and confirm status is `healthy` or only expected warnings.

## Security
- Restrict Go dashboard with IP allowlist or basic auth.
- Ensure admin-only access to Horizon and internal ops pages.
- Keep server firewall minimal (HTTP/HTTPS + SSH only).

## Future Scaling
- Move Redis or workers to separate hosts if needed.
- Promote MySQL to managed service if storage/IO grows.
- Migrate to containerized deployment when ready.
