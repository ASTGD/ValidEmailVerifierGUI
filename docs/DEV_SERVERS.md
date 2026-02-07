# Dev Servers Runbook

Use this runbook to start and stop all local dev services in this repo.

**Core services**
1. `./vendor/bin/sail up -d`

**Frontend (Vite)**
1. `./vendor/bin/sail npm run dev`

**Queue worker**
1. `./vendor/bin/sail artisan queue:work --timeout=1800`

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

## Stop background sessions

- `./scripts/stop-all.sh`

## Optional stop

- `./vendor/bin/sail stop`
