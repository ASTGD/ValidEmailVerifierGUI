<?php

namespace App\Services;

use App\Models\EngineServer;
use App\Models\EngineServerProvisioningBundle;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Spatie\Permission\Models\Role;

class EngineWorkerProvisioningService
{
    public function createBundle(EngineServer $server, ?User $actor): EngineServerProvisioningBundle
    {
        $this->guardConfig($server);

        $server->loadMissing('verifierDomain');

        $ttlMinutes = (int) config('engine.worker_provisioning_ttl_minutes', 60);
        $expiresAt = now()->addMinutes(max(1, $ttlMinutes));

        $this->expirePreviousBundles($server);

        $token = $this->createVerifierToken($server);

        $bundleUuid = (string) Str::uuid();
        $prefix = trim((string) config('engine.worker_provisioning_prefix', 'provisioning/worker'), '/');
        $envKey = $prefix.'/'.$server->id.'/'.$bundleUuid.'/worker.env';
        $scriptKey = $prefix.'/'.$server->id.'/'.$bundleUuid.'/install.sh';

        $identityDomain = $server->verifierDomain?->domain ?? $server->identity_domain;
        $apiBaseUrl = trim((string) config('app.url'), '/');

        $workerEnv = $this->buildWorkerEnv($server, $apiBaseUrl, $token->plainTextToken, $identityDomain);
        $installScript = $this->buildInstallScript($server, $workerEnv);

        $disk = (string) config('engine.worker_provisioning_disk', 'local');
        Storage::disk($disk)->put($envKey, $workerEnv, ['visibility' => 'private']);
        Storage::disk($disk)->put($scriptKey, $installScript, ['visibility' => 'private']);

        return EngineServerProvisioningBundle::create([
            'engine_server_id' => $server->id,
            'bundle_uuid' => $bundleUuid,
            'env_key' => $envKey,
            'script_key' => $scriptKey,
            'token_id' => $token->accessToken->id,
            'created_by' => $actor?->id,
            'expires_at' => $expiresAt,
        ]);
    }

    private function guardConfig(EngineServer $server): void
    {
        $registry = (string) config('engine.worker_registry');
        $image = (string) config('engine.worker_image');
        $envPath = (string) config('engine.worker_env_path');
        $apiBaseUrl = trim((string) config('app.url'), '/');

        if ($registry === '' || $image === '' || $envPath === '' || $apiBaseUrl === '') {
            throw new RuntimeException('Provisioning config is incomplete.');
        }

        if ($server->supportsAgentProcessControl()) {
            $agentToken = trim((string) config('engine_servers.process_control.agent_token', ''));
            $agentSecret = trim((string) config('engine_servers.process_control.agent_hmac_secret', ''));
            if ($agentToken === '' || $agentSecret === '') {
                throw new RuntimeException('Agent process control credentials are missing.');
            }
        }
    }

    private function expirePreviousBundles(EngineServer $server): void
    {
        $bundleIds = EngineServerProvisioningBundle::query()
            ->where('engine_server_id', $server->id)
            ->pluck('token_id')
            ->filter();

        if ($bundleIds->isNotEmpty()) {
            PersonalAccessToken::query()->whereIn('id', $bundleIds)->delete();
        }

        EngineServerProvisioningBundle::query()
            ->where('engine_server_id', $server->id)
            ->update([
                'expires_at' => now(),
                'token_id' => null,
            ]);
    }

    private function createVerifierToken(EngineServer $server): NewAccessToken
    {
        $role = Role::findOrCreate(Roles::VERIFIER_SERVICE);

        $user = User::firstOrCreate(
            ['email' => 'verifier-service@local.test'],
            ['name' => 'Verifier Service', 'password' => bcrypt(Str::random(32))]
        );

        $user->syncRoles([$role]);

        return $user->createToken('engine-server:'.$server->id);
    }

