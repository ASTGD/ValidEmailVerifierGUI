#!/usr/bin/env bash
set -euo pipefail

if ! command -v screen >/dev/null 2>&1; then
  echo "screen is required to run Cloudflare tunnels in the background"
  exit 1
fi

if ! command -v cloudflared >/dev/null 2>&1; then
  echo "cloudflared is required but not installed"
  exit 1
fi

app_tunnel_name="${APP_TUNNEL_NAME:-app-dev}"
go_tunnel_name="${GO_TUNNEL_NAME:-go-dev}"
app_origin_url="${APP_ORIGIN_URL:-http://localhost:8082}"
go_origin_url="${GO_ORIGIN_URL:-http://localhost:9091}"

screen_sessions="$(screen -ls 2>/dev/null | tr -d '\r' || true)"

for name in dev-tunnel-app dev-tunnel-go; do
  if printf '%s\n' "$screen_sessions" | grep -Fq ".${name}"; then
    screen -S "$name" -X quit || true
  fi
done

screen -dmS dev-tunnel-app bash -lc "cloudflared tunnel run --url '$app_origin_url' '$app_tunnel_name'"
screen -dmS dev-tunnel-go bash -lc "cloudflared tunnel run --url '$go_origin_url' '$go_tunnel_name'"

echo "Cloudflare tunnels started:"
echo "  app tunnel: $app_tunnel_name -> $app_origin_url"
echo "  go tunnel:  $go_tunnel_name -> $go_origin_url"
