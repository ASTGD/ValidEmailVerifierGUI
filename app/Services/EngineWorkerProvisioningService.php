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
        $this->guardConfig();

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
        $installScript = $this->buildInstallScript($workerEnv);

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

    private function guardConfig(): void
    {
        $registry = (string) config('engine.worker_registry');
        $image = (string) config('engine.worker_image');
        $envPath = (string) config('engine.worker_env_path');
        $apiBaseUrl = trim((string) config('app.url'), '/');

        if ($registry === '' || $image === '' || $envPath === '' || $apiBaseUrl === '') {
            throw new RuntimeException('Provisioning config is incomplete.');
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
        $lines = [
            $this->envLine('ENGINE_API_BASE_URL', $apiBaseUrl),
            $this->envLine('ENGINE_API_TOKEN', $plainTextToken),
            $this->envLine('ENGINE_SERVER_IP', $server->ip_address),
            $this->envLine('ENGINE_SERVER_NAME', $server->name),
            $this->envLine('WORKER_ID', (string) $server->id),
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

        return implode("\n", $lines)."\n";
    }

    private function buildInstallScript(string $workerEnv): string
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

docker rm -f valid-email-worker >/dev/null 2>&1 || true

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

docker run -d --name valid-email-worker --restart "$RESTART_POLICY" \
  "${RUNTIME_FLAGS[@]}" \
  --env-file "$ENV_PATH" \
  "$IMAGE"

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
