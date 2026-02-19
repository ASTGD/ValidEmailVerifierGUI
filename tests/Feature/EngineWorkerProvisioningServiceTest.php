<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\User;
use App\Services\EngineWorkerProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EngineWorkerProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_uses_channel_tag_and_runtime_hardening_flags(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();

        config()->set('engine.worker_image', 'ghcr.io/astgd/vev-engine');
        config()->set('engine.worker_image_channel', 'canary');
        config()->set('engine.worker_image_digest', '');
        config()->set('engine.worker_runtime_hardening_enabled', true);
        config()->set('engine.worker_runtime_read_only', true);
        config()->set('engine.worker_runtime_no_new_privileges', true);
        config()->set('engine.worker_runtime_cap_drop_all', true);
        config()->set('engine.worker_runtime_tmpfs_size_mb', 96);
        config()->set('engine.worker_runtime_pids_limit', 300);
        config()->set('engine.worker_runtime_memory_limit', '512m');
        config()->set('engine.worker_runtime_cpu_limit', '1.5');

        $bundle = app(EngineWorkerProvisioningService::class)->createBundle(
            $this->createEngineServer(),
            User::factory()->create()
        );

        $script = Storage::disk('local')->get($bundle->script_key);

        $this->assertStringContainsString("IMAGE='ghcr.io/astgd/vev-engine:canary'", $script);
        $this->assertStringContainsString('HARDENING_ENABLED=1', $script);
        $this->assertStringContainsString('HARDENING_READ_ONLY=1', $script);
        $this->assertStringContainsString('HARDENING_NO_NEW_PRIVILEGES=1', $script);
        $this->assertStringContainsString('HARDENING_CAP_DROP_ALL=1', $script);
        $this->assertStringContainsString('HARDENING_TMPFS_SIZE_MB=96', $script);
        $this->assertStringContainsString('HARDENING_PIDS_LIMIT=300', $script);
        $this->assertStringContainsString("MEMORY_LIMIT='512m'", $script);
        $this->assertStringContainsString("CPU_LIMIT='1.5'", $script);
        $this->assertStringContainsString('RUNTIME_FLAGS+=(--read-only)', $script);
        $this->assertStringContainsString('RUNTIME_FLAGS+=(--security-opt no-new-privileges)', $script);
        $this->assertStringContainsString('RUNTIME_FLAGS+=(--cap-drop ALL)', $script);
        $this->assertStringContainsString('"${RUNTIME_FLAGS[@]}"', $script);
    }

    public function test_bundle_pins_image_digest_when_digest_is_configured(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();

        config()->set('engine.worker_image', 'ghcr.io/astgd/vev-engine:stable');
        config()->set('engine.worker_image_channel', 'canary');
        config()->set('engine.worker_image_digest', 'sha256:abc123def456');

        $bundle = app(EngineWorkerProvisioningService::class)->createBundle(
            $this->createEngineServer(),
            User::factory()->create()
        );

        $script = Storage::disk('local')->get($bundle->script_key);

        $this->assertStringContainsString("IMAGE='ghcr.io/astgd/vev-engine@sha256:abc123def456'", $script);
    }

    public function test_bundle_keeps_explicit_image_tag_when_no_digest_is_set(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();

        config()->set('engine.worker_image', 'ghcr.io/astgd/vev-engine:v1.2.3');
        config()->set('engine.worker_image_channel', 'canary');
        config()->set('engine.worker_image_digest', '');

        $bundle = app(EngineWorkerProvisioningService::class)->createBundle(
            $this->createEngineServer(),
            User::factory()->create()
        );

        $script = Storage::disk('local')->get($bundle->script_key);

        $this->assertStringContainsString("IMAGE='ghcr.io/astgd/vev-engine:v1.2.3'", $script);
    }

    public function test_bundle_includes_control_plane_heartbeat_env_when_configured(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();
        config()->set('services.go_control_plane.base_url', 'https://go.example.test');
        config()->set('services.go_control_plane.token', 'go-token-123');

        $bundle = app(EngineWorkerProvisioningService::class)->createBundle(
            $this->createEngineServer(),
            User::factory()->create()
        );

        $workerEnv = Storage::disk('local')->get($bundle->env_key);

        $this->assertStringContainsString('LARAVEL_HEARTBEAT_ENABLED=true', $workerEnv);
        $this->assertStringContainsString('LARAVEL_HEARTBEAT_EVERY_N=10', $workerEnv);
        $this->assertStringContainsString('CONTROL_PLANE_BASE_URL=https://go.example.test', $workerEnv);
        $this->assertStringContainsString('CONTROL_PLANE_TOKEN=go-token-123', $workerEnv);
        $this->assertStringContainsString('CONTROL_PLANE_HEARTBEAT_ENABLED=true', $workerEnv);
        $this->assertStringContainsString('CONTROL_PLANE_POLICY_SYNC_ENABLED=true', $workerEnv);
    }

    public function test_bundle_installs_worker_agent_and_systemd_units_for_agent_mode(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();

        $server = $this->createEngineServer();
        $server->update([
            'process_control_mode' => 'agent_systemd',
            'agent_enabled' => true,
            'agent_service_name' => 'vev-worker.service',
        ]);

        $bundle = app(EngineWorkerProvisioningService::class)->createBundle(
            $server->fresh(),
            User::factory()->create()
        );

        $script = Storage::disk('local')->get($bundle->script_key);

        $this->assertStringContainsString('AGENT_ENABLED=1', $script);
        $this->assertStringContainsString('AGENT_PORT=9713', $script);
        $this->assertStringContainsString("AGENT_SERVICE_NAME='vev-worker-agent.service'", $script);
        $this->assertStringContainsString('cat > "$AGENT_SCRIPT_PATH" <<\'PYTHON\'', $script);
        $this->assertStringContainsString('cat > "$WORKER_SERVICE_PATH" <<EOF', $script);
        $this->assertStringContainsString('ExecStart=$WORKER_CONTROL_SCRIPT start', $script);
        $this->assertStringContainsString('systemctl enable --now "$WORKER_SERVICE_NAME"', $script);
        $this->assertStringContainsString('systemctl enable --now "$AGENT_SERVICE_NAME"', $script);
    }

    public function test_bundle_requires_agent_credentials_for_agent_mode(): void
    {
        Storage::fake('local');
        $this->setBaseProvisioningConfig();
        config()->set('engine_servers.process_control.agent_token', '');
        config()->set('engine_servers.process_control.agent_hmac_secret', '');

        $server = $this->createEngineServer();
        $server->update([
            'process_control_mode' => 'agent_systemd',
            'agent_enabled' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Agent process control credentials are missing.');

        app(EngineWorkerProvisioningService::class)->createBundle($server->fresh(), User::factory()->create());
    }

    private function setBaseProvisioningConfig(): void
    {
        config()->set('app.url', 'http://localhost:8082');
        config()->set('engine.worker_registry', 'ghcr.io');
        config()->set('engine.worker_env_path', '/etc/vev/worker.env');
        config()->set('engine.worker_provisioning_disk', 'local');
        config()->set('engine.worker_provisioning_prefix', 'provisioning/worker-tests');
        config()->set('engine.worker_provisioning_ttl_minutes', 60);
        config()->set('engine.worker_image_stable_tag', 'stable');
        config()->set('engine.worker_image_canary_tag', 'canary');
        config()->set('engine.worker_runtime_restart_policy', 'unless-stopped');
        config()->set('engine.worker_agent_port', 9713);
        config()->set('engine_servers.process_control.agent_token', 'agent-token');
        config()->set('engine_servers.process_control.agent_hmac_secret', 'agent-secret');
        config()->set('engine_servers.process_control.signature_ttl_seconds', 60);
    }

    private function createEngineServer(): EngineServer
    {
        return EngineServer::query()->create([
            'name' => 'smtp-worker-test',
            'ip_address' => '127.0.0.10',
            'environment' => 'local',
            'region' => 'us-east-1',
            'is_active' => true,
        ]);
    }
}
