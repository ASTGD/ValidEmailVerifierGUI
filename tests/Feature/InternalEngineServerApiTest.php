<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\VerifierDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalEngineServerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.go_control_plane.internal_api_token', 'internal-token');
        config()->set('services.go_control_plane.internal_api_rate_limit_per_minute', 500);
        config()->set('engine.worker_registry', 'ghcr.io');
        config()->set('engine.worker_image', 'ghcr.io/astgd/vev-engine');
        config()->set('engine.worker_env_path', '/opt/vev/worker.env');
    }

    public function test_internal_engine_server_api_requires_internal_token(): void
    {
        $this->getJson('/api/internal/engine-servers')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['request_id']);
    }

    public function test_internal_engine_server_api_lists_and_mutates_servers(): void
    {
        $domain = VerifierDomain::query()->create([
            'domain' => 'example.org',
            'is_active' => true,
        ]);

        $server = EngineServer::query()->create([
            'name' => 'engine-a',
            'ip_address' => '10.0.0.10',
            'environment' => 'production',
            'region' => 'us-east-1',
            'is_active' => true,
            'drain_mode' => false,
            'max_concurrency' => 12,
            'helo_name' => 'helo.example.org',
            'mail_from_address' => 'probe@example.org',
            'verifier_domain_id' => $domain->id,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-servers')
            ->assertOk()
            ->assertJsonPath('data.servers.0.id', $server->id)
            ->assertJsonPath('data.servers.0.identity_domain', 'example.org')
            ->assertJsonPath('data.verifier_domains.0.domain', 'example.org');

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers', [
                'name' => 'engine-b',
                'ip_address' => '10.0.0.11',
                'environment' => 'staging',
                'region' => 'us-west-2',
                'is_active' => true,
                'drain_mode' => false,
                'max_concurrency' => 8,
                'helo_name' => 'helo2.example.org',
                'mail_from_address' => 'probe2@example.org',
                'verifier_domain_id' => $domain->id,
                'notes' => 'created via go control plane',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'engine-b');

        $createdServer = EngineServer::query()->where('name', 'engine-b')->firstOrFail();

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/engine-servers/'.$createdServer->id, [
                'name' => 'engine-b-updated',
                'ip_address' => '10.0.0.11',
                'environment' => 'staging',
                'region' => 'eu-central-1',
                'is_active' => true,
                'drain_mode' => true,
                'max_concurrency' => 16,
                'helo_name' => 'helo3.example.org',
                'mail_from_address' => 'probe3@example.org',
                'verifier_domain_id' => $domain->id,
                'notes' => 'updated via go control plane',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'engine-b-updated')
            ->assertJsonPath('data.drain_mode', true)
            ->assertJsonPath('data.max_concurrency', 16);

        $this->assertDatabaseHas('engine_servers', [
            'id' => $createdServer->id,
            'name' => 'engine-b-updated',
            'drain_mode' => true,
            'max_concurrency' => 16,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_server_created',
            'subject_type' => EngineServer::class,
            'subject_id' => (string) $createdServer->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_server_updated',
            'subject_type' => EngineServer::class,
            'subject_id' => (string) $createdServer->id,
        ]);
    }

    public function test_internal_engine_server_api_can_generate_and_read_latest_bundle(): void
    {
        $server = EngineServer::query()->create([
            'name' => 'engine-provision',
            'ip_address' => '10.0.0.12',
            'is_active' => true,
            'drain_mode' => false,
        ]);

        $createResponse = $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers/'.$server->id.'/provisioning-bundles')
            ->assertCreated()
            ->assertJsonPath('data.engine_server_id', $server->id)
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonStructure([
                'data' => [
                    'bundle_uuid',
                    'download_urls' => ['install', 'env'],
                    'install_command_template',
                ],
            ]);

        $bundleUuid = (string) $createResponse->json('data.bundle_uuid');

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-servers/'.$server->id.'/provisioning-bundles/latest')
            ->assertOk()
            ->assertJsonPath('data.bundle_uuid', $bundleUuid);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_worker_bundle_generated',
            'subject_type' => EngineServer::class,
            'subject_id' => (string) $server->id,
        ]);
    }

    public function test_internal_engine_server_api_latest_bundle_not_found_uses_error_envelope(): void
    {
        $server = EngineServer::query()->create([
            'name' => 'engine-without-bundle',
            'ip_address' => '10.0.0.14',
            'is_active' => true,
            'drain_mode' => false,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-servers/'.$server->id.'/provisioning-bundles/latest')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'bundle_not_found')
            ->assertJsonPath('message', 'No provisioning bundle found.')
            ->assertJsonStructure(['request_id']);
    }

    public function test_internal_engine_server_api_validation_errors_use_standard_error_envelope(): void
    {
        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers', [
                'name' => '',
                'ip_address' => '',
                'is_active' => true,
                'drain_mode' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'request_id',
                'errors' => [
                    'name',
                    'ip_address',
                ],
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function internalHeaders(): array
    {
        return [
            'Authorization' => 'Bearer internal-token',
            'X-Triggered-By' => 'go-ui:test',
        ];
    }
}