    private function buildWorkerEnv(
        EngineServer $server,
        string $apiBaseUrl,
        string $plainTextToken,
        ?string $identityDomain
    ): string {
        $controlPlaneBaseUrl = trim((string) config('services.go_control_plane.base_url', ''));
        $controlPlaneToken = trim((string) config('services.go_control_plane.token', ''));

        $lines = [
            $this->envLine('ENGINE_API_BASE_URL', $apiBaseUrl),
            $this->envLine('ENGINE_API_TOKEN', $plainTextToken),
            $this->envLine('ENGINE_SERVER_IP', $server->ip_address),
            $this->envLine('ENGINE_SERVER_NAME', $server->name),
            $this->envLine('WORKER_ID', (string) $server->id),
            $this->envLine('LARAVEL_HEARTBEAT_ENABLED', 'true'),
            $this->envLine('LARAVEL_HEARTBEAT_EVERY_N', '10'),
        ];

        if ($server->environment) {
            $lines[] = $this->envLine('ENGINE_SERVER_ENV', $server->environment);
        }

        if ($server->region) {
            $lines[] = $this->envLine('ENGINE_SERVER_REGION', $server->region);
        }

        if ($server->helo_name) {
            $lines[] = $this->envLine('HELO_NAME', $server->helo_name);
        }

        if ($server->mail_from_address) {
            $lines[] = $this->envLine('MAIL_FROM_ADDRESS', $server->mail_from_address);
        }

        if ($identityDomain) {
            $lines[] = $this->envLine('IDENTITY_DOMAIN', $identityDomain);
        }

        if ($controlPlaneBaseUrl !== '' && $controlPlaneToken !== '') {
            $lines[] = $this->envLine('CONTROL_PLANE_BASE_URL', $controlPlaneBaseUrl);
            $lines[] = $this->envLine('CONTROL_PLANE_TOKEN', $controlPlaneToken);
            $lines[] = $this->envLine('CONTROL_PLANE_HEARTBEAT_ENABLED', 'true');
            $lines[] = $this->envLine('CONTROL_PLANE_POLICY_SYNC_ENABLED', 'true');
        }

        return implode("\n", $lines)."\n";
    }

