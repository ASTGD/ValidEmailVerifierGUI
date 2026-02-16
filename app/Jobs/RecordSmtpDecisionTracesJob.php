<?php

namespace App\Jobs;

use App\Models\VerificationJobChunk;
use App\Services\SmtpDecisionTracing\SmtpDecisionTraceRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RecordSmtpDecisionTracesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public string $chunkId)
    {
        $this->connection = 'redis_imports';
        $this->queue = (string) config('queue.connections.redis_imports.queue', 'imports');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("smtp-decision-traces:{$this->chunkId}"))
                ->expireAfter($this->timeout + 120)
                ->releaseAfter(30),
        ];
    }

    public function handle(SmtpDecisionTraceRecorder $recorder): void
    {
        $chunk = VerificationJobChunk::query()->find($this->chunkId);
        if (! $chunk) {
            return;
        }

        $inserted = $recorder->recordFromChunk($chunk);

        if ($inserted > 0 && $chunk->job) {
            $chunk->job->addLog('smtp_decision_traces_recorded', 'SMTP decision traces captured.', [
                'chunk_id' => (string) $chunk->id,
                'trace_count' => $inserted,
            ]);
        }
    }
}
