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
        $envKey = $prefix . '/' . $server->id . '/' . $bundleUuid . '/worker.env';
        $scriptKey = $prefix . '/' . $server->id . '/' . $bundleUuid . '/install.sh';

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

        return $user->createToken('engine-server:' . $server->id);
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

        return implode("\n", $lines) . "\n";
    }

    private function buildInstallScript(string $workerEnv): string
    {
        $registry = (string) config('engine.worker_registry');
        $image = (string) config('engine.worker_image');
        $envPath = (string) config('engine.worker_env_path');

        $escapedEnv = rtrim($workerEnv, "\n");

        return <<<"BASH"
#!/usr/bin/env bash
set -euo pipefail

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

mkdir -p "$(dirname "$envPath")"
cat > "$envPath" <<'ENVFILE'
$escapedEnv
ENVFILE

printf "%s\n" "$GHCR_TOKEN" | docker login $registry -u "$GHCR_USER" --password-stdin

docker pull $image

docker rm -f valid-email-worker >/dev/null 2>&1 || true

docker run -d --name valid-email-worker --restart unless-stopped \
  --env-file "$envPath" \
  $image

BASH;
    }

    private function envLine(string $key, string $value): string
    {
        return $key . '=' . $value;
    }
}
