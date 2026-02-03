# Deep Verification Engine Plan
**Laravel Control Plane (0-2) + Deep Verification Workers (SMTP) (Stage 3)**  
**Workers today:** Go  
**Workers later:** Easily swappable to **.NET Worker Service** or **Node.js** by re-implementing the same Engine Contract  
**Storage now:** **AWS S3** (required for remote workers)

This document is a living PM+Engineering plan that Codex and developers can follow step-by-step.

---

## 1) Why This Approch

This is the recommended architecture for this project because it balances:
- **Fast delivery** (Laravel is already built and strong at orchestration/admin/UI)
- **Operational safety** (Laravel remains the source of truth)
- **Scale readiness** (deep verification runs outside the web app)
- **Worker replaceability** (Go -> .NET -> Node.js later without rewriting Laravel)

**Option A definition:**
- **Laravel handles stages 0-2**: ingestion, parsing, normalization, dedupe, cache lookup, chunking, orchestration, admin control, final merge.
- **Workers handle stage 3**: SMTP / deep verification only (DNS+SMTP checks, rate limits, retries, classification).

---

## 2) Core Principles (Non-Negotiable)
### 2.1 Control plane vs Data plane
- **Laravel = control plane** and single source of truth.
- **Workers = data plane**; stateless executors that process chunks and report back.

### 2.2 Worker swappability (Go now, .NET/Node later)
To keep workers replaceable:
- Workers **must not read or write MySQL directly**.
- Workers **must not rely on Laravel internal queue payload formats**.
- Workers integrate via a stable **Engine Contract** (HTTP API + S3 objects).
- All worker I/O uses **S3 pre-signed URLs** (preferred) or disk/key references.
- Worker identity is controlled via **Sanctum token** (verifier-service).

This means any future worker stack only needs to:
1) consume a `chunk_id`
2) call the Engine Contract endpoints
3) read/write chunk files via S3 signed URLs
4) submit results back to Laravel

### 2.3 Storage is always disk/key (never absolute paths)
- Laravel stores only `disk` + `key`.
- Never store absolute filesystem paths or public URLs.
- Customer downloads are always through Laravel routes/controllers (authorization + streaming/signed URL).

---

## 3) Verification Stages (Pipeline)
### Stage 0 - Normalize + dedupe (Laravel background processing)
- Stream parse the uploaded file
- Extract candidates
- Normalize (trim + lowercase)
- Deduplicate within the job (avoid verifying same email twice)
- Count totals

### Stage 1 - Low-cost checks (Laravel optional)
- Syntax validation
- Domain validity / DNS checks
- MX lookup
These can reduce SMTP workload, but are optional depending on your business rules.

### Stage 2 - Cache lookup (Laravel)
- Batch query AWS NoSQL store (e.g., DynamoDB)
- Keys should be hashed normalized emails (privacy + speed)
- If cache hit is "fresh enough", classify without SMTP.
- Remaining "unknown" emails go to workers.

### Stage 3 - Deep verification (Workers)
- DNS + SMTP handshake/probing
- Rate limiting per domain & per server/IP
- Timeout handling, retries, greylisting behavior
- Final classification: `valid`, `invalid`, `risky` (and optionally "unknown" if you want)

### Final Output Schema + Deliverability Score
Laravel finalization emits customer-facing CSVs with:
```
email,status,sub_status,score,reason
```

Score policy:
- Deterministic 0–100 confidence score based on status + reason.
- Catch-all results are capped and governed by admin policy:
  - `risky_only` (default) always stays risky.
  - `promote_if_score_gte` can promote to valid if score meets threshold.
- Recent SG4 outcome cache hits adjust the score (boost for valid, reduce for invalid).

---

## 4) Storage Strategy (AWS S3 Now)
### 4.1 Decision: Use AWS S3 immediately
Remote workers require shared storage. Therefore all verification artifacts must be stored in **S3** from day 1.

Store in S3:
- Original uploads (job input)
- Chunk input files
- Chunk output files (valid/invalid/risky per chunk)
- Final merged outputs (valid/invalid/risky per job)
- Optional: JSON report, summary CSV, logs export

