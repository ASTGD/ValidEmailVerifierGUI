# Laravel Admin Operations

## What This Area Owns
- Customer/account operations
- Verification job visibility
- Engine settings (policy and queue-related config)
- SG6 consent and campaign approvals

## Daily Checklist
1. Open Ops Overview and check queue incidents.
2. Check finalization health and stuck chunks.
3. Confirm SG6 campaigns are not paused due to delivery issues.
4. Review recent admin actions before changing runtime settings.

## Safe Change Rules
1. Do not change multiple reliability settings at once.
2. Record reason before any manual recovery action.
3. Prefer queue-lane-specific actions over global restarts.
4. If unsure, use conservative values and monitor for one window.

## Engine Server Fallback (Break-Glass Only)
1. Normal daily engine operations must be done from Go: `/verifier-engine-room/workers`.
2. Laravel `/admin/engine-servers` is emergency fallback only.
3. Fallback is gated by config:
- `ENGINE_SERVERS_FALLBACK_UI_ENABLED`
- `ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_ONLY`
- `ENGINE_SERVERS_FALLBACK_UI_SUPER_ADMIN_EMAILS`
4. Keep fallback disabled by default in production. Enable only during Go control-plane incident response and disable again after recovery.

## Related Links
- Horizon operations: `/internal/docs/horizon/queue-operations`
- Go runtime settings: `/internal/docs/go/runtime-settings`
