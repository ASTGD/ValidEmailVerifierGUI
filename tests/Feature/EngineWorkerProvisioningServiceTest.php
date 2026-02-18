<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\User;
use App\Services\EngineWorkerProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
