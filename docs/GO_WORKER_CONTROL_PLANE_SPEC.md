# Go Worker Control Plane Spec (Current)

## Purpose
The Go control plane is the primary operations surface for worker runtime control, server onboarding, pool management, and policy rollout safety.

Laravel remains:
1. Database source-of-truth for server/pool/provisioning records.
2. Verifier policy payload source-of-truth.
3. Fallback heartbeat sink and admin fallback UI host.

## UI Information Architecture
Route base: `/verifier-engine-room`

### Ops
1. `/overview`
2. `/workers`
3. `/alerts`

### Infra
1. `/provisioning`
2. `/servers`
3. `/servers/{id}`
4. `/servers/{id}/edit`
5. `/pools`

### Governance
1. `/settings`

## Page Responsibilities
### Workers (daily runtime controls only)
1. Runtime table from Go heartbeat stream.
2. Actions: `Pause/Resume`, `Drain`, `Quarantine/Unquarantine`.
3. Deep links:
   - `Manage Server` when matched.
   - `Register Server` when orphan/unmatched.
4. Includes decision trace explorer for risky/unknown probe outcomes.

### Provisioning (wizard only)
1. Step 1: Add New Server or Select Server.
2. Step 2: Generate short-lived provisioning bundle.
3. Step 3: Run one-line install command on VPS.
4. Step 4: Run verification checks (manual trigger button):
   - agent reachability
   - process state
   - runtime heartbeat match
   - explicit `claim-next` API auth probe (validation-only request, no chunk claim)

No inventory table is shown on this page.

### Servers (inventory list)
1. Server list and operational state view.
2. Filter sets: `All`, `Operational`, `Needs Attention`, `Matched`, `Unmatched`.
3. Row action is `Manage` only.
4. No create/re-provision controls in list rows.

### Server Manage
1. Diagnostics and status summary.
2. Infrastructure control block:
   - `Start`, `Stop`, `Restart`, `Status`
3. Links to edit metadata and provisioning wizard.

### Server Edit
1. Dedicated metadata edit form.
2. Pool assignment is editable here.

### Pools (first-class server groups)
1. Create/Edit/Archive/Set Default.
2. Profile mapping per provider:
   - `standard`
   - `low_hit`
   - `warmup`
3. Runtime desired scaling per pool.
4. Linked server counts and policy summaries.

## Unified Status Semantics
### Process state
Derived from latest agent/systemctl status:
1. `running`
2. `starting`
3. `stopping`
4. `stopped`
5. `unknown`

### Heartbeat state
Derived from Laravel heartbeat freshness threshold:
1. `healthy`
2. `stale`
3. `none`

### Runtime match
Identity correlation between registry server and runtime worker (id/ip/host), not freshness:
1. `matched`
2. `unmatched`

Valid state example:
1. `matched + stale` means identity link exists, but heartbeat is old.

## Control Model (Critical)
### Runtime controls
`/workers` actions change scheduler intent (`desired_state`) and claim behavior:
1. `Pause/Resume`
2. `Drain`
3. `Quarantine/Unquarantine`

### Infrastructure controls
`/servers/{id}` actions target host process lifecycle via agent/systemd (`start/stop/restart/status`).

These are intentionally separate surfaces.

## Core API Surface (Go control plane)
### Worker and pool runtime
1. `POST /api/workers/heartbeat`
2. `GET /api/workers`
3. `POST /api/workers/{id}/pause|resume|drain`
4. `POST /api/workers/{id}/quarantine|unquarantine`
5. `GET /api/pools`
6. `POST /api/pools/{pool}/scale`

### Health and observability
1. `GET /api/health/ready`
2. `GET /api/incidents`
3. `GET /api/alerts`
4. `GET /api/slo`
5. `GET /metrics`

### Provider and policy controls
1. `GET /api/providers/health`
2. `GET /api/providers/quality`
3. `GET /api/providers/accuracy/calibration`
4. `GET /api/providers/unknown/clusters`
5. `GET /api/providers/unknown/reasons`
6. `GET /api/providers/retry-effectiveness`
7. `GET /api/providers/policies`
8. `POST /api/providers/{provider}/mode`
9. `POST /api/providers/policies/reload`
10. `GET /api/policies/versions`
11. `POST /api/policies/validate`
12. `POST /api/policies/promote`
13. `POST /api/policies/rollback`
14. `POST /api/policies/shadow/evaluate`
15. `GET /api/policies/shadow/runs`
16. `GET /api/policies/shadow/compare`
17. `GET /api/decisions/trace`

## Laravel Internal API Dependencies
Go UI uses internal token-auth routes in Laravel for:
1. Engine server list/create/update/delete.
2. Provisioning bundle generate/latest.
3. Server command execution/status.
4. Engine pool list/create/update/archive/set-default.
5. Decision trace list.

## Policy Safety Coupling
Before promote/rollback activation, control plane fetches policy payload from Laravel and validates required contract fields.
Rollout history stores validation metadata and checksum.

## Authentication
1. API auth: `Authorization: Bearer <CONTROL_PLANE_TOKEN>`.
2. UI auth: HTTP Basic, password = `CONTROL_PLANE_TOKEN`.

## Legacy Cleanup Status
1. Legacy `/ui/*` compatibility routes are removed.
2. Legacy workers-registry mixed template path is removed.
3. Canonical UI routes are only under `/verifier-engine-room/*`.

## Environment Keys (Core)
1. `CONTROL_PLANE_TOKEN`
2. `REDIS_ADDR`
3. `LARAVEL_INTERNAL_API_BASE_URL`
4. `LARAVEL_INTERNAL_API_TOKEN`
5. `LARAVEL_API_BASE_URL`
6. `LARAVEL_VERIFIER_TOKEN`
7. `POLICY_PAYLOAD_STRICT_VALIDATION_ENABLED`

Operational tuning keys are documented separately in:
1. `docs/GO_RUNTIME_SETTINGS_REFERENCE.md`

## Reliability
Drill runner:
1. `services/go-control-plane/scripts/run_reliability_drill.sh`

Report template:
1. `docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md`

## Final Regression Checklist (Real VPS)
1. Provisioning: create/select server -> generate bundle -> install command completes.
2. Bundle expiry: expired link handled, regenerate works, new install command works.
3. Server Manage: `start/stop/restart/status` reflect actual process state.
4. Workers page: runtime controls (`pause/resume`, `drain`, `quarantine`) do not change host process lifecycle.
5. Provisioning Step 4: claim-next auth probe reports `pass` (no 401/403).
6. Runtime match: worker heartbeat appears and matches server identity.
7. Pool assignment: selected pool persists from provisioning/edit and is visible in servers/workers.

## Go Release Freeze Checklist
1. Docs + runbooks aligned to current IA (`workers`, `provisioning`, `servers`, `pools`).
2. No temporary Go UI rollout flags remain in runtime config.
3. Legacy route/template compatibility paths removed.
4. Final operator checklist confirmed by PM on at least two real VPS workers.