Laravel persists only:
- `disk` (e.g. `s3`)
- `key` (e.g. `uploads/{user_id}/{job_id}/input.csv`)

### 4.2 Worker file access: Pre-signed URLs (preferred)
Workers should not need long-lived AWS credentials when possible.
Preferred approach:
- Laravel generates **pre-signed GET** for chunk input
- Laravel generates **pre-signed PUT** for chunk outputs
- Worker uploads via signed URLs and reports only keys + counts in `/complete`.

---

## 5) Data Model (Laravel Control Plane)

### 5.1 verification_jobs
Represents one uploaded list verification job (owned by a user/customer).

Minimum fields:
- `id` (uuid)
- `user_id`
- `status`: `pending|processing|completed|failed`
- `input_disk`, `input_key`
- `output_disk`
- `valid_key`, `invalid_key`, `risky_key` (final merged outputs)
- `total_emails`
- `valid_count`, `invalid_count`, `risky_count`
- `started_at`, `finished_at`
- `error_message` (nullable)
- timestamps

### 5.2 verification_job_chunks
Represents one work unit that workers process.

Minimum fields:
- `id` (uuid)
- `job_id` (fk)
- `chunk_no` (int)
- `status`: `pending|processing|completed|failed`
- `attempts` (int default 0)
- `assigned_server_id` (nullable)
- `assigned_worker_id` (nullable string)
- `lease_expires_at` (nullable)  <- crash recovery & duplicate protection
- `input_disk`, `input_key`
- `output_disk` (nullable)
- `valid_key`, `invalid_key`, `risky_key` (nullable)
- `email_count` (nullable)
- `valid_count`, `invalid_count`, `risky_count` (nullable)
- timestamps

Indexes/constraints:
- Unique index: `(job_id, chunk_no)`
- Index: `(status)`
- Index: `(job_id, status)`

### 5.3 verification_servers
Represents the fleet of verification servers (50+).

Minimum fields:
- `id`
- `name`
- `region/provider` (nullable)
- `is_enabled` (bool)
- `max_concurrent_chunks` (int)
- `last_heartbeat_at` (nullable)
- `health_status` (string or computed)
- timestamps

Optional fields:
- notes, tags, IP pool metadata, avg latency, failure rate, capacity weights

### 5.4 verification_workers (optional but recommended)
Represents worker processes on servers (useful for debugging).

Fields:
- `worker_id` (string)
- `server_id`
- `version` (nullable)
- `last_seen_at`
- `current_chunk_id` (nullable)

### 5.5 Logs / Events
You already have job logs in the repo. Extend to support:
- chunk-level logs (chunk_id)
- server/worker events (server_id, worker_id in context)

---

## 6) Engine Contract (Stable API)
Workers must interact with Laravel only via API endpoints.
Auth:
- `auth:sanctum`
- verifier-service token + middleware (e.g., EnsureVerifierService)

### 6.1 Claim/lease chunk (prevents double-processing)
**POST** `/api/engine/chunks/{chunk}/claim`

Purpose:
- Atomically set `pending -> processing`
- Assign server/worker identity
- Set lease expiry (e.g., now + 10 minutes)
- Return 409 if already claimed/completed/failed

Request example:
```json
{
  "server_id": 12,
  "worker_id": "srv-12:worker-3"
}
```

### 6.2 Fetch chunk details
GET /api/engine/chunks/{chunk}
Returns:
job_id, attempt count

storage disk+key references

policy hints (timeouts, max concurrency, etc.) from config/DB (not hardcoded)


### 6.3 Signed URL: download chunk input
GET /api/engine/chunks/{chunk}/input-url
Returns:
```
{
  "disk": "s3",
  "key": "chunks/{job_id}/{chunk_no}/input.txt",
  "url": "https://signed-s3-url",
  "expires_in": 300
}
```

