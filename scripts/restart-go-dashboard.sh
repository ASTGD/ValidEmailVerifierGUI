#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to restart go dashboard"
  exit 1
fi

screen_sessions="$(screen -ls 2>/dev/null | tr -d '\r' || true)"
if printf '%s\n' "$screen_sessions" | grep -Fq ".dev-go-dashboard"; then
  screen -S dev-go-dashboard -X quit || true
fi

if command -v lsof >/dev/null 2>&1; then
  busy_pids="$(lsof -tiTCP:9091 -sTCP:LISTEN -n -P 2>/dev/null || true)"
  if [[ -n "$busy_pids" ]]; then
    echo "Port 9091 is busy. Stopping existing process(es): $busy_pids"
    kill $busy_pids >/dev/null 2>&1 || true
    sleep 1
  fi
fi

screen -dmS dev-go-dashboard bash -lc "cd '$root_dir' && ./scripts/run-go-dashboard.sh"

echo "Go dashboard restarted in screen session: dev-go-dashboard"
