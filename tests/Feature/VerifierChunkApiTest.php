<?php

namespace Tests\Feature;

use App\Contracts\EngineStorageUrlSigner;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Models\EngineServer;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifierChunkApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVerifier(): User
    {
        $verifier = User::factory()->create();
        Role::findOrCreate(Roles::VERIFIER_SERVICE, config('auth.defaults.guard'));
        $verifier->assignRole(Roles::VERIFIER_SERVICE);
        Sanctum::actingAs($verifier);

        return $verifier;
    }

    private function makeJob(): VerificationJob
    {
        $customer = User::factory()->create();

        return VerificationJob::create([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$customer->id.'/job/input.csv',
        ]);
    }

    private function makeChunk(VerificationJob $job, array $overrides = []): VerificationJobChunk
    {
        return VerificationJobChunk::create(array_merge([
            'verification_job_id' => $job->id,
            'chunk_no' => 1,
            'status' => 'processing',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
            'email_count' => 10,
        ], $overrides));
    }

    public function test_job_complete_is_idempotent(): void
    {
        $this->actingAsVerifier();

        $job = $this->makeJob();

        $payload = [
            'output_key' => 'results/'.$job->user_id.'/'.$job->id.'/cleaned.csv',
            'output_disk' => 's3',
            'total_emails' => 10,
            'valid_count' => 7,
            'invalid_count' => 2,
            'risky_count' => 1,
            'unknown_count' => 0,
        ];

        $this->postJson(route('api.verifier.jobs.complete', $job), $payload)
            ->assertOk();

        $this->postJson(route('api.verifier.jobs.complete', $job), $payload)
            ->assertOk();

        $this->postJson(route('api.verifier.jobs.complete', $job), array_merge($payload, [
            'output_key' => 'results/'.$job->user_id.'/'.$job->id.'/different.csv',
        ]))
            ->assertStatus(409);
    }

    public function test_chunk_fail_retry_policy(): void
    {
        config(['engine.max_attempts' => 2]);

        $this->actingAsVerifier();

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'claim_expires_at' => now()->addMinutes(10),
            'claim_token' => 'token',
        ]);

        $this->postJson(route('api.verifier.chunks.fail', $chunk), [
            'error_message' => 'timeout',
            'retryable' => true,
        ])->assertOk();

        $chunk->refresh();
        $this->assertSame('pending', $chunk->status);
        $this->assertSame(1, $chunk->attempts);
        $this->assertNull($chunk->claim_expires_at);

        $this->postJson(route('api.verifier.chunks.fail', $chunk), [
            'error_message' => 'timeout again',
            'retryable' => true,
        ])->assertOk();

        $chunk->refresh();
        $this->assertSame('failed', $chunk->status);
        $this->assertSame(2, $chunk->attempts);
    }

    public function test_chunk_complete_is_idempotent(): void
    {
        $this->actingAsVerifier();

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'completed',
            'output_disk' => 's3',
            'valid_key' => 'results/chunks/'.$job->id.'/1/valid.csv',
            'invalid_key' => 'results/chunks/'.$job->id.'/1/invalid.csv',
            'risky_key' => 'results/chunks/'.$job->id.'/1/risky.csv',
            'email_count' => 10,
            'valid_count' => 7,
            'invalid_count' => 2,
            'risky_count' => 1,
        ]);

        $payload = [
            'output_disk' => 's3',
            'valid_key' => $chunk->valid_key,
            'invalid_key' => $chunk->invalid_key,
            'risky_key' => $chunk->risky_key,
            'email_count' => 10,
            'valid_count' => 7,
            'invalid_count' => 2,
            'risky_count' => 1,
        ];

        $this->postJson(route('api.verifier.chunks.complete', $chunk), $payload)
            ->assertOk();

        $this->postJson(route('api.verifier.chunks.complete', $chunk), array_merge($payload, [
            'valid_key' => 'results/chunks/'.$job->id.'/1/other.csv',
        ]))
            ->assertStatus(409);
    }

    public function test_signed_url_endpoints_use_signer(): void
    {
        $this->actingAsVerifier();

        $this->app->instance(EngineStorageUrlSigner::class, new class implements EngineStorageUrlSigner {
            public function temporaryDownloadUrl(string $disk, string $key, int $expirySeconds): string
            {
                return sprintf('https://example.test/download?disk=%s&key=%s', $disk, $key);
            }

            public function temporaryUploadUrl(string $disk, string $key, int $expirySeconds, ?string $contentType = null): string
            {
                return sprintf('https://example.test/upload?disk=%s&key=%s', $disk, $key);
            }
        });

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'input_disk' => 's3',
        ]);

        $this->getJson(route('api.verifier.chunks.input-url', $chunk))
            ->assertOk()
            ->assertJsonFragment([
                'disk' => 's3',
                'key' => $chunk->input_key,
            ]);

        $this->postJson(route('api.verifier.chunks.output-urls', $chunk))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'disk',
                    'expires_in',
                    'targets' => [
                        'valid' => ['key', 'url'],
                        'invalid' => ['key', 'url'],
                        'risky' => ['key', 'url'],
                    ],
                ],
            ]);
    }

    public function test_claim_next_returns_no_content_when_empty(): void
    {
        $this->actingAsVerifier();

        $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-1',
                'ip_address' => '127.0.0.1',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-1',
        ])->assertNoContent();
    }

    public function test_claim_next_returns_single_chunk_and_leases(): void
    {
        $this->actingAsVerifier();

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'pending',
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);

        $response = $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-1',
                'ip_address' => '127.0.0.1',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-1',
            'lease_seconds' => 120,
        ])->assertOk();

        $response->assertJsonFragment([
            'chunk_id' => (string) $chunk->id,
            'job_id' => (string) $job->id,
            'chunk_no' => 1,
            'verification_mode' => VerificationMode::Standard->value,
        ]);

        $chunk->refresh();
        $this->assertSame('processing', $chunk->status);
        $this->assertNotNull($chunk->claim_expires_at);
        $this->assertSame('worker-1', $chunk->assigned_worker_id);
        $this->assertNotNull($chunk->engine_server_id);

        $server = EngineServer::find($chunk->engine_server_id);
        $this->assertNotNull($server);

        $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-1',
                'ip_address' => '127.0.0.1',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-1',
        ])->assertNoContent();
    }
}
