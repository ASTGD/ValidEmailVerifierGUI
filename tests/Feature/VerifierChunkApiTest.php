<?php

namespace Tests\Feature;

use App\Contracts\EngineStorageUrlSigner;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Models\EngineServer;
use App\Models\EngineSetting;
use App\Models\EngineVerificationPolicy;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\ScreeningToProbeChunkPlanner;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'processing_stage' => 'screening',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
            'email_count' => 10,
        ], $overrides));
    }

    private function setProbeStageEnabled(bool $enabled): void
    {
        EngineSetting::query()->update(['enhanced_mode_enabled' => $enabled]);
        EngineVerificationPolicy::query()
            ->where('mode', 'enhanced')
            ->update(['enabled' => $enabled]);
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

        $this->app->instance(EngineStorageUrlSigner::class, new class implements EngineStorageUrlSigner
        {
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
        EngineSetting::query()->update(['engine_paused' => false]);

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
            'verification_mode' => VerificationMode::Enhanced->value,
            'processing_stage' => 'screening',
            'worker_capability_required' => 'screening',
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

    public function test_engine_paused_blocks_claim_next(): void
    {
        $this->actingAsVerifier();

        EngineSetting::query()->update(['engine_paused' => true]);

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'pending',
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);

        $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-paused',
                'ip_address' => '127.0.0.2',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-paused',
        ])->assertNoContent();

        $chunk->refresh();
        $this->assertSame('pending', $chunk->status);
        $this->assertNull($chunk->claim_expires_at);
        $this->assertNull($chunk->assigned_worker_id);
    }

    public function test_claim_next_skips_unavailable_chunks(): void
    {
        $this->actingAsVerifier();
        EngineSetting::query()->update(['engine_paused' => false]);

        $job = $this->makeJob();
        $futureChunk = $this->makeChunk($job, [
            'chunk_no' => 1,
            'status' => 'pending',
            'available_at' => now()->addMinutes(15),
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);

        $readyChunk = $this->makeChunk($job, [
            'chunk_no' => 2,
            'status' => 'pending',
            'available_at' => null,
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);

        $response = $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-available',
                'ip_address' => '127.0.0.9',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-available',
        ])->assertOk();

        $response->assertJsonFragment([
            'chunk_id' => (string) $readyChunk->id,
        ]);

        $futureChunk->refresh();
        $this->assertSame('pending', $futureChunk->status);
    }

    public function test_claim_next_respects_worker_capability_stage_filter(): void
    {
        $this->actingAsVerifier();
        EngineSetting::query()->update(['engine_paused' => false]);

        $job = $this->makeJob();
        $screeningChunk = $this->makeChunk($job, [
            'chunk_no' => 1,
            'status' => 'pending',
            'processing_stage' => 'screening',
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);
        $probeChunk = $this->makeChunk($job, [
            'chunk_no' => 2,
            'status' => 'pending',
            'processing_stage' => 'smtp_probe',
            'claim_expires_at' => null,
            'claim_token' => null,
            'assigned_worker_id' => null,
        ]);

        $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-screening',
                'ip_address' => '127.0.0.31',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-screening',
            'worker_capability' => 'screening',
        ])->assertOk()->assertJsonFragment([
            'chunk_id' => (string) $screeningChunk->id,
            'processing_stage' => 'screening',
        ]);

        $this->postJson(route('api.verifier.chunks.claim-next'), [
            'engine_server' => [
                'name' => 'engine-probe',
                'ip_address' => '127.0.0.32',
                'environment' => 'test',
                'region' => 'local',
            ],
            'worker_id' => 'worker-probe',
            'worker_capability' => 'smtp_probe',
        ])->assertOk()->assertJsonFragment([
            'chunk_id' => (string) $probeChunk->id,
            'processing_stage' => 'smtp_probe',
            'worker_capability_required' => 'smtp_probe',
        ]);
    }

    public function test_screening_chunk_completion_creates_probe_chunk_candidates(): void
    {
        $this->actingAsVerifier();
        $this->setProbeStageEnabled(true);
        Storage::fake('local');

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'processing',
            'processing_stage' => 'screening',
            'input_disk' => 'local',
            'claim_expires_at' => now()->addMinutes(5),
            'claim_token' => 'claim-token',
            'assigned_worker_id' => 'worker-1',
        ]);

        $validKey = 'results/chunks/'.$job->id.'/1/valid.csv';
        $invalidKey = 'results/chunks/'.$job->id.'/1/invalid.csv';
        $riskyKey = 'results/chunks/'.$job->id.'/1/risky.csv';

        Storage::disk('local')->put($validKey, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('local')->put($invalidKey, "email,reason\nhard-invalid@example.com,syntax\n");
        Storage::disk('local')->put($riskyKey, "email,reason\nprobe@example.com,smtp_tempfail\n");

        $this->postJson(route('api.verifier.chunks.complete', $chunk), [
            'output_disk' => 'local',
            'valid_key' => $validKey,
            'invalid_key' => $invalidKey,
            'risky_key' => $riskyKey,
            'email_count' => 3,
            'valid_count' => 1,
            'invalid_count' => 1,
            'risky_count' => 1,
        ])->assertOk();

        $probeChunk = VerificationJobChunk::query()
            ->where('verification_job_id', $job->id)
            ->where('processing_stage', 'smtp_probe')
            ->where('parent_chunk_id', $chunk->id)
            ->first();

        $this->assertNotNull($probeChunk);
        $this->assertSame('pending', $probeChunk->status);
        $this->assertSame(2, $probeChunk->email_count);
        $this->assertTrue(Storage::disk('local')->exists((string) $probeChunk->input_key));
        $this->assertStringContainsString('valid@example.com', Storage::disk('local')->get((string) $probeChunk->input_key));
        $this->assertStringContainsString('probe@example.com', Storage::disk('local')->get((string) $probeChunk->input_key));
        $this->assertStringNotContainsString('hard-invalid@example.com', Storage::disk('local')->get((string) $probeChunk->input_key));

        $this->assertSame("email,reason\n", Storage::disk('local')->get($validKey));
        $this->assertSame("email,reason\n", Storage::disk('local')->get($riskyKey));
        $this->assertStringContainsString('hard-invalid@example.com,syntax', Storage::disk('local')->get($invalidKey));
    }

    public function test_screening_chunk_completion_does_not_treat_email_address_as_header(): void
    {
        $this->actingAsVerifier();
        $this->setProbeStageEnabled(true);
        Storage::fake('local');

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'processing',
            'processing_stage' => 'screening',
            'input_disk' => 'local',
            'claim_expires_at' => now()->addMinutes(5),
            'claim_token' => 'claim-token',
            'assigned_worker_id' => 'worker-1',
        ]);

        $validKey = 'results/chunks/'.$job->id.'/1/valid.csv';
        $invalidKey = 'results/chunks/'.$job->id.'/1/invalid.csv';
        $riskyKey = 'results/chunks/'.$job->id.'/1/risky.csv';

        Storage::disk('local')->put($validKey, "email,reason\nemail@example.com,smtp_connect_ok\n");
        Storage::disk('local')->put($invalidKey, "email,reason\nhard-invalid@example.com,syntax\n");
        Storage::disk('local')->put($riskyKey, "email,reason\nprobe@example.com,smtp_tempfail\n");

        $this->postJson(route('api.verifier.chunks.complete', $chunk), [
            'output_disk' => 'local',
            'valid_key' => $validKey,
            'invalid_key' => $invalidKey,
            'risky_key' => $riskyKey,
            'email_count' => 3,
            'valid_count' => 1,
            'invalid_count' => 1,
            'risky_count' => 1,
        ])->assertOk();

        $probeChunk = VerificationJobChunk::query()
            ->where('verification_job_id', $job->id)
            ->where('processing_stage', 'smtp_probe')
            ->where('parent_chunk_id', $chunk->id)
            ->first();

        $this->assertNotNull($probeChunk);
        $this->assertSame(2, $probeChunk->email_count);
        $this->assertStringContainsString('email@example.com', Storage::disk('local')->get((string) $probeChunk->input_key));
        $this->assertStringContainsString('probe@example.com', Storage::disk('local')->get((string) $probeChunk->input_key));
    }

    public function test_screening_chunk_completion_skips_probe_handoff_when_probe_disabled(): void
    {
        $this->actingAsVerifier();
        $this->setProbeStageEnabled(false);
        Storage::fake('local');

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'processing',
            'processing_stage' => 'screening',
            'input_disk' => 'local',
            'claim_expires_at' => now()->addMinutes(5),
            'claim_token' => 'claim-token',
            'assigned_worker_id' => 'worker-1',
        ]);

        $validKey = 'results/chunks/'.$job->id.'/1/valid.csv';
        $invalidKey = 'results/chunks/'.$job->id.'/1/invalid.csv';
        $riskyKey = 'results/chunks/'.$job->id.'/1/risky.csv';

        Storage::disk('local')->put($validKey, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('local')->put($invalidKey, "email,reason\nhard-invalid@example.com,syntax\n");
        Storage::disk('local')->put($riskyKey, "email,reason\nprobe@example.com,smtp_tempfail\n");

        $this->postJson(route('api.verifier.chunks.complete', $chunk), [
            'output_disk' => 'local',
            'valid_key' => $validKey,
            'invalid_key' => $invalidKey,
            'risky_key' => $riskyKey,
            'email_count' => 3,
            'valid_count' => 1,
            'invalid_count' => 1,
            'risky_count' => 1,
        ])->assertOk();

        $probeChunk = VerificationJobChunk::query()
            ->where('verification_job_id', $job->id)
            ->where('processing_stage', 'smtp_probe')
            ->where('parent_chunk_id', $chunk->id)
            ->first();

        $this->assertNull($probeChunk);
        $this->assertStringContainsString('valid@example.com', Storage::disk('local')->get($validKey));
        $this->assertStringContainsString('probe@example.com', Storage::disk('local')->get($riskyKey));
        $this->assertStringContainsString('hard-invalid@example.com,syntax', Storage::disk('local')->get($invalidKey));

        $job->refresh();
        $this->assertNotNull($job->metrics);
        $this->assertSame(3, (int) $job->metrics->screening_total_count);
        $this->assertSame(0, (int) $job->metrics->probe_candidate_count);
    }

    public function test_screening_probe_handoff_failure_returns_retryable_response_without_data_loss(): void
    {
        $this->actingAsVerifier();
        $this->setProbeStageEnabled(true);
        Storage::fake('local');

        $job = $this->makeJob();
        $chunk = $this->makeChunk($job, [
            'status' => 'processing',
            'processing_stage' => 'screening',
            'input_disk' => 'local',
            'claim_expires_at' => now()->addMinutes(5),
            'claim_token' => 'claim-token',
            'assigned_worker_id' => 'worker-1',
        ]);

        $validKey = 'results/chunks/'.$job->id.'/1/valid.csv';
        $invalidKey = 'results/chunks/'.$job->id.'/1/invalid.csv';
        $riskyKey = 'results/chunks/'.$job->id.'/1/risky.csv';

        Storage::disk('local')->put($validKey, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('local')->put($invalidKey, "email,reason\nhard-invalid@example.com,syntax\n");
        Storage::disk('local')->put($riskyKey, "email,reason\nprobe@example.com,smtp_tempfail\n");

        $this->app->bind(ScreeningToProbeChunkPlanner::class, static fn () => new class extends ScreeningToProbeChunkPlanner
        {
            public function __construct() {}

            public function plan(VerificationJob $job, VerificationJobChunk $chunk, string $outputDisk): array
            {
                throw new \RuntimeException('forced planner failure');
            }
        });

        $this->postJson(route('api.verifier.chunks.complete', $chunk), [
            'output_disk' => 'local',
            'valid_key' => $validKey,
            'invalid_key' => $invalidKey,
            'risky_key' => $riskyKey,
            'email_count' => 3,
            'valid_count' => 1,
            'invalid_count' => 1,
            'risky_count' => 1,
        ])->assertStatus(503);

        $chunk->refresh();
        $this->assertSame('processing', $chunk->status);
        $this->assertNull($chunk->output_disk);
        $this->assertNull($chunk->valid_key);
        $this->assertNull($chunk->invalid_key);
        $this->assertNull($chunk->risky_key);
        $this->assertStringContainsString('valid@example.com', Storage::disk('local')->get($validKey));
        $this->assertStringContainsString('probe@example.com', Storage::disk('local')->get($riskyKey));
    }
}
