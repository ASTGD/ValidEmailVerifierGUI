# Documentation Map

Use this map to keep docs clean and reduce drift.

## 1) PM/Admin Operations (internal docs portal)
These files are rendered in `/internal/docs`:
- `docs/OPS_PLATFORM_OVERVIEW.md`
- `docs/LARAVEL_ADMIN_OPERATIONS.md`
- `docs/HORIZON_QUEUE_OPERATIONS.md`
- `docs/GO_RUNTIME_SETTINGS_REFERENCE.md`
- `docs/GO_TUNING_PLAYBOOK.md`
- `docs/SG6_OPERATIONS.md`
- `docs/OPS_RUNBOOKS_DRILLS.md`
- `docs/OPS_CHANGELOG.md`

## 2) Engineering Specs
- `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`
- `docs/ENGINE_CONTRACT.md`
- `docs/DEEP_VERIFICATION_ENGINE_PLAN.md`

## 3) Developer Runbooks
- `docs/DEV_SERVERS.md`
- `docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md`

## 4) Continuity (Codex handoff)
- `docs/CONTEXT_HANDOFF.md`

## 5) Archived/Historical
- `docs/archive/`

Rules:
1. Keep `docs/CONTEXT_HANDOFF.md` as the single continuity handoff file.
2. Do not add PM/Admin release notes to `CONTEXT_HANDOFF.md`; use `docs/OPS_CHANGELOG.md`.
3. Avoid creating duplicate docs for the same topic. Update canonical files instead.
