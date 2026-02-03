# Engine Contract v1

This contract defines the language-agnostic API that deep verification workers (Go/.NET/Node) use to integrate with the Laravel control plane.

## Entities
- **VerificationJob**: the top-level verification request created by a customer upload.
- **VerificationJobChunk**: a chunk of unknown emails created by Laravel for deep verification.
- **EngineServer**: a worker host tracked by heartbeat.

## Chunk Lifecycle
`pending` → `processing` (lease) → `completed` or `failed`

- Chunks start in `pending`.
- Workers should only process chunks that are pending and/or leased to them.
- Leases prevent double processing; leases expire automatically and allow re-claiming.

## Storage Rules
- Laravel stores only `disk` + `key` values (no absolute paths).
- Workers must use signed URLs for I/O (S3 presigned URLs in production).
- Workers should **not** access Laravel’s database directly.
- Finalization (merge) happens in Laravel once all chunks are completed.
- Admin observability lives in the Filament “Engine” and “Verification Jobs” screens.

## Control Plane Boundary
- Laravel performs only parsing, normalization, dedupe, cache lookups, chunking, and final merge.
- **MX/DNS/SMTP checks are never performed by Laravel** and must be done by the external engine workers.

## SG4 Feedback Loop (Outcomes Cache)
Laravel can short-circuit verification using cached outcomes to reduce SMTP work.

Stored outcome fields:
- `email_hash` (sha256 hex of normalized email)
- `outcome` (`valid`, `invalid`, `risky`)
- `reason_code` (optional)
- `observed_at`
- `source` (optional)

Freshness behavior:
- Outcomes are only treated as cache hits when `observed_at` is within `engine.cache_freshness_days`.

Ingestion:
- Admin CSV import (Filament “Feedback Imports”).
- Optional API: `POST /api/feedback/outcomes` (auth:sanctum + admin/service token).

Safety controls:
- Kill switch: `engine.feedback_api_enabled` disables the API.
- Rate limiting: `engine.feedback_rate_limit_per_minute`.
- Payload limits: `engine.feedback_max_items_per_request` and `engine.feedback_max_payload_kb`.
- Retention: outcomes pruned after `engine.feedback_retention_days`, imports pruned after `engine.feedback_import_retention_days`.

Cache write-back (DynamoDB, optional):
- After finalization, Laravel can write verified cache-miss outcomes back to DynamoDB.
- Only emails that were **cache misses during parsing** are eligible for write-back.
- Only `valid` and `invalid` statuses are written (risky/unknown are skipped).
- Items written with attributes: `email`, `result` (`Valid`/`Invalid`), `DateTime` (ISO-8601).
- Write-back is controlled by admin settings (batch size, throttle, retry/backoff, failure mode).
- Test-only mode (cache-only runs) can write cache-miss emails to a separate test table with `result = Cache_miss`.

## Verification Modes
Workers must honor the `verification_mode` supplied by Laravel:
- `standard` (default): Signal Groups 1–4.
- `enhanced` (opt-in): gated and currently behaves like Standard until mailbox-level signals are implemented.

Field:
- `verification_mode` (string): `standard` or `enhanced`. Returned in claim-next and chunk details.

Guardrails for `enhanced` mode (design-level):
- Opt-in only (plan-gated) with explicit audit logs.
- Strict rate limits and safety thresholds.
- Kill switch available to admins to disable enhanced mode globally.

Portal usage:
- Customer list uploads and single-check requests set `verification_mode` at job creation.
- Enhanced is only selectable when global settings, enhanced policy, and customer entitlement allow it.

## Connectivity-Based Classification (Phase 8B)
In Phase 8B the worker performs **DNS/MX + SMTP connectivity checks** (EHLO + QUIT only). No mailbox-level RCPT probing is performed.

