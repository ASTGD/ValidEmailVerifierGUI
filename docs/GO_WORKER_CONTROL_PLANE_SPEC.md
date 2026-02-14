# Go Worker Control Plane Spec (Current Runtime)

## Purpose
The Go control plane is the operational source of truth for Go worker runtime state:
- Worker and pool liveness (primary heartbeat path)
- Desired-state control (`running`, `paused`, `draining`, `stopped`)
- Provider health/mode controls
- SMTP policy rollout controls (validate/promote/rollback)
- Probe quality and routing effectiveness telemetry

Laravel remains the policy payload source-of-truth and fallback heartbeat sink.

## Source-of-Truth Split
- **Primary operational heartbeat:** `POST /api/workers/heartbeat` (Go control plane)
- **Fallback identity/liveness heartbeat:** `POST /api/verifier/heartbeat` (Laravel)

Practical rule:
- Ops controls, incidents, and pool state come from the Go control plane.
- Laravel heartbeat is retained as fallback/identity refresh and does not own worker desired-state control.

## Authentication
- API: `Authorization: Bearer <CONTROL_PLANE_TOKEN>`
- UI: HTTP Basic Auth
  - Username: any non-empty value
  - Password: `CONTROL_PLANE_TOKEN`

## Core Endpoints
### Worker and pool control
- `POST /api/workers/heartbeat`
- `GET /api/workers`
- `POST /api/workers/{id}/pause|resume|drain|stop`
- `POST /api/workers/{id}/quarantine|unquarantine`
- `GET /api/pools`
- `POST /api/pools/{pool}/scale`

### Health/alerts/metrics
- `GET /api/health/ready`
- `GET /api/incidents`
- `GET /api/alerts`
- `GET /api/slo`
- `GET /metrics`

### Provider controls
- `GET /api/providers/health`
- `GET /api/providers/quality`
- `GET /api/providers/policies`
- `POST /api/providers/{provider}/mode`
- `POST /api/providers/policies/reload`

### Policy lifecycle controls
- `GET /api/policies/versions`
- `POST /api/policies/validate`
- `POST /api/policies/promote`
- `POST /api/policies/rollback`

## SMTP Policy Safety Coupling
Before activation (`promote`/`rollback`), control plane preflights policy payload from Laravel:
- Fetch: `GET /api/verifier/policy-versions/{version}/payload`
- Validate required schema fields (minimum contract):
  - `enabled`
  - `version`
  - `profiles.generic.retry` with required retry keys
- Reject activation if payload is missing/invalid

Rollout records persist validation metadata:
- `validation_status`
- `payload_checksum`
- `payload_validated_at`

## Rotation Observability Telemetry
Workers send routing counters in heartbeat payload:
- `retry_claims_total`
- `retry_anti_affinity_success_total`
- `same_worker_avoid_total`
- `same_pool_avoid_total`
- `provider_affinity_hit_total`
- `fallback_claim_total`

Control plane aggregates these into routing-quality KPIs and exposes them in UI and Prometheus metrics.

## Canary Autopilot (KPI Closed Loop)
Autopilot can progress canary rollout automatically:
- Step ladder: `5 -> 25 -> 50 -> 100`
- Progression requires consecutive healthy windows
- Automatic rollback gates on KPI regression:
  - unknown-rate regression
  - tempfail-recovery drop
  - policy-block spike
- Manual override wins:
  - if explicit manual provider mode override is active, autopilot does not mutate rollout state

## Key Environment Variables
### Required
- `PORT`
- `CONTROL_PLANE_TOKEN`
- `REDIS_ADDR`

### Policy payload coupling
- `LARAVEL_API_BASE_URL`
- `LARAVEL_VERIFIER_TOKEN`
- `POLICY_PAYLOAD_STRICT_VALIDATION_ENABLED=true`

### Autopilot
- `POLICY_CANARY_AUTOPILOT_ENABLED=false`
- `POLICY_CANARY_WINDOW_MINUTES=15`
- `POLICY_CANARY_REQUIRED_HEALTH_WINDOWS=4`
- `POLICY_CANARY_UNKNOWN_REGRESSION_THRESHOLD=0.05`
- `POLICY_CANARY_TEMPFAIL_RECOVERY_DROP_THRESHOLD=0.10`
- `POLICY_CANARY_POLICY_BLOCK_SPIKE_THRESHOLD=0.10`

### Multi-instance safety / operations
- `LEADER_LOCK_ENABLED=true`
- `LEADER_LOCK_TTL_SECONDS=45`
- `STALE_WORKER_TTL_SECONDS=86400`
- `STUCK_DESIRED_GRACE_SECONDS=600`

## Redis Keys (Operational)
- `worker:{id}:status|heartbeat|last_seen|meta|metrics|stage_metrics|smtp_metrics|provider_metrics|routing_metrics`
- `worker:{id}:desired_state|desired_state_updated|quarantined|pool`
- `workers:active`, `workers:known`, `pools:known`
- `pool:{pool}:desired_count`
- `control_plane:incident:*`, `control_plane:incidents:*`
- `control_plane:provider_modes`
- `control_plane:provider_policy_state`
- `control_plane:smtp_policy_versions`
- `control_plane:smtp_policy_active`
- `control_plane:smtp_policy_rollout_history`

## Reliability Drills
Run drill scenarios from:
- `services/go-control-plane/scripts/run_reliability_drill.sh`

Use template:
- `docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md`
