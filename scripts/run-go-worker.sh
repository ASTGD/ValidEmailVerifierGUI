#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir/engine-worker-go"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

missing_vars=()
for var in ENGINE_API_BASE_URL ENGINE_API_TOKEN ENGINE_SERVER_IP; do
  if [[ -z "${!var-}" ]]; then
    missing_vars+=("$var")
  fi
done

if [[ ${#missing_vars[@]} -gt 0 ]]; then
  echo "Missing required env vars: ${missing_vars[*]}"
  echo "Set them in your shell or in engine-worker-go/.env"
  echo "Example:"
  echo "ENGINE_API_BASE_URL=http://localhost:8082"
  echo "ENGINE_API_TOKEN=..."
  echo "ENGINE_SERVER_IP=127.0.0.1"
  exit 1
fi

go run ./cmd/worker
