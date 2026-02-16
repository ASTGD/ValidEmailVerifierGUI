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
- `POST /api/workers/{id}/quarantine|unquarantine`
- `GET /api/pools`
- `POST /api/pools/{pool}/scale`
- `GET /api/health/ready`
- `GET /api/incidents`
- `GET /api/alerts`
- `GET /api/slo`
- `GET /api/providers/health`
- `GET /api/providers/quality`
- `GET /api/providers/accuracy/calibration`
- `GET /api/providers/unknown/clusters`
- `GET /api/providers/policies`
- `POST /api/providers/{provider}/mode`
- `POST /api/providers/policies/reload`
- `GET /api/routing/effectiveness`
- `GET /api/policies/versions`
- `POST /api/policies/promote`
- `POST /api/policies/rollback`
- `POST /api/policies/shadow/evaluate`
- `GET /api/policies/shadow/runs`
- `GET /metrics` (Prometheus format; auth required)

All endpoints require `Authorization: Bearer <CONTROL_PLANE_TOKEN>`.

## UI
- Open `http://<host>:<port>/verifier-engine-room/overview`
- Use HTTP Basic Auth (any username, password = `CONTROL_PLANE_TOKEN`)
- Overview live updates stream from `/verifier-engine-room/events` (SSE)
- Alerts page: `/verifier-engine-room/alerts`
- Runtime settings page: `/verifier-engine-room/settings`
- Provider controls are available under Settings (mode override + policy reload).
- Policy rollout controls are available under Settings (promote/rollback with canary tracking).
- Poll refresh intervals for Workers/Pools/Alerts and Overview SSE cadence are configurable in Settings (stored in Redis).

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

## Reliability + automation (Phase 9+)
Optional runtime controls:
```
CONTROL_PLANE_INSTANCE_ID=node-a
SSE_WRITE_TIMEOUT_SECONDS=15
LEADER_LOCK_ENABLED=true
LEADER_LOCK_TTL_SECONDS=45
STALE_WORKER_TTL_SECONDS=86400
STUCK_DESIRED_GRACE_SECONDS=600
AUTOSCALE_ENABLED=false
AUTOSCALE_INTERVAL_SECONDS=30
AUTOSCALE_COOLDOWN_SECONDS=120
AUTOSCALE_MIN_DESIRED=1
AUTOSCALE_MAX_DESIRED=4
AUTOSCALE_CANARY_PERCENT=100
QUARANTINE_ERROR_RATE_THRESHOLD=15
PROVIDER_POLICY_ENGINE_ENABLED=false
ADAPTIVE_RETRY_ENABLED=false
PROVIDER_AUTOPROTECT_ENABLED=false
PROVIDER_TEMPFAIL_WARN_RATE=0.30
PROVIDER_TEMPFAIL_CRITICAL_RATE=0.55
PROVIDER_REJECT_WARN_RATE=0.20
PROVIDER_REJECT_CRITICAL_RATE=0.40
PROVIDER_UNKNOWN_WARN_RATE=0.20
PROVIDER_UNKNOWN_CRITICAL_RATE=0.35
```
- Leader lock protects alert/snapshot/autoscale loops in multi-instance deployments.
- Incident lifecycle is tracked in Redis (`active` and `resolved`) and exposed in `/api/incidents`.
- Worker quarantine endpoints allow auto-protect or manual quarantine for unstable workers.

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
- `worker:{id}:stage_metrics`
- `worker:{id}:smtp_metrics`
- `worker:{id}:provider_metrics`
- `worker:{id}:quarantined`
- `workers:active`
- `pools:known`
- `pool:{pool}:desired_count`
- `control_plane:incident:*`
- `control_plane:leader:*`
- `control_plane:provider_modes`
- `control_plane:provider_policy_state`
- `control_plane:smtp_policy_versions`
- `control_plane:smtp_policy_active`
- `control_plane:smtp_policy_rollout_history`
- `control_plane:smtp_policy_shadow_runs`
