# Deep Verification Engine Plan (Phase 0)

## Purpose
This document defines the Laravel-side scaffold that lets a deep verification engine (Go now, .NET/Node later) safely pull/claim work, send heartbeats, and report results. The design keeps Laravel as the control plane and ensures worker implementations stay replaceable without schema churn.

## Entities (Laravel)
- **VerificationJob**: the unit of work for email verification.
- **EngineServer**: a verifier server tracked by heartbeat.

## Job Lifecycle (Phase 0)
1) Jobs start in `pending`.
2) Engine claims a job and receives a lease token.
3) Job status becomes `processing` with claim metadata set.
4) Engine completes the job and reports results.

## Engine API Contract v1
All endpoints are under `/api/verifier/*`, protected by:
- `auth:sanctum`
- `EnsureVerifierService`
- `throttle:verifier-api`

### POST /api/verifier/heartbeat
Upserts the engine server and records a heartbeat.

Request:
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

Errors:
- 401 Unauthorized (no Sanctum auth)
- 403 Forbidden (not verifier-service)
- 422 Validation error

### POST /api/verifier/jobs/{job}/claim
Atomically claims a pending job with a lease.

Request:
```json
{
  "engine_server_id": 12,
  "lease_seconds": 600
}
```

Response:
```json
{
  "data": {
    "id": "uuid",
    "status": "processing",
    "engine_server_id": 12,
    "claimed_at": "2026-01-14T10:00:00Z",
    "claim_expires_at": "2026-01-14T10:10:00Z",
    "claim_token": "uuid"
  }
}
```

Errors:
- 401 Unauthorized
- 403 Forbidden
- 409 Conflict (job already claimed or not pending)
- 422 Validation error

### GET /api/verifier/jobs
Lists jobs for the verifier engine.

Query:
- `status` (default: `pending`)
- `limit`

### POST /api/verifier/jobs/{job}/status
Updates job status while enforcing valid transitions.

Request:
```json
{ "status": "processing", "error_message": "optional" }
```

Errors:
- 409 Conflict on invalid transition

### POST /api/verifier/jobs/{job}/complete
Marks a job completed and stores output keys + counts.

Request:
```json
{
  "output_key": "results/{user_id}/{job_id}/cleaned.csv",
  "output_disk": "s3",
  "engine_server_id": 12,
  "total_emails": 5000,
  "valid_count": 3200,
  "invalid_count": 1400,
  "risky_count": 400,
  "unknown_count": 0
}
```

Errors:
- 409 Conflict if already completed/failed

### GET /api/verifier/jobs/{job}/download
Downloads the input file via Laravel storage (works for local or S3).

## Notes
- Storage remains disk/key based; no absolute paths are stored.
- Lease tokens are for claim coordination only, not for customer access.
- Engine implementations should avoid direct database access.
