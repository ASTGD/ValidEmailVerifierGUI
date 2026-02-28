<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\EngineServerCommand;
use App\Models\EngineServerProvisioningBundle;
use App\Models\EngineSetting;
use App\Models\EngineWorkerPool;
use App\Models\SmtpDecisionTrace;
use App\Models\VerificationWorker;
use App\Models\VerifierDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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
        config()->set('engine.worker_agent_port', 9713);
        config()->set('engine_servers.process_control.agent_token', 'agent-token');
        config()->set('engine_servers.process_control.agent_hmac_secret', 'agent-secret');
    }

    public function test_internal_engine_server_api_requires_internal_token(): void
    {
        $this->getJson('/api/internal/engine-servers')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['request_id']);

        $this->getJson('/api/internal/smtp-decision-traces')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['request_id']);

        $this->getJson('/api/internal/engine-pools')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['request_id']);

        $this->getJson('/api/internal/provisioning-credentials')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['request_id']);
    }

    public function test_internal_provisioning_credentials_api_supports_show_update_and_reveal(): void
    {
        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/provisioning-credentials')
            ->assertOk()
            ->assertJsonPath('data.ghcr_username', '')
            ->assertJsonPath('data.ghcr_token_configured', false)
            ->assertJsonPath('data.ghcr_token_masked', '');

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/provisioning-credentials', [
                'ghcr_username' => 'astgd',
                'ghcr_token' => 'ghp_test_token_12345',
            ])
            ->assertOk()
            ->assertJsonPath('data.ghcr_username', 'astgd')
            ->assertJsonPath('data.ghcr_token_configured', true)
            ->assertJsonPath('data.ghcr_token_masked', '******');

        $settings = EngineSetting::query()->firstOrFail();
        $this->assertSame('astgd', $settings->provisioning_ghcr_username);
        $this->assertSame('ghp_test_token_12345', $settings->provisioning_ghcr_token);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/provisioning-credentials/reveal')
            ->assertOk()
            ->assertJsonPath('data.ghcr_token', 'ghp_test_token_12345');

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/provisioning-credentials', [
                'ghcr_username' => 'astgd-updated',
                'ghcr_token' => '******',
            ])
            ->assertOk()
            ->assertJsonPath('data.ghcr_username', 'astgd-updated')
            ->assertJsonPath('data.ghcr_token_configured', true);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/provisioning-credentials/reveal')
            ->assertOk()
            ->assertJsonPath('data.ghcr_token', 'ghp_test_token_12345');

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/provisioning-credentials', [
                'ghcr_username' => 'astgd',
                'clear_ghcr_token' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.ghcr_token_configured', false)
            ->assertJsonPath('data.ghcr_token_masked', '');

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/provisioning-credentials/reveal')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'ghcr_token_missing');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_provisioning_credentials_updated',
            'subject_type' => EngineSetting::class,
            'subject_id' => (string) $settings->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_provisioning_credentials_revealed',
            'subject_type' => EngineSetting::class,
            'subject_id' => (string) $settings->id,
        ]);
    }

    public function test_internal_provisioning_credentials_api_requires_username_on_update(): void
    {
        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/provisioning-credentials', [
                'ghcr_username' => '',
                'ghcr_token' => 'token-value',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'request_id',
                'errors' => [
                    'ghcr_username',
                ],
            ]);
    }

    public function test_internal_engine_server_api_lists_and_mutates_servers(): void
    {
        $domain = VerifierDomain::query()->create([
            'domain' => 'example.org',
            'is_active' => true,
        ]);
        $primaryPool = EngineWorkerPool::query()->create([
            'slug' => 'pool-primary',
            'name' => 'Pool Primary',
            'is_active' => true,
            'is_default' => false,
            'provider_profiles' => [
                'generic' => 'standard',
                'gmail' => 'standard',
                'microsoft' => 'standard',
                'yahoo' => 'standard',
            ],
        ]);
        $secondaryPool = EngineWorkerPool::query()->create([
            'slug' => 'pool-secondary',
            'name' => 'Pool Secondary',
            'is_active' => true,
            'is_default' => false,
            'provider_profiles' => [
                'generic' => 'standard',
                'gmail' => 'low_hit',
                'microsoft' => 'standard',
                'yahoo' => 'standard',
            ],
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
            'worker_pool_id' => $primaryPool->id,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-servers')
            ->assertOk()
            ->assertJsonPath('data.servers.0.id', $server->id)
            ->assertJsonPath('data.servers.0.identity_domain', 'example.org')
            ->assertJsonPath('data.servers.0.worker_pool_id', $primaryPool->id)
            ->assertJsonPath('data.servers.0.worker_pool_slug', 'pool-primary')
            ->assertJsonPath('data.servers.0.worker_pool_name', 'Pool Primary')
            ->assertJsonPath('data.servers.0.process_state', 'unknown')
            ->assertJsonPath('data.servers.0.heartbeat_state', 'none')
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
                'worker_pool_id' => $primaryPool->id,
                'notes' => 'created via go control plane',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'engine-b')
            ->assertJsonPath('data.worker_pool_id', $primaryPool->id)
            ->assertJsonPath('data.worker_pool_slug', 'pool-primary');

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
                'worker_pool_id' => $secondaryPool->id,
                'notes' => 'updated via go control plane',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'engine-b-updated')
            ->assertJsonPath('data.drain_mode', true)
            ->assertJsonPath('data.max_concurrency', 16)
            ->assertJsonPath('data.worker_pool_id', $secondaryPool->id)
            ->assertJsonPath('data.worker_pool_slug', 'pool-secondary')
            ->assertJsonPath('data.worker_pool_name', 'Pool Secondary');

        $this->assertDatabaseHas('engine_servers', [
            'id' => $createdServer->id,
            'name' => 'engine-b-updated',
            'drain_mode' => true,
            'max_concurrency' => 16,
            'worker_pool_id' => $secondaryPool->id,
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

    public function test_internal_engine_pool_api_supports_crud_archive_and_default_switch(): void
    {
        /** @var EngineWorkerPool $defaultPool */
        $defaultPool = EngineWorkerPool::query()->where('is_default', true)->firstOrFail();

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-pools')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $defaultPool->slug);

        $createResponse = $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools', [
                'slug' => 'gmail-lowhit',
                'name' => 'Gmail Low Hit',
                'description' => 'Low hit profile for Gmail',
                'is_active' => true,
                'provider_profiles' => [
                    'generic' => 'standard',
                    'gmail' => 'low_hit',
                    'microsoft' => 'standard',
                    'yahoo' => 'warmup',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'gmail-lowhit')
            ->assertJsonPath('data.provider_profiles.gmail', 'low_hit')
            ->assertJsonPath('data.provider_profiles.yahoo', 'warmup');

        $poolID = (int) $createResponse->json('data.id');
        $pool = EngineWorkerPool::query()->findOrFail($poolID);

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/engine-pools/'.$poolID, [
                'slug' => 'gmail-lowhit',
                'name' => 'Gmail Low Hit Updated',
                'description' => 'Updated profile',
                'is_active' => true,
                'is_default' => false,
                'provider_profiles' => [
                    'generic' => 'standard',
                    'gmail' => 'low_hit',
                    'microsoft' => 'warmup',
                    'yahoo' => 'standard',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Gmail Low Hit Updated')
            ->assertJsonPath('data.provider_profiles.microsoft', 'warmup');

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools/'.$poolID.'/set-default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('engine_worker_pools', [
            'id' => $poolID,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('engine_worker_pools', [
            'id' => $defaultPool->id,
            'is_default' => false,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->putJson('/api/internal/engine-pools/'.$poolID, [
                'slug' => 'gmail-lowhit',
                'name' => 'Gmail Low Hit Updated',
                'description' => 'Updated profile',
                'is_active' => false,
                'is_default' => true,
                'provider_profiles' => [
                    'generic' => 'standard',
                    'gmail' => 'low_hit',
                    'microsoft' => 'warmup',
                    'yahoo' => 'standard',
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'pool_default_inactive');

        $server = EngineServer::query()->create([
            'name' => 'engine-pool-guard',
            'ip_address' => '10.0.0.201',
            'is_active' => true,
            'drain_mode' => false,
            'worker_pool_id' => $poolID,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools/'.$poolID.'/archive')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'pool_archive_blocked');

        $server->update([
            'worker_pool_id' => $defaultPool->id,
        ]);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools/'.$defaultPool->id.'/set-default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools/'.$poolID.'/archive')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-pools/'.$defaultPool->id.'/archive')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'pool_archive_blocked');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_pool_created',
            'subject_type' => EngineWorkerPool::class,
            'subject_id' => (string) $poolID,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_pool_updated',
            'subject_type' => EngineWorkerPool::class,
            'subject_id' => (string) $poolID,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_pool_default_set',
            'subject_type' => EngineWorkerPool::class,
            'subject_id' => (string) $poolID,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_pool_archived',
            'subject_type' => EngineWorkerPool::class,
            'subject_id' => (string) $poolID,
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

    public function test_internal_engine_server_api_rejects_inactive_pool_assignment(): void
    {
        $inactivePool = EngineWorkerPool::query()->create([
            'slug' => 'archived-pool',
            'name' => 'Archived Pool',
            'is_active' => false,
            'is_default' => false,
            'provider_profiles' => [
                'generic' => 'standard',
                'gmail' => 'standard',
                'microsoft' => 'standard',
                'yahoo' => 'standard',
            ],
        ]);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers', [
                'name' => 'engine-invalid-pool',
                'ip_address' => '10.0.0.91',
                'is_active' => true,
                'drain_mode' => false,
                'worker_pool_id' => $inactivePool->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure([
                'request_id',
                'errors' => ['worker_pool_id'],
            ]);
    }

    public function test_internal_engine_server_api_blocks_delete_for_active_server(): void
    {
        $server = EngineServer::query()->create([
            'name' => 'engine-active',
            'ip_address' => '10.0.0.15',
            'is_active' => true,
            'drain_mode' => false,
            'last_heartbeat_at' => now(),
            'last_agent_status' => [
                'service_state' => 'active',
            ],
        ]);

        $this->withHeaders($this->internalHeaders())
            ->deleteJson('/api/internal/engine-servers/'.$server->id)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'server_delete_blocked')
            ->assertJsonPath('details.process_state', 'running')
            ->assertJsonPath('details.heartbeat_state', 'healthy')
            ->assertJsonStructure(['request_id']);

        $this->assertDatabaseHas('engine_servers', [
            'id' => $server->id,
        ]);
    }

    public function test_internal_engine_server_api_deletes_stopped_server_and_related_records(): void
    {
        $server = EngineServer::query()->create([
            'name' => 'engine-stopped',
            'ip_address' => '10.0.0.16',
            'is_active' => true,
            'drain_mode' => false,
            'last_heartbeat_at' => now()->subMinutes(30),
            'last_agent_status' => [
                'service_state' => 'inactive',
            ],
        ]);

        $bundle = EngineServerProvisioningBundle::query()->create([
            'engine_server_id' => $server->id,
            'bundle_uuid' => (string) fake()->uuid(),
            'env_key' => 'env-key',
            'script_key' => 'script-key',
            'expires_at' => now()->addHour(),
        ]);

        $command = EngineServerCommand::query()->create([
            'engine_server_id' => $server->id,
            'action' => 'stop',
            'status' => 'success',
            'source' => 'go_control_plane_internal_api',
            'request_id' => (string) fake()->uuid(),
        ]);

        $worker = VerificationWorker::query()->create([
            'worker_id' => 'worker-delete-test',
            'engine_server_id' => $server->id,
            'version' => 'dev',
            'last_seen_at' => now()->subMinutes(15),
        ]);

        $this->withHeaders($this->internalHeaders())
            ->deleteJson('/api/internal/engine-servers/'.$server->id)
            ->assertOk()
            ->assertJsonPath('data.id', $server->id)
            ->assertJsonPath('data.name', 'engine-stopped')
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('engine_servers', [
            'id' => $server->id,
        ]);
        $this->assertDatabaseMissing('engine_server_provisioning_bundles', [
            'id' => $bundle->id,
        ]);
        $this->assertDatabaseMissing('engine_server_commands', [
            'id' => $command->id,
        ]);
        $this->assertDatabaseMissing('verification_workers', [
            'id' => $worker->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'engine_server_deleted',
            'subject_type' => EngineServer::class,
            'subject_id' => (string) $server->id,
        ]);
    }

    public function test_internal_engine_server_api_lists_smtp_decision_traces_with_filters(): void
    {
        SmtpDecisionTrace::query()->create([
            'verification_job_id' => (string) fake()->uuid(),
            'verification_job_chunk_id' => (string) fake()->uuid(),
            'email_hash' => str_repeat('a', 64),
            'provider' => 'gmail',
            'policy_version' => 'v4.1.0',
            'matched_rule_id' => 'rule-gmail-tempfail',
            'decision_class' => 'unknown',
            'smtp_code' => '451',
            'enhanced_code' => '4.7.1',
            'retry_strategy' => 'tempfail',
            'reason_tag' => 'provider_tempfail_unresolved',
            'confidence_hint' => 'medium',
            'session_strategy_id' => 'gmail:normal',
            'attempt_route' => [
                'route' => 'mx:mx1.gmail.test',
                'mx_host' => 'mx1.gmail.test',
                'attempt_number' => 1,
                'worker_id' => 'worker-a',
                'pool' => 'pool-a',
                'provider' => 'gmail',
            ],
            'trace_payload' => [
                'attempt_chain' => [
                    ['attempt_number' => 1, 'mx_host' => 'mx1.gmail.test', 'reason_tag' => 'provider_tempfail_unresolved'],
                ],
            ],
            'observed_at' => now()->subMinute(),
        ]);

        SmtpDecisionTrace::query()->create([
            'verification_job_id' => (string) fake()->uuid(),
            'verification_job_chunk_id' => (string) fake()->uuid(),
            'email_hash' => str_repeat('b', 64),
            'provider' => 'yahoo',
            'policy_version' => 'v4.1.0',
            'matched_rule_id' => 'rule-yahoo-valid',
            'decision_class' => 'deliverable',
            'reason_tag' => 'mailbox_exists',
            'confidence_hint' => 'high',
            'session_strategy_id' => 'yahoo:normal',
            'attempt_route' => ['route' => 'mx:mx.yahoo.test'],
            'trace_payload' => ['attempt_chain' => []],
            'observed_at' => now(),
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/smtp-decision-traces?provider=gmail&decision_class=unknown&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'gmail')
            ->assertJsonPath('data.0.reason_tag', 'provider_tempfail_unresolved')
            ->assertJsonPath('data.0.attempt_chain.0.mx_host', 'mx1.gmail.test')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonStructure([
                'meta' => ['next_before_id'],
            ]);
    }

    public function test_internal_engine_server_api_can_execute_agent_command(): void
    {
        Http::fake([
            'https://agent.example/v1/commands' => Http::response([
                'status' => 'success',
                'agent_command_id' => 'agt-100',
                'service_state' => 'inactive',
            ], 200),
        ]);

        $server = EngineServer::query()->create([
            'name' => 'engine-command',
            'ip_address' => '10.0.0.50',
            'is_active' => true,
            'drain_mode' => false,
            'process_control_mode' => 'agent_systemd',
            'agent_enabled' => true,
            'agent_base_url' => 'https://agent.example',
            'agent_timeout_seconds' => 6,
            'agent_verify_tls' => true,
            'agent_service_name' => 'vev-worker.service',
        ]);

        $response = $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers/'.$server->id.'/commands', [
                'action' => 'stop',
                'reason' => 'maintenance',
                'idempotency_key' => 'stop-'.$server->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'stop')
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.agent_command_id', 'agt-100');

        $commandId = (string) $response->json('data.id');
        $this->assertDatabaseHas('engine_server_commands', [
            'id' => $commandId,
            'engine_server_id' => $server->id,
            'action' => 'stop',
            'status' => 'success',
            'agent_command_id' => 'agt-100',
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/internal/engine-servers/'.$server->id.'/commands/'.$commandId)
            ->assertOk()
            ->assertJsonPath('data.id', $commandId)
            ->assertJsonPath('data.status', 'success');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return $request->url() === 'https://agent.example/v1/commands'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === 'Bearer agent-token';
        });
    }

    public function test_internal_engine_server_api_defaults_agent_base_url_for_agent_mode(): void
    {
        $response = $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers', [
                'name' => 'engine-agent-default-url',
                'ip_address' => '10.0.0.77',
                'is_active' => true,
                'drain_mode' => false,
                'process_control_mode' => 'agent_systemd',
                'agent_enabled' => true,
                'agent_service_name' => 'vev-worker.service',
            ])
            ->assertCreated()
            ->assertJsonPath('data.process_control_mode', 'agent_systemd')
            ->assertJsonPath('data.agent_enabled', true)
            ->assertJsonPath('data.agent_base_url', 'http://10.0.0.77:9713');

        $this->assertDatabaseHas('engine_servers', [
            'id' => (int) $response->json('data.id'),
            'agent_base_url' => 'http://10.0.0.77:9713',
        ]);
    }

    public function test_internal_engine_server_api_marks_command_failed_when_agent_process_control_disabled(): void
    {
        Http::fake();

        $server = EngineServer::query()->create([
            'name' => 'engine-command-disabled',
            'ip_address' => '10.0.0.51',
            'is_active' => true,
            'drain_mode' => false,
            'process_control_mode' => 'control_plane_only',
            'agent_enabled' => false,
        ]);

        $response = $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers/'.$server->id.'/commands', [
                'action' => 'start',
                'idempotency_key' => 'start-'.$server->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'start')
            ->assertJsonPath('data.status', 'failed');

        $commandId = (string) $response->json('data.id');
        $this->assertDatabaseHas('engine_server_commands', [
            'id' => $commandId,
            'engine_server_id' => $server->id,
            'action' => 'start',
            'status' => 'failed',
        ]);

        Http::assertNothingSent();
    }

    public function test_internal_engine_server_api_sets_unknown_process_state_when_agent_is_unreachable(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        $server = EngineServer::query()->create([
            'name' => 'engine-unreachable-agent',
            'ip_address' => '10.0.0.52',
            'is_active' => true,
            'drain_mode' => false,
            'last_heartbeat_at' => now()->subMinutes(20),
            'process_control_mode' => 'agent_systemd',
            'agent_enabled' => true,
            'agent_base_url' => 'http://10.0.0.52:9713',
            'last_agent_status' => [
                'service_state' => 'active',
            ],
        ]);

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/internal/engine-servers/'.$server->id.'/commands', [
                'action' => 'stop',
                'reason' => 'host-reinstalled',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $server->refresh();
        $this->assertSame('unknown', strtolower((string) data_get($server->last_agent_status, 'service_state')));

        $this->withHeaders($this->internalHeaders())
            ->deleteJson('/api/internal/engine-servers/'.$server->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
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
