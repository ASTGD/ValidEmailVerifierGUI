<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationJobMetric;
use Illuminate\Support\Carbon;

class JobMetricsRecorder
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordPhase(VerificationJob $job, string $phase, array $attributes = []): VerificationJobMetric
    {
        $metric = VerificationJobMetric::firstOrNew([
            'verification_job_id' => $job->id,
        ]);

        if (! $metric->exists) {
            $metric->progress_percent = 0;
            $metric->processed_emails = 0;
            $metric->cache_hit_count = 0;
            $metric->cache_miss_count = 0;
            $metric->writeback_written_count = 0;
        }

        if ($metric->phase !== $phase) {
            $metric->phase = $phase;
            $metric->phase_started_at = now();
        }

        $metric->phase_updated_at = now();

        $this->applyAttributes($metric, $attributes);
        $this->updateResourceUsage($metric);

        $metric->save();

        return $metric;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyAttributes(VerificationJobMetric $metric, array $attributes): void
    {
        if (array_key_exists('progress_percent', $attributes)) {
            $metric->progress_percent = $this->clampInt((int) $attributes['progress_percent'], 0, 100);
        }

        if (array_key_exists('processed_emails', $attributes)) {
            $metric->processed_emails = max(0, (int) $attributes['processed_emails']);
        }

        if (array_key_exists('total_emails', $attributes)) {
            $metric->total_emails = $attributes['total_emails'] === null
                ? null
                : max(0, (int) $attributes['total_emails']);
        }

        if (array_key_exists('cache_hit_count', $attributes)) {
            $metric->cache_hit_count = max(0, (int) $attributes['cache_hit_count']);
        }

        if (array_key_exists('cache_miss_count', $attributes)) {
            $metric->cache_miss_count = max(0, (int) $attributes['cache_miss_count']);
        }

        if (array_key_exists('writeback_written_count', $attributes)) {
            $metric->writeback_written_count = max(0, (int) $attributes['writeback_written_count']);
        }
    }

    private function updateResourceUsage(VerificationJobMetric $metric): void
    {
        $currentPeakMemoryMb = memory_get_peak_usage(true) / 1024 / 1024;
        $metric->peak_memory_mb = $metric->peak_memory_mb === null
            ? round($currentPeakMemoryMb, 2)
            : max((float) $metric->peak_memory_mb, round($currentPeakMemoryMb, 2));

        $rusage = getrusage();
        $cpuTimeMs = $this->cpuTimeMs($rusage);
        $now = now();

        if ($metric->cpu_time_ms !== null && $metric->cpu_sampled_at instanceof Carbon) {
            $elapsedMs = max(1, $metric->cpu_sampled_at->diffInMilliseconds($now));
            $deltaMs = max(0, $cpuTimeMs - (int) $metric->cpu_time_ms);
            $cpuPercent = min(100, ($deltaMs / $elapsedMs) * 100);

            if ($metric->peak_cpu_percent === null) {
                $metric->peak_cpu_percent = round($cpuPercent, 2);
            } else {
                $metric->peak_cpu_percent = max((float) $metric->peak_cpu_percent, round($cpuPercent, 2));
            }
        }

        $metric->cpu_time_ms = $cpuTimeMs;
        $metric->cpu_sampled_at = $now;
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function cpuTimeMs(array $usage): int
    {
        $userSeconds = (int) ($usage['ru_utime.tv_sec'] ?? 0);
        $userMicros = (int) ($usage['ru_utime.tv_usec'] ?? 0);
        $sysSeconds = (int) ($usage['ru_stime.tv_sec'] ?? 0);
        $sysMicros = (int) ($usage['ru_stime.tv_usec'] ?? 0);

        return (int) round((($userSeconds + $sysSeconds) * 1000) + (($userMicros + $sysMicros) / 1000));
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return min($max, max($min, $value));
    }
}
