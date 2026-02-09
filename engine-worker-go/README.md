# Engine Worker (Go)

This worker pulls chunks from the Laravel API, downloads inputs via signed URLs, runs stage-specific verification, uploads outputs, and completes chunks.

- `screening` stage uses fast DNS/MX + SMTP connectivity checks.
- `smtp_probe` stage uses mailbox-level SMTP probing (`MAIL FROM` + `RCPT TO`) when policy and identity prerequisites are available.

## Requirements
- Go 1.22+
- Laravel API running with verifier endpoints enabled
- A Sanctum token for a `verifier-service` user

## Token safety
Never paste tokens into chat or commit them to git. Use environment variables or a secrets manager when running the worker.

## Configuration (env)
- `ENGINE_API_BASE_URL` (required) — e.g. `http://localhost:8082`
- `ENGINE_API_TOKEN` (required) — Sanctum token for verifier-service
- `WORKER_ID` (optional) — defaults to hostname
- `WORKER_CAPABILITY` (optional) — `screening`, `smtp_probe`, or `all` (default `all`)
- `ENGINE_SERVER_NAME` (optional) — defaults to worker id
- `ENGINE_SERVER_IP` (required) — IP address to register/heartbeat
- `ENGINE_SERVER_ENV` (optional) — e.g. `local`
- `ENGINE_SERVER_REGION` (optional) — e.g. `local`
- `POLL_INTERVAL_SECONDS` (default 5)
- `HEARTBEAT_INTERVAL_SECONDS` (default 30)
- `LEASE_SECONDS` (optional)
- `MAX_CONCURRENCY` (default 1)
- `DNS_TIMEOUT_MS` (default 2000)
- `SMTP_CONNECT_TIMEOUT_MS` (default 2000)
- `SMTP_READ_TIMEOUT_MS` (default 2000)
- `SMTP_EHLO_TIMEOUT_MS` (default 2000)
- `MAX_MX_ATTEMPTS` (default 2)
- `RETRYABLE_NETWORK_RETRIES` (default 1)
- `BACKOFF_MS_BASE` (default 200)
- `PER_DOMAIN_CONCURRENCY` (default 2)
- `SMTP_RATE_LIMIT_PER_MINUTE` (default 0, disabled)
- `HELO_NAME` (optional; defaults to hostname)

## Run
```bash
cd engine-worker-go
ENGINE_API_BASE_URL=http://localhost:8082 \
ENGINE_API_TOKEN=... \
ENGINE_SERVER_IP=127.0.0.1 \
go run ./cmd/worker
```

## Docker build + run
Build:
```bash
docker build -t engine-worker-go ./engine-worker-go
```

Run (env vars required):
```bash
docker run --rm \
  -e ENGINE_API_BASE_URL=http://localhost:8082 \
  -e ENGINE_API_TOKEN=... \
  -e ENGINE_SERVER_IP=127.0.0.1 \
  -e ENGINE_SERVER_NAME=worker-local \
  -e ENGINE_SERVER_ENV=local \
  -e ENGINE_SERVER_REGION=local \
  engine-worker-go
```

## Getting a verifier-service token
Example (local dev):
```bash
./vendor/bin/sail artisan tinker
```
```php
$user = \App\Models\User::role(\App\Support\Roles::VERIFIER_SERVICE)->first();
$token = $user->createToken('engine-worker')->plainTextToken;
```

## End-to-end test
1) Upload a list in the portal (creates chunks).
2) Run the worker.
3) Watch chunks complete in `/admin/verification-job-chunks`.
4) Job should finalize and downloads appear in the portal.

## Notes
- Outputs use schema `email,reason`.
- Screening lane classifications are connectivity-oriented:
  - invalid: `syntax`, `mx_missing`, `smtp_unavailable`
  - risky: `dns_timeout`, `dns_servfail`, `smtp_connect_timeout`, `smtp_timeout`, `smtp_tempfail`
  - valid: `smtp_connect_ok`
- SMTP probe lane adds mailbox-level reasons:
  - valid: `rcpt_ok`
  - invalid: `rcpt_rejected`
  - risky: `catch_all`, `smtp_tempfail`, `smtp_probe_disabled`, `smtp_probe_identity_missing`
- SMTP reply intelligence is provider-aware and conservative:
  - parses multiline SMTP replies and enhanced status codes (`X.Y.Z`)
  - applies deterministic decision classes internally (`deliverable`, `undeliverable`, `retryable`, `policy_blocked`, `unknown`)
  - keeps uncertain evidence in risky paths (never silently promotes unknown signals to valid)