    private function buildInstallScript(EngineServer $server, string $workerEnv): string
    {
        $registry = (string) config('engine.worker_registry');
        $image = $this->resolveWorkerImageReference();
        $envPath = (string) config('engine.worker_env_path');
        $restartPolicy = trim((string) config('engine.worker_runtime_restart_policy', 'unless-stopped'));
        $hardeningEnabled = (bool) config('engine.worker_runtime_hardening_enabled', true);
        $readOnly = (bool) config('engine.worker_runtime_read_only', true);
        $noNewPrivileges = (bool) config('engine.worker_runtime_no_new_privileges', true);
        $capDropAll = (bool) config('engine.worker_runtime_cap_drop_all', true);
        $tmpfsSizeMb = max(16, (int) config('engine.worker_runtime_tmpfs_size_mb', 64));
        $pidsLimit = max(64, (int) config('engine.worker_runtime_pids_limit', 256));
        $memoryLimit = trim((string) config('engine.worker_runtime_memory_limit', ''));
        $cpuLimit = trim((string) config('engine.worker_runtime_cpu_limit', ''));
        $agentEnabled = $server->supportsAgentProcessControl();
        $agentToken = trim((string) config('engine_servers.process_control.agent_token', ''));
        $agentHmacSecret = trim((string) config('engine_servers.process_control.agent_hmac_secret', ''));
        $agentSignatureTTL = max(5, (int) config('engine_servers.process_control.signature_ttl_seconds', 60));
        $agentPort = max(1, min(65535, (int) config('engine.worker_agent_port', 9713)));
        $workerServiceName = trim((string) $server->agent_service_name);
        if ($workerServiceName === '') {
            $workerServiceName = 'vev-worker.service';
        }
        $agentServiceName = 'vev-worker-agent.service';

        $escapedEnv = rtrim($workerEnv, "\n");
        $registryArg = escapeshellarg($registry);
        $imageArg = escapeshellarg($image);
        $envPathArg = escapeshellarg($envPath);
        $restartPolicyArg = escapeshellarg($restartPolicy !== '' ? $restartPolicy : 'unless-stopped');
        $hardeningEnabledArg = $hardeningEnabled ? '1' : '0';
        $readOnlyArg = $readOnly ? '1' : '0';
        $noNewPrivilegesArg = $noNewPrivileges ? '1' : '0';
        $capDropAllArg = $capDropAll ? '1' : '0';
        $tmpfsSizeArg = (string) $tmpfsSizeMb;
        $pidsLimitArg = (string) $pidsLimit;
        $memoryLimitArg = escapeshellarg($memoryLimit);
        $cpuLimitArg = escapeshellarg($cpuLimit);
        $agentEnabledArg = $agentEnabled ? '1' : '0';
        $agentTokenArg = escapeshellarg($agentToken);
        $agentHmacSecretArg = escapeshellarg($agentHmacSecret);
        $agentSignatureTTLArg = (string) $agentSignatureTTL;
        $agentPortArg = (string) $agentPort;
        $workerServiceNameArg = escapeshellarg($workerServiceName);
        $agentServiceNameArg = escapeshellarg($agentServiceName);

        $template = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

REGISTRY={{REGISTRY}}
IMAGE={{IMAGE}}
ENV_PATH={{ENV_PATH}}
RESTART_POLICY={{RESTART_POLICY}}
HARDENING_ENABLED={{HARDENING_ENABLED}}
HARDENING_READ_ONLY={{HARDENING_READ_ONLY}}
HARDENING_NO_NEW_PRIVILEGES={{HARDENING_NO_NEW_PRIVILEGES}}
HARDENING_CAP_DROP_ALL={{HARDENING_CAP_DROP_ALL}}
HARDENING_TMPFS_SIZE_MB={{HARDENING_TMPFS_SIZE_MB}}
HARDENING_PIDS_LIMIT={{HARDENING_PIDS_LIMIT}}
MEMORY_LIMIT={{MEMORY_LIMIT}}
CPU_LIMIT={{CPU_LIMIT}}
AGENT_ENABLED={{AGENT_ENABLED}}
AGENT_TOKEN={{AGENT_TOKEN}}
AGENT_HMAC_SECRET={{AGENT_HMAC_SECRET}}
AGENT_SIGNATURE_TTL={{AGENT_SIGNATURE_TTL}}
AGENT_PORT={{AGENT_PORT}}
WORKER_SERVICE_NAME={{WORKER_SERVICE_NAME}}
AGENT_SERVICE_NAME={{AGENT_SERVICE_NAME}}
WORKER_CONTAINER_NAME="valid-email-worker"
WORKER_CONTROL_SCRIPT="/usr/local/bin/vev-worker-control"
WORKER_SERVICE_PATH="/etc/systemd/system/${WORKER_SERVICE_NAME}"
AGENT_SERVICE_PATH="/etc/systemd/system/${AGENT_SERVICE_NAME}"
AGENT_SCRIPT_PATH="/usr/local/bin/vev-worker-agent"
AGENT_ENV_PATH="/etc/vev/worker-agent.env"

GHCR_USER=""
GHCR_TOKEN=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ghcr-user)
      GHCR_USER="$2"
      shift 2
      ;;
    --ghcr-token)
      GHCR_TOKEN="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

if [[ -z "$GHCR_USER" || -z "$GHCR_TOKEN" ]]; then
  echo "GHCR credentials are required."
  exit 1
fi

if [[ $EUID -ne 0 ]]; then
  echo "Run this installer as root."
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  if ! command -v apt-get >/dev/null 2>&1; then
    echo "Unsupported OS. This installer requires apt-get (Ubuntu)."
    exit 1
  fi
  apt-get update -y
  apt-get install -y docker.io
  systemctl enable --now docker
fi

mkdir -p "$(dirname "$ENV_PATH")"
cat > "$ENV_PATH" <<'ENVFILE'
{{WORKER_ENV}}
ENVFILE

printf "%s\n" "$GHCR_TOKEN" | docker login "$REGISTRY" -u "$GHCR_USER" --password-stdin

docker pull "$IMAGE"

cat > "$WORKER_CONTROL_SCRIPT" <<'WORKERCTL'
#!/usr/bin/env bash
set -euo pipefail

IMAGE={{IMAGE}}
ENV_PATH={{ENV_PATH}}
RESTART_POLICY={{RESTART_POLICY}}
HARDENING_ENABLED={{HARDENING_ENABLED}}
HARDENING_READ_ONLY={{HARDENING_READ_ONLY}}
HARDENING_NO_NEW_PRIVILEGES={{HARDENING_NO_NEW_PRIVILEGES}}
HARDENING_CAP_DROP_ALL={{HARDENING_CAP_DROP_ALL}}
HARDENING_TMPFS_SIZE_MB={{HARDENING_TMPFS_SIZE_MB}}
HARDENING_PIDS_LIMIT={{HARDENING_PIDS_LIMIT}}
MEMORY_LIMIT={{MEMORY_LIMIT}}
CPU_LIMIT={{CPU_LIMIT}}
WORKER_CONTAINER_NAME="valid-email-worker"

