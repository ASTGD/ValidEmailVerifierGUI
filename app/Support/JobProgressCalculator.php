<?php

namespace App\Support;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;

class JobProgressCalculator
{
    public static function progressPercent(VerificationJob $job): int
    {
        if ($job->status === VerificationJobStatus::Completed || $job->status === VerificationJobStatus::Failed) {
            return 100;
        }

        $metrics = $job->metrics;
        $totalChunks = (int) ($job->chunks_count ?? 0);
        $completedChunks = (int) ($job->chunks_completed_count ?? 0);

        if ($totalChunks > 0) {
            $ratio = $completedChunks / max(1, $totalChunks);

            return (int) round(10 + (80 * $ratio));
        }

        if (! $job->prepared_at) {
            $processed = (int) ($metrics?->processed_emails ?? 0);
            $estimate = max(1, (int) config('engine.max_emails_per_upload', 100000));
            $ratio = min(1, $processed / $estimate);

            return (int) round(10 + (40 * $ratio));
        }

        if (! $job->finished_at) {
            $written = (int) ($metrics?->writeback_written_count ?? 0);
            $total = (int) ($metrics?->cache_miss_count ?? 0);

            if ($total > 0 && $written > 0) {
                $ratio = min(1, $written / $total);

                return (int) round(90 + (10 * $ratio));
            }

            return 75;
        }

        return 100;
    }

    public static function phaseLabel(VerificationJob $job): string
    {
        $metrics = $job->metrics;
        if ($metrics && $metrics->phase) {
            return str_replace('_', ' ', $metrics->phase);
        }

        if ($job->status === VerificationJobStatus::Completed) {
            return 'completed';
        }

        if ($job->status === VerificationJobStatus::Failed) {
            return 'failed';
        }

        if (! $job->prepared_at) {
            return 'parse_cache';
        }

        if (! $job->finished_at) {
            return 'finalize';
        }

        return 'processing';
    }
}