Classification meaning:
- **valid**: the domain mail flow is reachable (`smtp_connect_ok`).
- **invalid**: permanent issues (`syntax`, `mx_missing`, `smtp_unavailable`).
- **risky**: transient/network issues (`dns_timeout`, `dns_servfail`, `smtp_connect_timeout`, `smtp_timeout`, `smtp_tempfail`).

Reason codes (output CSV `reason` column):
| Category | Reason code |
| --- | --- |
| invalid | `syntax` |
| invalid | `mx_missing` |
| invalid | `smtp_unavailable` |
| risky | `dns_timeout` |
| risky | `dns_servfail` |
| risky | `smtp_connect_timeout` |
| risky | `smtp_timeout` |
| risky | `smtp_tempfail` |
| risky | `disposable_domain` |
| risky | `role_account` |
| risky | `domain_typo_suspected:suggest=<domain>` |
| valid | `smtp_connect_ok` |

Notes:
- `domain_typo_suspected` includes the suggested domain in the reason string.

## Mailbox Probing (SG5 Enhanced)
Enhanced mode performs **RCPT probing** using `HELO` + `MAIL FROM` + `RCPT TO`.
It does not send a message body.

Classification meaning (Enhanced only):
- **valid**: RCPT accepted (`rcpt_ok`).
- **invalid**: RCPT rejected (`rcpt_rejected`).
- **risky**: catch-all detected or tempfail (`catch_all`, `smtp_tempfail`).

Reason codes (Enhanced only):
| Category | Reason code |
| --- | --- |
| valid | `rcpt_ok` |
| invalid | `rcpt_rejected` |
| risky | `catch_all` |

## Final Output Schema (CSV)
Final merged outputs are generated by Laravel and streamed to storage. Each row is:
```
email,status,sub_status,score,reason
```

Fields:
- **status**: `valid` | `invalid` | `risky`
- **sub_status**: `catch_all` | `mailbox_not_found` | `smtp_connect_ok` | `mx_missing` | `syntax` | `disposable_domain` | `role_account` | `domain_typo_suspected` | `timeout` | `tempfail` | `unknown`
- **score**: Deliverability Confidence Score (0–100)
- **reason**: stable reason code (may include extra context, e.g. `domain_typo_suspected:suggest=gmail.com`)

Back-compat:
- Chunk outputs from workers remain `email,reason` and are normalized during finalization.

## Deliverability Confidence Score
Laravel assigns a deterministic score per row using only existing signals (no new probing):
- Base scores depend on status (valid/invalid/risky) with overrides for key reasons.
- Catch-all results are capped (configurable).
- Recent SG4 outcome cache hits adjust the score (positive for recent valid, negative for recent invalid).
- The final score is clamped to 0–100.

Catch-all policy:
- `risky_only` (default) always outputs catch-all as `risky`.
- `promote_if_score_gte` promotes catch-all to `valid` when the score meets the configured threshold.

## Work Distribution / Queue Strategy
“Workers pull work by calling claim-next; Laravel remains the source of truth and atomically leases chunks to workers. We may introduce a broker queue later, but the worker contract remains unchanged.”

## Authentication
All endpoints below require:
- `auth:sanctum`
- `EnsureVerifierService`
- `throttle:verifier-api`

## Error Codes
- **200/201**: success
- **401**: unauthorized (missing/invalid token)
- **403**: forbidden (not verifier-service)
- **409**: conflict (already claimed/completed or conflicting payload)
- **422**: validation error

---

## Engine API Endpoints

### Heartbeat
**POST** `/api/verifier/heartbeat`

Payload:
```json
{
  "server": {
    "name": "engine-1",
    "ip_address": "192.168.1.10",
    "environment": "prod",
    "region": "us-east-1",
    "meta": { "version": "1.0.0" }
  }
}
```

Response:
```json
{
  "data": {
    "server_id": 12,
    "status": "online",
    "heartbeat_threshold_minutes": 5,
    "identity": {
      "helo_name": "verify.example.com",
      "mail_from_address": "verify@verify.example.com",
      "identity_domain": "verify.example.com"
    }
  }
}
```

