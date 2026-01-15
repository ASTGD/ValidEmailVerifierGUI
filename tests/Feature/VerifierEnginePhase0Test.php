<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\EngineServer;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifierEnginePhase0Test extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_upserts_engine_server(): void
    {
        Sanctum::actingAs($this->makeVerifier());

        $payload = [
            'server' => [
                'name' => 'engine-1',
                'ip_address' => '192.168.1.10',
                'environment' => 'prod',
                'region' => 'us-east-1',
            ],
        ];

        $this->postJson(route('api.verifier.heartbeat'), $payload)
            ->assertOk()
            ->assertJsonStructure(['data' => ['server_id', 'status', 'heartbeat_threshold_minutes']]);

        $this->assertDatabaseHas('engine_servers', [
            'ip_address' => '192.168.1.10',
            'name' => 'engine-1',
        ]);

        $this->postJson(route('api.verifier.heartbeat'), [
            'server' => [
                'name' => 'engine-rename',
                'ip_address' => '192.168.1.10',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('engine_servers', [
            'ip_address' => '192.168.1.10',
            'name' => 'engine-rename',
        ]);
    }

    public function test_job_claim_is_atomic(): void
    {
        Sanctum::actingAs($this->makeVerifier());

        $job = $this->makePendingJob();

        $this->postJson(route('api.verifier.jobs.claim', $job), [
            'lease_seconds' => 300,
        ])->assertOk();

        $this->postJson(route('api.verifier.jobs.claim', $job), [
            'lease_seconds' => 300,
        ])->assertStatus(409);
    }

    public function test_claim_expired_jobs_can_be_reclaimed(): void
    {
        Sanctum::actingAs($this->makeVerifier());

        $job = $this->makePendingJob([
            'claim_expires_at' => now()->subMinutes(10),
            'claim_token' => 'expired-token',
        ]);

        $this->postJson(route('api.verifier.jobs.claim', $job), [
            'lease_seconds' => 300,
        ])->assertOk();

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertNotSame('expired-token', $job->claim_token);
    }

    private function makeVerifier(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(Roles::VERIFIER_SERVICE, config('auth.defaults.guard'));
        $user->assignRole(Roles::VERIFIER_SERVICE);

        return $user;
    }

    private function makePendingJob(array $overrides = []): VerificationJob
    {
        $customer = User::factory()->create();
        $server = EngineServer::create([
            'name' => 'engine-1',
            'ip_address' => '10.0.0.1',
        ]);

        return VerificationJob::create(array_merge([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$customer->id.'/job/input.csv',
            'engine_server_id' => $server->id,
        ], $overrides));
    }
}
