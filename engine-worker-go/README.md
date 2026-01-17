# Engine Worker (Go) — Phase 8A

This mock worker pulls chunks from the Laravel API, downloads inputs via signed URLs, generates mock outputs, uploads them, and completes chunks. It does **not** perform SMTP/DNS/MX verification.

## Requirements
- Go 1.22+
- Laravel API running with verifier endpoints enabled
- A Sanctum token for a `verifier-service` user

## Configuration (env)
- `ENGINE_API_BASE_URL` (required) — e.g. `http://localhost:8082`
- `ENGINE_API_TOKEN` (required) — Sanctum token for verifier-service
- `WORKER_ID` (optional) — defaults to hostname
- `ENGINE_SERVER_NAME` (optional) — defaults to worker id
- `ENGINE_SERVER_IP` (required) — IP address to register/heartbeat
- `ENGINE_SERVER_ENV` (optional) — e.g. `local`
- `ENGINE_SERVER_REGION` (optional) — e.g. `local`
- `POLL_INTERVAL_SECONDS` (default 5)
- `HEARTBEAT_INTERVAL_SECONDS` (default 30)
- `LEASE_SECONDS` (optional)
- `MAX_CONCURRENCY` (default 1)

## Run
```bash
cd engine-worker-go
ENGINE_API_BASE_URL=http://localhost:8082 \
ENGINE_API_TOKEN=... \
ENGINE_SERVER_IP=127.0.0.1 \
go run ./cmd/worker
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
- Outputs are mock-classified:
  - missing `@` → invalid (`syntax`)
  - domain contains `example` → risky (`mock_risky_domain`)
  - otherwise → valid (`mock_valid`)
