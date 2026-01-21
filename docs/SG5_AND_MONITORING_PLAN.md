# SG5 and Monitoring plan

Status: future plan (no implementation yet).

## Goals
- SG5 Enhanced RCPT probing (opt-in, safe, compliant, accurate).
- Reputation Monitor (RBL checks + admin controls).
- Provider APIs + scoring (later).

## SG5 Enhanced RCPT probing
Scope
- Runs only in Enhanced mode (plan + job opt-in; customer consent required).
- Laravel remains control plane only (no SMTP/DNS in Laravel).

Flow (worker)
- Syntax + MX lookup + SMTP connect (existing SG1–SG4).
- `HELO/EHLO` with verified `helo_name`.
- `MAIL FROM` using per-server `mail_from_address`.
- `RCPT TO` for the real address:
  - 250 -> continue to catch-all check (if enabled).
  - 550/551/553 -> invalid.
  - 4xx -> risky (tempfail/greylist).
- Catch-all detection (optional):
  - `RCPT TO` random local-part at same domain.
  - 250 -> risky (catch_all).
  - 550 -> valid.

Outputs
- Keep categories: valid / invalid / risky.
- Add stable reason codes for SG5 (examples: `rcpt_ok`, `rcpt_rejected`, `catch_all`, `smtp_tempfail`).

Safety & compliance
- No DATA/body send in SG5.
- Strict throttling, backoff, circuit breaker.
- Per-provider policies (gmail/outlook/yahoo).
- Full audit logging with response codes.

## Identity & sender management (VPS)
Recommended model
- Store per-server identity in `engine_servers` (or related table):
  - `helo_name`
  - `mail_from_address`
  - `identity_domain`
  - `pool_tags` (gmail-safe, outlook-safe, warmup, etc.)
  - `status` (enabled/draining/disabled)
- Worker fetches identity via control plane (policy/claim payload).

DNS requirements (status only in Admin)
- SPF/DKIM/DMARC + rDNS alignment.
- Admin should **display** DNS status but **not** manage DNS directly.

## Reputation Monitor (RBL checks + admin controls)
Architecture
- Dedicated monitor service (Go container), scheduled checks.
- Performs RBL DNS lookups (outside Laravel).
- Pushes results to Laravel API (Sanctum token).

Data model (conceptual)
- `engine_server_reputation_checks` (IP, rbl, status, checked_at).
- `engine_server_blacklist_events` (first_seen, last_seen, severity).
- `engine_server_delist_requests` (status, notes, actor).

Admin controls
- View blacklist status per IP.
- Drain/disable/quarantine servers.
- Adjust rate limits + concurrency.
- Track delist requests with instructions + status.
- Alerting (email/Slack).

## Provider APIs + scoring (later)
Inputs
- Gmail Postmaster Tools (domain/IP reputation).
- Microsoft SNDS.
- Yahoo/others when available.
- Internal metrics: tempfail, reject, feedback loop outcomes.

Output
- Reputation score per IP/domain/pool.
- Routing weights based on score.

## Sequencing (recommended)
1) SG5 Enhanced RCPT probing.
2) Reputation Monitor + admin controls.
3) Provider APIs + scoring.

## Open decisions (future)
- Final reason-code list for SG5.
- Random local-part generation strategy.
- Provider policy defaults per domain.
- RBL list + check frequency.
- Whether identity is per-server or per-pool.
