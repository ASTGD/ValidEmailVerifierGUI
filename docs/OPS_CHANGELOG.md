# Operational Change Log

This is the PM/Admin-facing release stream for operations behavior.

For Codex continuity and implementation handoff details, use `docs/CONTEXT_HANDOFF.md`.

## 2026-02
- Queue segmentation and queue recovery hardening completed.
- Unified internal pipeline enforced: `screening -> smtp_probe`.
- Go control-plane matured with policy lifecycle (`validate/promote/rollback`) and safety checks.
- Provider allowlist and policy retry regressions fixed (`4.7.x` retryable; `5.7.x` policy-blocked).
- SG6 baseline delivered: consent-gated, admin-run, webhook integrity + replay/idempotency protections.
- Internal docs portal launched and linked from Laravel Admin, Horizon, and Go panel.
- Go settings UX hardened with inline help tips, recommended values, dynamic risk bands, and save guards.
- Engine server daily operations moved to Go Workers page (Laravel remains DB/provisioning source of truth).

## 2026-02 (ongoing)
- Worker process-control foundation added for remote start/stop orchestration.
- Docker worker image supply-chain improvements: digest pinning/canary flow + verification controls.
- Go UI information architecture finalized to 4 operational surfaces:
  - Workers (runtime scheduling only)
  - Provisioning (wizard only)
  - Servers (inventory + manage/edit)
  - Pools (first-class server groups)
- Pools became first-class infrastructure objects with:
  - create/edit/archive/set-default
  - server assignment from provisioning/edit flows
  - provider profile mapping (`standard`, `low_hit`, `warmup`)
- Provisioning flow hardened:
  - explicit Run Verification Checks action
  - expired/latest bundle handling improvements
  - clearer install + verify sequence
- Go closeout hardening:
  - removed legacy `/ui/*` and workers-registry compatibility UI paths
  - Step 4 claim-next sanity is now explicit API auth probe (validation-only, non-claiming)
  - runtime vs infrastructure control copy/tooltips finalized
- Migration/test-state hardening completed:
  - duplicate checkout-intent migrations made safe/idempotent
  - full suite green (`210 passed`)