RUNTIME_FLAGS=()

if [[ "$HARDENING_ENABLED" == "1" ]]; then
  if [[ "$HARDENING_READ_ONLY" == "1" ]]; then
    RUNTIME_FLAGS+=(--read-only)
  fi
  if [[ "$HARDENING_NO_NEW_PRIVILEGES" == "1" ]]; then
    RUNTIME_FLAGS+=(--security-opt no-new-privileges)
  fi
  if [[ "$HARDENING_CAP_DROP_ALL" == "1" ]]; then
    RUNTIME_FLAGS+=(--cap-drop ALL)
  fi

  RUNTIME_FLAGS+=(--tmpfs "/tmp:rw,nosuid,nodev,noexec,size=${HARDENING_TMPFS_SIZE_MB}m")
  RUNTIME_FLAGS+=(--pids-limit "$HARDENING_PIDS_LIMIT")
fi

if [[ -n "$MEMORY_LIMIT" ]]; then
  RUNTIME_FLAGS+=(--memory "$MEMORY_LIMIT")
fi

if [[ -n "$CPU_LIMIT" ]]; then
  RUNTIME_FLAGS+=(--cpus "$CPU_LIMIT")
fi

action="${1:-status}"

case "$action" in
  start)
    docker rm -f "$WORKER_CONTAINER_NAME" >/dev/null 2>&1 || true
    docker run -d --name "$WORKER_CONTAINER_NAME" --restart "$RESTART_POLICY" \
      "${RUNTIME_FLAGS[@]}" \
      --env-file "$ENV_PATH" \
      "$IMAGE"
    ;;
  stop)
    docker stop "$WORKER_CONTAINER_NAME" >/dev/null 2>&1 || true
    docker rm -f "$WORKER_CONTAINER_NAME" >/dev/null 2>&1 || true
    ;;
  restart)
    "$0" stop
    "$0" start
    ;;
  status)
    if docker inspect -f '{{.State.Running}}' "$WORKER_CONTAINER_NAME" 2>/dev/null | grep -q '^true$'; then
      echo "running"
      exit 0
    fi
    echo "stopped"
    exit 3
    ;;
  *)
    echo "Unsupported action: $action" >&2
    exit 2
    ;;
esac
WORKERCTL
chmod 0755 "$WORKER_CONTROL_SCRIPT"

if [[ "$AGENT_ENABLED" != "1" ]]; then
  "$WORKER_CONTROL_SCRIPT" restart
  exit 0
fi

if ! command -v python3 >/dev/null 2>&1; then
  if ! command -v apt-get >/dev/null 2>&1; then
    echo "Unsupported OS. This installer requires apt-get (Ubuntu)."
    exit 1
  fi
  apt-get update -y
  apt-get install -y python3
fi

mkdir -p "$(dirname "$AGENT_ENV_PATH")"
cat > "$AGENT_ENV_PATH" <<EOF
AGENT_TOKEN=$AGENT_TOKEN
AGENT_HMAC_SECRET=$AGENT_HMAC_SECRET
AGENT_SIGNATURE_TTL=$AGENT_SIGNATURE_TTL
AGENT_PORT=$AGENT_PORT
AGENT_ALLOWED_SERVICE=$WORKER_SERVICE_NAME
EOF
chmod 0600 "$AGENT_ENV_PATH"

cat > "$AGENT_SCRIPT_PATH" <<'PYTHON'
#!/usr/bin/env python3
import hashlib
import hmac
import json
import os
import subprocess
import time
import uuid
from http.server import BaseHTTPRequestHandler, HTTPServer

TOKEN = os.getenv("AGENT_TOKEN", "")
SECRET = os.getenv("AGENT_HMAC_SECRET", "")
TTL_SECONDS = int(os.getenv("AGENT_SIGNATURE_TTL", "60"))
ALLOWED_SERVICE = os.getenv("AGENT_ALLOWED_SERVICE", "vev-worker.service")
NONCE_CACHE = {}


