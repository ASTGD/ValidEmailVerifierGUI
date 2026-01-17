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
    "heartbeat_threshold_minutes": 5
  }
}
```

---

### Job Claim (legacy – optional)
**POST** `/api/verifier/jobs/{job}/claim`

Payload:
```json
{ "engine_server_id": 12, "lease_seconds": 600 }
```

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
