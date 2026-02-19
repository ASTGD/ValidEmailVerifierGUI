<?php

namespace Tests\Feature;

use App\Models\EngineServer;
use App\Models\SmtpDecisionTrace;
use App\Models\VerifierDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
