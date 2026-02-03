# Engine Monitor (RBL checks)

This service polls the Laravel API for active engine servers and the configured RBL list, performs DNS checks, and posts results back for admin visibility.

## Environment

- `MONITOR_API_BASE_URL` (required): Base URL of the Laravel app.
- `MONITOR_API_TOKEN` (required): Sanctum token for the verifier-service identity.
- `MONITOR_TIMEOUT_SECONDS` (optional, default 8): DNS lookup timeout per RBL.
- `MONITOR_INTERVAL_SECONDS` (optional): Override the interval instead of API config.

Resolver settings are provided by the Laravel admin settings:
- **System DNS (host)** uses the server's configured resolver.
- **Custom DNS server** uses the configured IP/port from the Settings page.

## Build

```bash
go build -o engine-monitor ./cmd/monitor
```

## Run (example)

```bash
MONITOR_API_BASE_URL="https://your-app.example" \
MONITOR_API_TOKEN="token" \
./engine-monitor
```

## systemd (example)

Create an environment file with the required variables, then reference it from a service unit:

```ini
[Unit]
Description=Engine blacklist monitor
After=network-online.target

[Service]
Type=simple
User=root
EnvironmentFile=/etc/engine-monitor/monitor.env
ExecStart=/opt/engine-monitor/engine-monitor
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```
