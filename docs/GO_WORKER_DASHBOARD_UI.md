# Go Worker Dashboard UI (Horizon-Style)

## Goals
- Single control center for Go workers
- Live visibility + safe control actions
- Minimal coupling to Laravel

## Pages
1) **Overview**
   - Online/offline workers
   - Active jobs/chunks
   - Throughput + error rate (last 5–15 min)
   - Pool health summary

2) **Workers**
   - Table: worker_id, host, pool, status, last heartbeat, current job/chunk
   - Actions: Pause, Resume, Drain, Stop

3) **Pools**
   - Desired count per pool
   - Online count
   - Scale controls (increase/decrease)

4) **Jobs / Chunks (read-only)**
   - Active jobs and which workers are processing them
   - Optional: chunk progress

5) **Alerts**
   - Offline workers
   - High error rate
   - Blacklist/reputation issues

## UI Stack (Go-native)
- Go HTML templates
- Tailwind (compiled) or PicoCSS
- Optional: HTMX for partial updates
- Charts: Chart.js (lightweight)

## Update Strategy
Option A (simple): auto-refresh every N seconds.
Option B (better): SSE or WebSocket for live updates.

## Access Control
- Admin-only token header
- Optional IP allowlist

## Laravel Integration
Add a sidebar link in Filament to open the Go dashboard in a new tab.
- Example: `http://<host>:<port>/verifier-engine-room/overview`

## Implementation Notes
- UI lives in `services/go-control-plane/templates` with Tailwind + Chart.js assets in `services/go-control-plane/assets`.
- Access is protected by HTTP Basic Auth (password = `CONTROL_PLANE_TOKEN`).
- History charts load when MySQL snapshots are enabled.
