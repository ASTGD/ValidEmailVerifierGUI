# Deep Verification Engine Plan (Phase 0–1)

For the stable worker API contract, see `docs/ENGINE_CONTRACT.md`.

## Purpose
This document defines the Laravel-side scaffold that lets a deep verification engine (Go now, .NET/Node later) safely pull/claim work, send heartbeats, and report results. The design keeps Laravel as the control plane and ensures worker implementations stay replaceable without schema churn.

Laravel is the control plane only:
- **No MX/DNS/SMTP verification is performed in Laravel.**
- Deep verification (MX/DNS/SMTP) happens exclusively in external engine workers.

## Verification Modes
Two verification modes are supported at the product level:
- **Standard (default):** uses Signal Groups 1–4 and is the current shipping mode.
- **Enhanced (opt-in):** mailbox-level signals (TBD), guarded and auditable. Pricing is TBD.

Enhanced mode is a separate opt-in pathway exposed in the Admin + Customer portals. It must be explicitly enabled, is not part of the default verification flow, and is not implemented in the worker yet (requests run in standard mode).

## Signal Groups 1–4 (Standard)
- **SG1 Domain health:** DNS/MX availability and domain-level health signals.
- **SG2 SMTP connectivity/policy:** EHLO + QUIT connectivity checks (no mailbox probing).
- **SG3 Enrichment:** role/disposable/typo/IDN heuristics and enrichment.
- **SG4 Feedback loop + cache:** outcomes ingestion + freshness-aware cache lookups (MySQL-first, NoSQL-ready).

## Portal Placement (Design)
- **Customer portal:** mode selector on upload/checkout (plan-gated).
- **Admin portal:** mode policy + per-job override + audit logs for mode changes.

## Entities (Laravel)
- **VerificationJob**: the unit of work for email verification.
- **VerificationJobChunk**: chunked input units created by the Laravel pipeline.
- **EngineServer**: a verifier server tracked by heartbeat.

## Job Lifecycle (Phase 0)
1) Jobs start in `pending`.
2) Engine claims a job and receives a lease token.
3) Job status becomes `processing` with claim metadata set.
4) Engine completes the job and reports results.

## Job Preparation Pipeline (Phase 1)
Laravel prepares work for the deep verification engine by parsing, deduping, and chunking input files.

### Stage 0–2 Steps
1) **Parse + Normalize + Dedupe** (streaming)
   - TXT: one email per line, plus tokens parsed from each line.
   - CSV: first column preferred, plus any email-like tokens in the row.
   - XLS/XLSX: read in row batches using PhpSpreadsheet (read-only, chunked).
2) **Cache Lookup (SG4 feedback loop)**
   - Batch lookup via `EmailVerificationCacheStore::lookupMany`.
   - Database-backed cache store uses hashed emails for lookup.
   - Freshness window enforced by `engine.cache_freshness_days`.
   - Outcomes ingestion is rate limited and size capped by `engine.feedback_*` limits.

## SG4 Production Hardening (Phase 11)
- Feedback API kill switch via `engine.feedback_api_enabled`.
- Rate limits: `engine.feedback_rate_limit_per_minute`.
- Payload limits: `engine.feedback_max_items_per_request`, `engine.feedback_max_payload_kb`.
- Retention jobs prune outcomes (`engine.feedback_retention_days`) and imports (`engine.feedback_import_retention_days`).
3) **Chunk Creation (pending for workers)**
   - Unknown emails are written to chunk files on the configured disk.
   - Chunk keys: `chunks/{job_uuid}/{chunk_no}/input.txt`.
   - `verification_job_chunks` rows created with counts and linkage.

### Phase 1 Notes
- Parsing is streaming for TXT/CSV and row-batched for XLS/XLSX.
- Max email limit is enforced via config (`VERIFIER_MAX_EMAILS_PER_UPLOAD`).
- Deduping uses an in-memory set with SQLite fallback when limits are exceeded.

## Phase 7 — Admin Observability & Ops Controls
Before SMTP workers go live, admins need clear visibility and safe controls:
- Job view exposes engine lease info, final outputs, cached artifacts, chunk summary, and recent logs.
- “Finalization Health” widget surfaces failed chunks, missing outputs, finalization backlog, and stuck leases.
- Ops actions requeue failed/stuck chunks and allow manual finalization with audit logs.
- Filament resources cover Engine Servers and Verification Job Chunks for day-to-day ops.

## Work Distribution / Queue Strategy (Phase 8A)
“Workers pull work by calling claim-next; Laravel remains the source of truth and atomically leases chunks to workers. We may introduce a broker queue later, but the worker contract remains unchanged.”

## Phase 8A — Worker Pull (Mock Verification)
Workers poll `claim-next`, download inputs via signed URLs, generate mock outputs, upload via signed PUT URLs, and call `complete`/`fail`. No SMTP logic is executed in Laravel or in this phase.

## Phase 8B — Worker Pull (Connectivity Verification)
Workers perform **DNS/MX + SMTP connectivity** checks only (EHLO + QUIT), with strict throttling and timeouts. No RCPT probing is performed. This is the **SG2 portion of Standard** and is currently implemented. Classification uses reason codes:
- valid: `smtp_connect_ok`
- invalid: `syntax`, `mx_missing`, `smtp_unavailable`
- risky: `dns_timeout`, `dns_servfail`, `smtp_connect_timeout`, `smtp_timeout`, `smtp_tempfail`

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
