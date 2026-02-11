# Go Worker Dashboard UI (Current)

## Goal
A single Go-native operations surface for worker control, provider quality, policy rollout, and safety automation.

## Main Pages
1. **Overview**
- Worker/pool/desired/error/incident summary
- Provider health table
- Probe SLO snapshot
- **Shard Routing Quality** panel (anti-affinity, affinity hit, fallback, pool skew)
- Live updates via SSE (`/verifier-engine-room/events`)

2. **Workers**
- Worker status, desired state, quarantine state
- Current job/chunk context
- Stage + SMTP + provider telemetry
- Control actions: pause/resume/drain/stop/quarantine

3. **Pools**
- Online vs desired per pool
- Manual scale control

4. **Alerts**
- Recent persisted alerts (`go_alerts`)
- Incident lifecycle visibility (active/resolved)

5. **Settings**
- Runtime safety toggles (alerts, auto-actions, autoscale)
- Provider mode controls + policy reload
- **Policy rollout lifecycle**
  - Validate payload
  - Promote validated version (canary)
  - Rollback
  - Validation badge and rollout history metadata
- Displays strict payload validation + autopilot gate config

## Live Update Model
- Overview uses SSE to keep cards/charts/quality panels fresh.
- Workers/Pools/Alerts use configurable refresh intervals from runtime settings.

## Access Control
- HTTP Basic Auth (password = `CONTROL_PLANE_TOKEN`)
- API bearer token auth for programmatic access
- Same-origin enforcement on all browser/UI POST routes

## Integration Notes
- Control plane heartbeat is operational truth.
- Laravel heartbeat remains fallback identity/liveness path.
- Policy payloads are sourced from Laravel policy-version endpoint.

## Operator Workflow (Policy)
1. Validate policy payload in Laravel admin (`SMTP Policy Versions`).
2. Validate same version in Go settings (preflight + checksum metadata sync).
3. Promote with canary percent.
4. Observe provider quality + routing quality.
5. Rollback immediately if KPI gates regress.
