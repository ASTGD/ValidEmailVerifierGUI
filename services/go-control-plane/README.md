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

All endpoints require `Authorization: Bearer <CONTROL_PLANE_TOKEN>`.

## UI
- Open `http://<host>:<port>/verifier-engine-room/overview`
- Use HTTP Basic Auth (any username, password = `CONTROL_PLANE_TOKEN`)

## Snapshots (Phase 3)
Set `MYSQL_DSN` to enable MySQL snapshots. Example:
```
MYSQL_DSN=user:pass@tcp(127.0.0.1:3306)/your_db?parseTime=true&charset=utf8mb4&loc=UTC
```
Snapshots run every `SNAPSHOT_INTERVAL_SECONDS`.

## Redis Keys
- `worker:{id}:status`
- `worker:{id}:heartbeat`
- `worker:{id}:desired_state`
- `worker:{id}:meta`
- `worker:{id}:metrics`
- `workers:active`
- `pools:known`
- `pool:{pool}:desired_count`