---

### Policy
**GET** `/api/verifier/policy`

Response:
```json
{
  "data": {
    "contract_version": "v1",
    "engine_paused": false,
    "enhanced_mode_enabled": false,
    "policies": {
      "standard": {
        "mode": "standard",
        "enabled": true,
        "dns_timeout_ms": 2000,
        "smtp_connect_timeout_ms": 2000,
        "smtp_read_timeout_ms": 2000,
        "max_mx_attempts": 2,
        "max_concurrency_default": 1,
        "per_domain_concurrency": 2,
        "catch_all_detection_enabled": false,
        "global_connects_per_minute": null,
        "tempfail_backoff_seconds": null,
        "circuit_breaker_tempfail_rate": null
      },
      "enhanced": {
        "mode": "enhanced",
        "enabled": false,
        "dns_timeout_ms": 2000,
        "smtp_connect_timeout_ms": 2000,
        "smtp_read_timeout_ms": 2000,
        "max_mx_attempts": 2,
        "max_concurrency_default": 1,
        "per_domain_concurrency": 2,
        "catch_all_detection_enabled": true,
        "global_connects_per_minute": null,
        "tempfail_backoff_seconds": null,
        "circuit_breaker_tempfail_rate": null
      }
    }
  }
}
```

Behavior:
- When `engine_paused` is true, workers should idle and `claim-next` returns 204.
- When `enhanced_mode_enabled` is false, enhanced requests run in standard mode with a warning log.

---

### Job Claim (legacy – optional)
**POST** `/api/verifier/jobs/{job}/claim`

Payload:
```json
{ "engine_server_id": 12, "lease_seconds": 600 }
```

---

### Chunk Claim-Next (worker pull)
**POST** `/api/verifier/chunks/claim-next`

Payload:
```json
{
  "engine_server": {
    "name": "engine-1",
    "ip_address": "192.168.1.10",
    "environment": "prod",
    "region": "us-east-1",
    "meta": { "version": "1.0.0" }
  },
  "worker_id": "engine-1:worker-1",
  "lease_seconds": 600
}
```

Response:
```json
{
  "data": {
    "chunk_id": "uuid",
    "job_id": "uuid",
    "chunk_no": 1,
    "verification_mode": "standard",
    "lease_expires_at": "2026-01-14T10:10:00Z",
    "input": { "disk": "s3", "key": "chunks/{job}/{chunk}/input.txt" }
  }
}
```

If no chunk is available, the endpoint returns **204 No Content**.

---

### Chunk Details
**GET** `/api/verifier/chunks/{chunk}`

Response:
```json
{
  "data": {
    "chunk_id": "uuid",
    "job_id": "uuid",
    "chunk_no": 1,
    "status": "pending",
    "attempts": 0,
    "verification_mode": "standard",
    "input": { "disk": "s3", "key": "chunks/{job}/{chunk}/input.txt" },
    "output": {
      "disk": "s3",
      "valid_key": "results/chunks/{job}/{chunk}/valid.csv",
      "invalid_key": "results/chunks/{job}/{chunk}/invalid.csv",
      "risky_key": "results/chunks/{job}/{chunk}/risky.csv"
    },
    "policy": {
      "lease_seconds": 600,
      "max_attempts": 3,
      "signed_url_expiry_seconds": 300
    }
  }
}
```

---

### Chunk Logs
**POST** `/api/verifier/chunks/{chunk}/log`

Payload:
```json
{
  "level": "info",
  "event": "smtp_batch_started",
  "message": "Processing gmail.com",
  "context": { "domain": "gmail.com" }
}
```

---

### Chunk Complete (idempotent)
**POST** `/api/verifier/chunks/{chunk}/complete`

Payload:
```json
{
  "output_disk": "s3",
  "valid_key": "results/chunks/{job}/{chunk}/valid.csv",
  "invalid_key": "results/chunks/{job}/{chunk}/invalid.csv",
  "risky_key": "results/chunks/{job}/{chunk}/risky.csv",
  "email_count": 5000,
  "valid_count": 3200,
  "invalid_count": 1400,
  "risky_count": 400
}
```

