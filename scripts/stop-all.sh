#!/usr/bin/env bash
set -euo pipefail

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to stop background sessions"
  exit 1
fi

screen_sessions="$(screen -ls 2>/dev/null | tr -d '\r' || true)"

for name in dev-vite dev-queue dev-scheduler dev-go-dashboard dev-go-worker dev-tunnel-app dev-tunnel-go dev-ngrok; do
  if printf '%s\n' "$screen_sessions" | grep -Fq ".${name}"; then
    screen -S "$name" -X quit || true
  fi
done

echo "Stopped dev screen sessions."
