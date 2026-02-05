# Go Worker Control Plane (Phase 1)

Lightweight Go service that tracks worker heartbeats, desired state, and pool scaling.

## Setup
1) Copy env example:
   ```bash
   cp .env.example .env
   ```
2) Set values in `.env` (token + Redis address).
3) Run:
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

## Redis Keys
- `worker:{id}:status`
- `worker:{id}:heartbeat`
- `worker:{id}:desired_state`
- `worker:{id}:meta`
- `worker:{id}:metrics`
- `workers:active`
- `pools:known`
- `pool:{pool}:desired_count`