### 6.4 Signed URLs: upload chunk outputs
POST /api/engine/chunks/{chunk}/output-urls
Returns signed PUT URLs + final keys:
```
{
  "disk": "s3",
  "targets": {
    "valid":   { "key": "chunks/.../valid.csv",   "url": "https://signed-put" },
    "invalid": { "key": "chunks/.../invalid.csv", "url": "https://signed-put" },
    "risky":   { "key": "chunks/.../risky.csv",   "url": "https://signed-put" }
  },
  "expires_in": 300
}
```

### 6.5 Heartbeat (server/worker health)
POST /api/engine/heartbeat
Records:
last_heartbeat_at

worker last_seen_at

inflight counts / throughput metrics (minimal, optional)

### 6.6 Log event (structured)
POST /api/engine/chunks/{chunk}/log
Payload:
```
{
  "level": "info",
  "event": "smtp_batch_started",
  "message": "Processing gmail.com",
  "context": { "domain": "gmail.com", "batch": 3 }
}
```

### 6.7 Complete chunk (idempotent)
POST /api/engine/chunks/{chunk}/complete
Payload:
```
{
  "output": {
    "disk": "s3",
    "valid_key": "chunks/.../valid.csv",
    "invalid_key": "chunks/.../invalid.csv",
    "risky_key": "chunks/.../risky.csv"
  },
  "counts": { "email_count": 5000, "valid": 3200, "invalid": 1400, "risky": 400 }
}
```

Rules:
If chunk already completed, respond success (no-op).

Clear lease and mark completed.

### 6.8 Fail chunk (retry policy)
POST /api/engine/chunks/{chunk}/fail
Payload:
```
{
  "error_message": "timeout contacting mx1.example.com",
  "retryable": true
}
```

Laravel behavior:
increment attempts

if attempts < max_attempts: requeue chunk as pending

else: mark failed

---

## 7) Work Distribution (Queue Strategy)
V1 (fastest to ship)
Workers claim pending chunks via API (pull model).

Laravel provides "claim next pending chunk" endpoint OR workers query pending list then claim.

V2 (scale)
Introduce Redis (or other broker) for chunk IDs.

Queue payload contains only:
- chunk_id
- attempt (optional)

Worker still must claim chunk via API for lease safety.

Important: queue messages remain language-agnostic so Go/.NET/Node can consume them easily.

---

## 8) End-to-End Workflow (Option A)
Step 1 - Upload (Portal)
Store original input file to S3

Create VerificationJob (status = pending)

Dispatch background pipeline job: "Prepare Job"

Step 2 - Prepare Job (Laravel Stage 0-2 pipeline)
Stream parse file -> normalize + dedupe

Batch query AWS NoSQL cache for known results (hash keys)

Save known results counts (and optionally cached outputs)

Create chunk rows for remaining emails

Write chunk input files to S3

Mark job status = processing

Step 3 - Workers Process Chunks (Stage 3)
For each chunk:
claim (lease)

get input signed URL

perform SMTP deep verification

request output signed URLs

upload chunk outputs

complete with stats OR fail (retry)

Step 4 - Finalize Job (Laravel)
When all chunks completed:
merge chunk outputs + cached results into final files (valid/invalid/risky) on S3

update job stats

set job status completed

customer downloads via portal routes (authorization enforced)

If any chunk permanently fails:
job fails or enters "partial complete" mode (future decision)

---

## 9) Admin Portal (Filament) Requirements (CEO visibility)
Minimum screens
Servers
- list servers, enable/disable, max concurrency
- show last heartbeat, health status, inflight counts (if reported)

Chunks
- filters: status, server, job
- show attempts, lease expiry, assigned worker
- actions: requeue, mark failed, view logs

Jobs
- job detail: chunk progress (completed/total), last logs
- quick actions: re-run finalize (if safe), requeue failed chunks

Dashboard widgets (nice-to-have)
- Pending chunks count
- Processing chunks count
- Failures last hour
- Offline servers count

---

## 10) Non-Functional Requirements
Reliability
- Lease-based claiming prevents duplicates
- Idempotent completion endpoints
- Safe retry policy with backoff
- Circuit breaker: auto-disable servers on repeated failures

