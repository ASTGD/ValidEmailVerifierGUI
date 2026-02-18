#!/usr/bin/env bash
set -euo pipefail

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to run ngrok in the background"
  exit 1
fi

screen -dmS dev-ngrok ngrok http 8082

sleep 1
if command -v curl >/dev/null 2>&1 && command -v python3 >/dev/null 2>&1; then
  curl -s http://127.0.0.1:4040/api/tunnels | python3 - <<'PY'
import json, sys
try:
    data = json.load(sys.stdin)
    tunnels = data.get("tunnels", [])
    if tunnels:
        print(tunnels[0].get("public_url", ""))
except Exception:
    pass
PY
fi
