# Dev Servers Runbook

Use this runbook to start and stop all local dev services in this repo.

**Core services**
1. `./vendor/bin/sail up -d`

**Frontend (Vite)**
1. `./vendor/bin/sail npm run dev`

**Queue workers (Horizon)**
1. `./vendor/bin/sail artisan horizon`

**Scheduler (required for snapshots + health checks)**
1. `./vendor/bin/sail artisan schedule:work`

**Go dashboard (control plane)**
1. `cd services/go-control-plane`
2. `cp .env.example .env`
3. Edit `.env` (token, Redis, optional MySQL settings)
4. `go run .`
5. Open `http://localhost:9091/verifier-engine-room/overview`

**Go worker**
1. `cd engine-worker-go`
2. Set required env vars in your shell or create `engine-worker-go/.env`
3. `go run ./cmd/worker`

Required env vars for the Go worker:
- `ENGINE_API_BASE_URL`
- `ENGINE_API_TOKEN`
- `ENGINE_SERVER_IP`

**ngrok (public tunnel)**
1. `ngrok http 8082`

## Helper scripts (separate commands)

- `./scripts/run-sail-up.sh`
- `./scripts/run-vite.sh`
- `./scripts/run-queue.sh`
- `./scripts/run-go-dashboard.sh`
- `./scripts/run-go-worker.sh`
- `./scripts/run-ngrok.sh`

## Run everything at once

- `./scripts/run-all.sh`

Notes:
- Uses `screen` to keep processes running in the background.
- Skips the Go dashboard if `services/go-control-plane/.env` is missing.
- Skips the Go worker if required env vars are missing.
- `run-queue.sh` starts Horizon (segmented queue workers).

## Queue reliability checks

- `./vendor/bin/sail artisan ops:queue-health`
- `./vendor/bin/sail artisan ops:queue-health --json`
- `./vendor/bin/sail artisan horizon:status`
- `./vendor/bin/sail artisan horizon:supervisors`

## Queue recovery sequence

1. `./vendor/bin/sail artisan horizon:terminate`
2. Restart Horizon process manager target (or re-run `./vendor/bin/sail artisan horizon`)
3. `./vendor/bin/sail artisan horizon:supervisors`
4. `./vendor/bin/sail artisan ops:queue-health --json`

## Stop background sessions

- `./scripts/stop-all.sh`

## Optional stop

- `./vendor/bin/sail stop`
