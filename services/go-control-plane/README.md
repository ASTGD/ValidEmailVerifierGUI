# Go Worker Control Plane (Phase 1)

Lightweight Go service that tracks worker heartbeats, desired state, and pool scaling.

## Setup
1) Copy env example:
   ```bash
   cp .env.example .env
   ```
2) Set values in `.env` (token + Redis address).
3) Build UI assets (Tailwind + Chart.js) via Sail:
   ```bash
   ./vendor/bin/sail npm --prefix services/go-control-plane/ui install
   ./vendor/bin/sail npm --prefix services/go-control-plane/ui run build
   ```
4) Run:
   ```bash
   go run .
   ```

## Endpoints
- `POST /api/workers/heartbeat`
- `GET /api/workers`
- `POST /api/workers/{id}/pause|resume|drain|stop`
- `GET /api/pools`
- `POST /api/pools/{pool}/scale`
- `GET /metrics` (Prometheus format; auth required)

All endpoints require `Authorization: Bearer <CONTROL_PLANE_TOKEN>`.

## UI
- Open `http://<host>:<port>/verifier-engine-room/overview`
- Use HTTP Basic Auth (any username, password = `CONTROL_PLANE_TOKEN`)
- Live updates stream from `/verifier-engine-room/events` (SSE)
- Alerts page: `/verifier-engine-room/alerts`
- Runtime settings page: `/verifier-engine-room/settings`

## Snapshots (Phase 3)
Set `MYSQL_DSN` to enable MySQL snapshots. Example:
```
MYSQL_DSN=user:pass@tcp(127.0.0.1:3306)/your_db?parseTime=true&charset=utf8mb4&loc=UTC
```
Snapshots run every `SNAPSHOT_INTERVAL_SECONDS`.

## Alerts (Phase 4)
Enable alerts and notifications with:
```
ALERTS_ENABLED=true
ALERT_CHECK_INTERVAL_SECONDS=30
ALERT_HEARTBEAT_GRACE_SECONDS=120
ALERT_COOLDOWN_SECONDS=300
ALERT_ERROR_RATE_THRESHOLD=10
AUTO_ACTIONS_ENABLED=false
```

Runtime toggles can also be changed from the Settings page. Those updates are stored in Redis and take effect without editing `.env`.

Slack:
```
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

Email (SMTP):
```
SMTP_HOST=smtp.mailserver.com
SMTP_PORT=587
SMTP_USERNAME=...
SMTP_PASSWORD=...
SMTP_FROM=ops@domain.com
SMTP_TO=ceo@domain.com,ops@domain.com
```

## Redis Keys
- `worker:{id}:status`
- `worker:{id}:heartbeat`
- `worker:{id}:desired_state`
- `worker:{id}:meta`
- `worker:{id}:metrics`
- `workers:active`
- `pools:known`
- `pool:{pool}:desired_count`
