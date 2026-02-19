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