Idempotency:
- If called again with the **same payload**, return success (no-op).
- If called again with **conflicting payload**, return **409**.

---

### Chunk Fail (retry policy, idempotent)
**POST** `/api/verifier/chunks/{chunk}/fail`

Payload:
```json
{ "error_message": "timeout contacting mx1.example.com", "retryable": true }
```

Behavior:
- Attempts increment each call.
- If `retryable` and attempts < `engine.max_attempts` → status `pending`.
- Otherwise → status `failed`.
- Repeated calls when already failed are treated as no-ops.

---

### Job Complete (idempotent)
**POST** `/api/verifier/jobs/{job}/complete`

Payload:
```json
{
  "output_disk": "s3",
  "output_key": "results/{user_id}/{job_id}/cleaned.csv",
  "total_emails": 5000,
  "valid_count": 3200,
  "invalid_count": 1400,
  "risky_count": 400,
  "unknown_count": 0
}
```

Idempotency:
- Same payload → success (no-op).
- Conflicting payload → **409**.

---

### Signed URLs (I/O)
**GET** `/api/verifier/chunks/{chunk}/input-url`

Response:
```json
{
  "data": {
    "disk": "s3",
    "key": "chunks/{job}/{chunk}/input.txt",
    "url": "https://signed-url",
    "expires_in": 300
  }
}
```

**POST** `/api/verifier/chunks/{chunk}/output-urls`

Response:
```json
{
  "data": {
    "disk": "s3",
    "expires_in": 300,
    "targets": {
      "valid":   { "key": "results/chunks/{job}/{chunk}/valid.csv", "url": "https://signed-put" },
      "invalid": { "key": "results/chunks/{job}/{chunk}/invalid.csv", "url": "https://signed-put" },
      "risky":   { "key": "results/chunks/{job}/{chunk}/risky.csv", "url": "https://signed-put" }
    }
  }
}
```

---

## Blacklist Monitor API
Used by the external blacklist monitor service (Go) to report RBL checks.

All endpoints require:
- `auth:sanctum`
- `EnsureVerifierService`
- `throttle:verifier-api`

### Config
**GET** `/api/monitor/config`

Response:
```json
{
  "data": {
    "enabled": true,
    "interval_minutes": 60,
    "rbl_list": [
      "rbl.example"
    ],
    "resolver_mode": "system",
    "resolver_ip": null,
    "resolver_port": 53
  }
}
```

### Servers
**GET** `/api/monitor/servers`

Response:
```json
{
  "data": {
    "servers": [
      {
        "id": 1,
        "name": "engine-1",
        "ip_address": "192.0.2.10",
        "environment": "prod",
        "region": "us-east-1",
        "is_active": true,
        "drain_mode": false,
        "last_heartbeat_at": "2026-01-25T12:00:00Z"
      }
    ]
  }
}
```

### Checks
**POST** `/api/monitor/checks`

Payload:
```json
{
  "server_id": 1,
  "server_ip": "192.0.2.10",
  "checked_at": "2026-01-25T12:05:00Z",
  "results": [
    {
      "rbl": "rbl.example",
      "listed": false,
      "response": null,
      "error_message": null
    }
  ]
}
```

Response:
```json
{
  "data": {
    "checks": 1,
    "listed": 0
  }
}
```

---

## Reference Worker Flow (Phase 8A/8B)
1) Claim a chunk via `POST /api/verifier/chunks/claim-next`.
2) Fetch `input-url` and download chunk input.
3) Perform verification in the worker (Phase 8B: DNS/MX + SMTP connectivity only).
4) Request signed `output-urls`.
5) Upload outputs via signed PUT URLs.
6) Call `complete` (or `fail`) for the chunk.
7) Laravel finalizes the job when all chunks are completed.