def cleanup_nonces() -> None:
    now = int(time.time())
    for nonce, expires_at in list(NONCE_CACHE.items()):
        if expires_at <= now:
            NONCE_CACHE.pop(nonce, None)


class Handler(BaseHTTPRequestHandler):
    def _json(self, code: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self) -> None:
        if self.path != "/v1/commands":
            self._json(404, {"status": "failed", "message": "not_found"})
            return

        auth = self.headers.get("Authorization", "").strip()
        if auth != f"Bearer {TOKEN}":
            self._json(401, {"status": "failed", "message": "unauthorized"})
            return

        timestamp_raw = self.headers.get("X-Timestamp", "").strip()
        nonce = self.headers.get("X-Nonce", "").strip()
        signature = self.headers.get("X-Signature", "").strip()
        if not timestamp_raw or not nonce or not signature:
            self._json(422, {"status": "failed", "message": "missing_signature_headers"})
            return

        try:
            timestamp = int(timestamp_raw)
        except ValueError:
            self._json(422, {"status": "failed", "message": "invalid_timestamp"})
            return

        now = int(time.time())
        if abs(now - timestamp) > TTL_SECONDS:
            self._json(422, {"status": "failed", "message": "signature_expired"})
            return

        cleanup_nonces()
        if nonce in NONCE_CACHE:
            self._json(409, {"status": "failed", "message": "nonce_reused"})
            return

        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length)

        canonical = "\n".join([
            "POST",
            "/v1/commands",
            str(timestamp),
            nonce,
            hashlib.sha256(raw).hexdigest(),
        ])
        expected = hmac.new(SECRET.encode("utf-8"), canonical.encode("utf-8"), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature):
            self._json(401, {"status": "failed", "message": "signature_mismatch"})
            return

        NONCE_CACHE[nonce] = now + TTL_SECONDS

        try:
            payload = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            self._json(422, {"status": "failed", "message": "invalid_json"})
            return

        action = str(payload.get("action", "")).strip().lower()
        if action not in {"start", "stop", "restart", "status"}:
            self._json(422, {"status": "failed", "message": "invalid_action"})
            return

        service = str(payload.get("service", "")).strip() or ALLOWED_SERVICE
        if service != ALLOWED_SERVICE:
            self._json(403, {"status": "failed", "message": "service_not_allowed"})
            return

        timeout_seconds = payload.get("timeout_seconds", 8)
        try:
            timeout = max(2, min(30, int(timeout_seconds)))
        except (TypeError, ValueError):
            timeout = 8

        command = ["systemctl", action, service]
        if action == "status":
            command = ["systemctl", "is-active", service]

        command_id = str(uuid.uuid4())
        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                timeout=timeout,
                check=False,
            )
        except subprocess.TimeoutExpired:
            self._json(504, {
                "status": "failed",
                "agent_command_id": command_id,
                "message": "command_timeout",
            })
            return

        output = "\n".join([completed.stdout.strip(), completed.stderr.strip()]).strip()
        service_state = "unknown"
        state_cmd = subprocess.run(
            ["systemctl", "is-active", service],
            capture_output=True,
            text=True,
            timeout=5,
            check=False,
        )
        if state_cmd.stdout.strip() != "":
            service_state = state_cmd.stdout.strip()

        if completed.returncode == 0:
            self._json(200, {
                "status": "success",
                "agent_command_id": command_id,
                "service": service,
                "action": action,
                "service_state": service_state,
                "output": output,
            })
            return

        self._json(422, {
            "status": "failed",
            "agent_command_id": command_id,
            "service": service,
            "action": action,
            "service_state": service_state,
            "message": output or "systemctl command failed",
        })

    def log_message(self, format: str, *args) -> None:
        return


def main() -> None:
    port = int(os.getenv("AGENT_PORT", "9713"))
    server = HTTPServer(("0.0.0.0", port), Handler)
    server.serve_forever()


if __name__ == "__main__":
    main()
PYTHON
chmod 0755 "$AGENT_SCRIPT_PATH"

cat > "$WORKER_SERVICE_PATH" <<EOF
[Unit]
Description=VEV Worker Container Service
After=docker.service network-online.target
Wants=network-online.target
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=$WORKER_CONTROL_SCRIPT start
ExecStop=$WORKER_CONTROL_SCRIPT stop
ExecReload=$WORKER_CONTROL_SCRIPT restart
TimeoutStartSec=60
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
EOF

