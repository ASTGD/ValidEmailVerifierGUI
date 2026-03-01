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
screen -dmS dev-scheduler bash -lc "cd '$root_dir' && ./vendor/bin/sail artisan schedule:work"

if [[ -f "$root_dir/services/go-control-plane/.env" ]]; then
  screen -dmS dev-go-dashboard bash -lc "'$root_dir/scripts/run-go-dashboard.sh'"
else
  echo "Go dashboard skipped (missing services/go-control-plane/.env)"
fi

echo "Local Go worker start is disabled in run-all.sh (using real VPS workers)."

"$root_dir/scripts/run-cloudflare-tunnels.sh"

echo "Started screen sessions:"
screen -ls | grep -E "dev-(vite|queue|scheduler|go-dashboard|tunnel-app|tunnel-go|ngrok)" || true
