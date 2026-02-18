<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\SmtpDecisionTrace;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\SmtpDecisionTracing\SmtpDecisionTraceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SmtpDecisionTraceRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_parses_smtp_probe_outputs_and_upserts_traces(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $job = VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $chunk = VerificationJobChunk::query()->create([
            'verification_job_id' => (string) $job->id,
            'chunk_no' => 1,
            'status' => 'completed',
            'processing_stage' => 'smtp_probe',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.csv',
            'output_disk' => 'local',
            'valid_key' => 'results/'.$job->id.'/chunk1-valid.csv',
            'invalid_key' => 'results/'.$job->id.'/chunk1-invalid.csv',
            'risky_key' => 'results/'.$job->id.'/chunk1-risky.csv',
            'routing_provider' => 'gmail',
            'preferred_pool' => 'reputation-a',
            'last_worker_ids' => ['worker-a'],
        ]);

        Storage::disk('local')->put($chunk->invalid_key, implode("\n", [
            'email,reason',
            'invalid@example.com,smtp_rejected:decision=undeliverable;confidence=high;tag=mailbox_not_found;provider=gmail;policy=v3.0.0;rule=invalid-hard;smtp=550;enhanced=5.1.1;strategy=none;attempt=1;route=mx:mx.google.test',
        ]));

        Storage::disk('local')->put($chunk->risky_key, implode("\n", [
            'email,reason',
            'risky@example.com,smtp_tempfail:decision=retryable;confidence=medium;tag=greylist;provider=gmail;policy=v3.0.0;rule=tempfail-greylist;smtp=451;enhanced=4.7.1;strategy=tempfail;attempt=2;route=mx:mx.google.test;mx=mx.google.test',
        ]));

        $recorder = app(SmtpDecisionTraceRecorder::class);
        $count = $recorder->recordFromChunk($chunk);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('smtp_decision_traces', 2);

        $unknownTrace = SmtpDecisionTrace::query()
            ->where('verification_job_chunk_id', (string) $chunk->id)
            ->where('email_hash', hash('sha256', 'risky@example.com'))
            ->first();

        $this->assertNotNull($unknownTrace);
        $this->assertSame('unknown', $unknownTrace->decision_class);
        $this->assertSame('greylist', $unknownTrace->reason_tag);
        $this->assertSame('tempfail', $unknownTrace->retry_strategy);
        $this->assertSame('gmail', $unknownTrace->provider);
        $this->assertSame('v3.0.0', $unknownTrace->policy_version);

        $invalidTrace = SmtpDecisionTrace::query()
            ->where('verification_job_chunk_id', (string) $chunk->id)
            ->where('email_hash', hash('sha256', 'invalid@example.com'))
            ->first();

        $this->assertNotNull($invalidTrace);
        $this->assertSame('undeliverable', $invalidTrace->decision_class);
        $this->assertSame('mailbox_not_found', $invalidTrace->reason_tag);
        $this->assertSame('none', $invalidTrace->retry_strategy);
    }

    public function test_recorder_handles_large_probe_outputs_with_batch_upserts_and_idempotency(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $job = VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $chunk = VerificationJobChunk::query()->create([
            'verification_job_id' => (string) $job->id,
            'chunk_no' => 2,
            'status' => 'completed',
            'processing_stage' => 'smtp_probe',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/2/input.csv',
            'output_disk' => 'local',
            'risky_key' => 'results/'.$job->id.'/chunk2-risky.csv',
            'routing_provider' => 'gmail',
            'preferred_pool' => 'reputation-b',
            'last_worker_ids' => ['worker-b'],
        ]);

        $totalRows = 1105;
        $lines = ['email,reason'];
        for ($i = 1; $i <= $totalRows; $i++) {
            $lines[] = sprintf(
                'batch%d@example.com,smtp_tempfail:decision=retryable;confidence=medium;tag=greylist;provider=gmail;policy=v3.1.0;rule=tempfail-greylist;smtp=451;enhanced=4.7.1;strategy=tempfail;attempt=2;route=mx:mx.google.test;mx=mx.google.test',
                $i
            );
        }

        Storage::disk('local')->put((string) $chunk->risky_key, implode("\n", $lines));

        $recorder = app(SmtpDecisionTraceRecorder::class);
        $firstPass = $recorder->recordFromChunk($chunk);
        $secondPass = $recorder->recordFromChunk($chunk);

        $this->assertSame($totalRows, $firstPass);
        $this->assertSame($totalRows, $secondPass);
        $this->assertSame($totalRows, SmtpDecisionTrace::query()->count());
    }

    public function test_recorder_persists_attempt_chain_from_reason_metadata(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $job = VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $chunk = VerificationJobChunk::query()->create([
            'verification_job_id' => (string) $job->id,
            'chunk_no' => 3,
            'status' => 'completed',
            'processing_stage' => 'smtp_probe',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/3/input.csv',
            'output_disk' => 'local',
            'risky_key' => 'results/'.$job->id.'/chunk3-risky.csv',
            'routing_provider' => 'gmail',
            'preferred_pool' => 'reputation-c',
            'last_worker_ids' => ['worker-c'],
        ]);

        $attemptChain = [
            [
                'attempt_number' => 1,
                'mx_host' => 'mx1.gmail.test',
                'attempt_route' => 'mx:mx1.gmail.test',
                'decision_class' => 'retryable',
                'reason_code' => 'smtp_tempfail',
                'reason_tag' => 'provider_tempfail_unresolved',
                'retry_strategy' => 'tempfail',
                'smtp_code' => 451,
                'enhanced_code' => '4.7.1',
                'provider_profile' => 'gmail',
                'confidence_hint' => 'medium',
                'evidence_strength' => 'medium',
            ],
            [
                'attempt_number' => 2,
                'mx_host' => 'mx2.gmail.test',
                'attempt_route' => 'mx:mx2.gmail.test',
                'decision_class' => 'unknown',
                'reason_code' => 'smtp_timeout',
                'reason_tag' => 'connection_unstable',
                'retry_strategy' => 'unknown',
                'smtp_code' => 0,
                'enhanced_code' => '',
                'provider_profile' => 'gmail',
                'confidence_hint' => 'low',
                'evidence_strength' => 'low',
            ],
        ];
        $encodedAttemptChain = rtrim(strtr(base64_encode((string) json_encode($attemptChain, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        Storage::disk('local')->put($chunk->risky_key, implode("\n", [
            'email,reason',
            sprintf(
                'chain@example.com,smtp_timeout:decision=unknown;confidence=low;tag=connection_unstable;provider=gmail;policy=v3.2.0;rule=tempfail-chain;strategy=unknown;attempt=2;route=mx:mx2.gmail.test;mx=mx2.gmail.test;attempt_chain=%s',
                $encodedAttemptChain
            ),
        ]));

        $recorder = app(SmtpDecisionTraceRecorder::class);
        $count = $recorder->recordFromChunk($chunk);

        $this->assertSame(1, $count);

        $trace = SmtpDecisionTrace::query()
            ->where('verification_job_chunk_id', (string) $chunk->id)
            ->where('email_hash', hash('sha256', 'chain@example.com'))
            ->first();

        $this->assertNotNull($trace);
        $tracePayload = is_array($trace->trace_payload) ? $trace->trace_payload : [];
        $this->assertCount(2, $tracePayload['attempt_chain'] ?? []);
        $this->assertSame('mx1.gmail.test', data_get($tracePayload, 'attempt_chain.0.mx_host'));
        $this->assertSame('reputation-c', data_get($tracePayload, 'attempt_chain.0.pool'));
        $this->assertSame('worker-c', data_get($tracePayload, 'attempt_chain.1.worker_id'));
        $this->assertSame('gmail', data_get($tracePayload, 'attempt_chain.1.provider'));

        $attemptRoute = is_array($trace->attempt_route) ? $trace->attempt_route : [];
        $this->assertSame('mx:mx2.gmail.test', $attemptRoute['route'] ?? null);
        $this->assertSame('mx2.gmail.test', $attemptRoute['mx_host'] ?? null);
        $this->assertSame('reputation-c', $attemptRoute['pool'] ?? null);
        $this->assertSame('gmail', $attemptRoute['provider'] ?? null);
    }
}
