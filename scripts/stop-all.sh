#!/usr/bin/env bash
set -euo pipefail

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to stop background sessions"
  exit 1
fi

for name in dev-vite dev-queue dev-go-dashboard dev-go-worker dev-ngrok; do
  if screen -ls | grep -q "\.${name}"; then
    screen -S "$name" -X quit
  fi
done

echo "Stopped dev screen sessions."
