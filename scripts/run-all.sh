#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"

"$root_dir/scripts/run-sail-up.sh"

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to run all services in the background"
  exit 1
fi

screen -dmS dev-vite bash -lc "cd '$root_dir' && ./vendor/bin/sail npm run dev"
screen -dmS dev-queue bash -lc "cd '$root_dir' && ./vendor/bin/sail artisan horizon"

if [[ -f "$root_dir/services/go-control-plane/.env" ]]; then
  screen -dmS dev-go-dashboard bash -lc "'$root_dir/scripts/run-go-dashboard.sh'"
else
  echo "Go dashboard skipped (missing services/go-control-plane/.env)"
fi

worker_env_file="$root_dir/engine-worker-go/.env"
if [[ -f "$worker_env_file" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$worker_env_file"
  set +a
fi

missing_vars=()
for var in ENGINE_API_BASE_URL ENGINE_API_TOKEN ENGINE_SERVER_IP; do
  if [[ -z "${!var-}" ]]; then
    missing_vars+=("$var")
  fi
done

if [[ ${#missing_vars[@]} -gt 0 ]]; then
  echo "Go worker skipped (missing env vars: ${missing_vars[*]})"
else
  screen -dmS dev-go-worker bash -lc "'$root_dir/scripts/run-go-worker.sh'"
fi

screen -dmS dev-ngrok bash -lc "ngrok http 8082"

echo "Started screen sessions:"
screen -ls | grep -E "dev-(vite|queue|go-dashboard|go-worker|ngrok)" || true