Performance
- Streaming parsing for large files
- Batch NoSQL lookups (never per-email requests)
- Chunk size configurable (recommend 5k-10k starting point)

Security
- Engine endpoints require Sanctum token + middleware
- Optional IP allowlist for engine calls
- Never expose public S3 URLs to customers
- Use signed URLs for worker I/O (preferred)

---

## 11) Phased Execution Plan (Step-by-Step)
Phase 1 - Control Plane Foundations (Laravel)
Outcome: Laravel tracks servers + chunks; engine API exists; admin can see/control.
Add migrations/models:
- verification_job_chunks
- verification_servers
- optional verification_workers

Add Engine Contract endpoints:
- claim, heartbeat, log, complete, fail
- input-url, output-urls (S3 presigned)

Add Filament resources/pages:
- Servers
- Chunks

Add ENGINE_CONTRACT.md (API contract reference) and keep it stable.

Acceptance criteria
- Admin can manage servers (enable/disable)
- You can create a chunk and process it using curl + S3 URLs
- Chunks show correct statuses and logs in Filament

Phase 2 - Stage 0-2 Pipeline (Laravel)
Outcome: Upload automatically creates chunks on S3 and marks job processing.
Background job: parse/extract/normalize/dedupe (streaming)

Batch cache lookup (AWS NoSQL) (stub in dev; real in staging/prod)

Create chunk rows + upload chunk inputs to S3

Record totals and mark job processing

Acceptance criteria
12k list -> 3 chunks (e.g., 5k/5k/2k)

18k list -> 4 chunks

Chunk rows linked to correct job/user, input keys exist in S3

Phase 3 - Finalization & Customer Outputs (Laravel)
Outcome: Completed chunks produce final downloadable result files on S3.
Finalizer job watches for all chunks completed

Merge chunk outputs (+ cached results) into final:
- valid.csv
- invalid.csv
- risky.csv

Update job stats and mark completed

Customer downloads via portal routes (stream or signed URL from Laravel)

Acceptance criteria
Customer downloads final valid/invalid/risky files

Stats match sum of chunk outputs (+ cached results)

Phase 4 - Deep Verification Workers v1 (Go)
Outcome: Real SMTP verification integrated end-to-end.
Worker loop:
- claim -> input-url -> verify -> output-urls -> upload -> complete/fail

Heartbeat + structured logs

Per-domain throttles + timeouts + retry policy

Acceptance criteria
Worker processes chunks reliably

Failures retry correctly

Admin sees heartbeat/logs/chunk assignments

Phase 5 - Scale (Optional)
Outcome: High volume throughput with brokered queue distribution.
Add Redis queue for chunk IDs (optional)

Add server selection strategy (least-used + health)

Drain/pause + auto-disable unhealthy servers

Alerts (offline servers, high failure rates)

---

## 12) What to Build Next (Recommended Priority)
Phase 1 (chunks + servers + engine API + Filament + S3 signed URLs)

Phase 2 (parse/dedupe/cache/chunk creation pipeline to S3)

Phase 3 (final merge outputs on S3)

Phase 4 (Go SMTP worker repo/service)

---

## 13) Decisions to Fill In (Project Settings)
- Queue distribution mode (V1): ______ (API pull/claim vs Redis queue)
- Redis adoption (V2): ______ (Yes/No, when)
- S3 presigned URL expiry: ______ seconds (recommend 300-900)
- Results retention policy: ______ days (e.g., 30/60/90)
- Cache store type: ______ (DynamoDB/other) and cache TTL/freshness window: ______ days
- Cache key strategy: ______ (normalize + SHA-256 hash, optional pepper stored in env/secret manager)
- SMTP timeout: ______ ms (e.g., 5000-10000)
- Max retries per chunk: ______ (e.g., 3-5)
- Per-domain concurrency: ______ (e.g., 2-5)
- Global worker concurrency per server: ______ (e.g., 100-500)
- Chunk size: ______ (recommend 5,000-10,000)
- File formats supported: ______ (csv/txt now; xlsx policy: convert to csv or enforce row limit)
- Maximum upload size: ______ MB