cat > "$AGENT_SERVICE_PATH" <<EOF
[Unit]
Description=VEV Worker Control Agent
After=network.target
Wants=network.target

[Service]
Type=simple
EnvironmentFile=$AGENT_ENV_PATH
ExecStart=$AGENT_SCRIPT_PATH
Restart=always
RestartSec=2
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now "$WORKER_SERVICE_NAME"
systemctl enable --now "$AGENT_SERVICE_NAME"

echo "Agent mode installed. Worker service: $WORKER_SERVICE_NAME, agent service: $AGENT_SERVICE_NAME, port: $AGENT_PORT"

BASH;

        return str_replace(
            [
                '{{WORKER_ENV}}',
                '{{REGISTRY}}',
                '{{IMAGE}}',
                '{{ENV_PATH}}',
                '{{RESTART_POLICY}}',
                '{{HARDENING_ENABLED}}',
                '{{HARDENING_READ_ONLY}}',
                '{{HARDENING_NO_NEW_PRIVILEGES}}',
                '{{HARDENING_CAP_DROP_ALL}}',
                '{{HARDENING_TMPFS_SIZE_MB}}',
                '{{HARDENING_PIDS_LIMIT}}',
                '{{MEMORY_LIMIT}}',
                '{{CPU_LIMIT}}',
                '{{AGENT_ENABLED}}',
                '{{AGENT_TOKEN}}',
                '{{AGENT_HMAC_SECRET}}',
                '{{AGENT_SIGNATURE_TTL}}',
                '{{AGENT_PORT}}',
                '{{WORKER_SERVICE_NAME}}',
                '{{AGENT_SERVICE_NAME}}',
            ],
            [
                $escapedEnv,
                $registryArg,
                $imageArg,
                $envPathArg,
                $restartPolicyArg,
                $hardeningEnabledArg,
                $readOnlyArg,
                $noNewPrivilegesArg,
                $capDropAllArg,
                $tmpfsSizeArg,
                $pidsLimitArg,
                $memoryLimitArg,
                $cpuLimitArg,
                $agentEnabledArg,
                $agentTokenArg,
                $agentHmacSecretArg,
                $agentSignatureTTLArg,
                $agentPortArg,
                $workerServiceNameArg,
                $agentServiceNameArg,
            ],
            $template
        );
    }

    private function resolveWorkerImageReference(): string
    {
        $image = trim((string) config('engine.worker_image'));
        $channel = strtolower(trim((string) config('engine.worker_image_channel', 'stable')));
        $digest = trim((string) config('engine.worker_image_digest', ''));
        $stableTag = trim((string) config('engine.worker_image_stable_tag', 'stable'));
        $canaryTag = trim((string) config('engine.worker_image_canary_tag', 'canary'));

        if ($image === '') {
            return $image;
        }

        if ($digest !== '') {
            $normalizedDigest = str_starts_with($digest, 'sha256:') ? $digest : 'sha256:'.$digest;

            return $this->stripImageTag($image).'@'.$normalizedDigest;
        }

        if (str_contains($image, '@') || $this->imageHasExplicitTag($image)) {
            return $image;
        }

        $tag = $channel === 'canary' ? ($canaryTag !== '' ? $canaryTag : 'canary') : ($stableTag !== '' ? $stableTag : 'stable');

        return $image.':'.$tag;
    }

    private function imageHasExplicitTag(string $image): bool
    {
        $image = trim($image);
        if ($image === '' || str_contains($image, '@')) {
            return false;
        }

        $lastSlashPos = strrpos($image, '/');
        $lastColonPos = strrpos($image, ':');

        return $lastColonPos !== false && ($lastSlashPos === false || $lastColonPos > $lastSlashPos);
    }

    private function stripImageTag(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return $image;
        }

        if (str_contains($image, '@')) {
            return (string) explode('@', $image, 2)[0];
        }

        if (! $this->imageHasExplicitTag($image)) {
            return $image;
        }

        return (string) substr($image, 0, (int) strrpos($image, ':'));
    }

    private function envLine(string $key, string $value): string
    {
        return $key.'='.$value;
    }
}
