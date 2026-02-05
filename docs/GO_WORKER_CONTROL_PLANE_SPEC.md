# Go Worker Control Plane Spec (External Service)

## Purpose
Provide a lightweight control plane for Go workers that owns:
- Worker registration and heartbeat status
- Desired-state commands (pause, resume, drain, stop)
- Pool scaling targets
- Telemetry/metrics for the Go dashboard

Laravel remains control-light and only links to this dashboard.

## Terminology
- **Worker**: A Go verifier process.
- **Pool**: Logical grouping (region, IP pool, reputation tier, or tag).
- **Desired state**: Target status assigned by control plane.

## Authentication
Use a static bearer token (env-based).
- `GO_CONTROL_PLANE_TOKEN` (stored outside this repo)

## Worker States
- `running`
- `paused` (no new chunks)
- `draining` (finish current chunk, then idle)
- `stopped`

## API Endpoints

### POST /api/workers/heartbeat
Registers or updates worker state.

Request:
```json
{
  "worker_id": "worker-01",
  "host": "node-a",
  "ip_address": "x.x.x.x",
  "version": "1.2.3",
  "pool": "default",
  "tags": ["us-east", "warm"],
  "status": "running",
  "current_job_id": "uuid-or-null",
  "current_chunk_id": "uuid-or-null",
  "metrics": {
    "emails_per_sec": 120,
    "errors_per_min": 2,
    "cache_hit_rate": 0.45,
    "avg_latency_ms": 140
  }
}
```

Response:
```json
{
  "desired_state": "running",
  "commands": []
}
```

### GET /api/workers
Returns worker list.

Response:
```json
{
  "data": [
    {
      "worker_id": "worker-01",
      "host": "node-a",
      "pool": "default",
      "status": "running",
      "last_heartbeat_at": "2026-02-05T12:34:56Z",
      "current_job_id": "uuid-or-null"
    }
  ]
}
```

### POST /api/workers/{worker_id}/pause
### POST /api/workers/{worker_id}/resume
### POST /api/workers/{worker_id}/drain
### POST /api/workers/{worker_id}/stop
Sets desired state for a worker.

Response:
```json
{ "desired_state": "paused" }
```

### GET /api/pools
Returns pool summary.

Response:
```json
{
  "data": [
    { "pool": "default", "online": 3, "desired": 5 }
  ]
}
```

### POST /api/pools/{pool}/scale
Sets desired count for a pool.

Request:
```json
{ "desired": 5 }
```

Response:
```json
{ "pool": "default", "desired": 5 }
```

## Redis Schema (Suggested)
- `worker:{id}:state` -> current state (string)
- `worker:{id}:desired` -> desired state (string)
- `worker:{id}:heartbeat` -> timestamp (ISO-8601)
- `worker:{id}:metrics` -> JSON blob (short-lived)
- `pool:{name}:desired` -> desired worker count
- `pool:{name}:online` -> computed count (optional cache)

TTL: heartbeat/metrics keys expire after N seconds to detect offline workers.

## Worker Agent Loop (Simplified)
1) On startup, POST `/heartbeat`.
2) Every N seconds:
   - POST `/heartbeat`.
   - Apply desired state:
     - `paused`: do not claim new chunks
     - `draining`: finish current chunk, then idle
     - `stopped`: shutdown

## Metrics Strategy
Expose metrics via:
- `/api/workers` payload (simple), or
- Prometheus endpoint on the control plane (preferred for Grafana).

## Laravel Integration
Laravel only links to the Go dashboard and shows read-only summary stats if needed.
No Laravel-side control actions are required.
