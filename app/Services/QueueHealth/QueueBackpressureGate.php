<?php

namespace App\Services\QueueHealth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class QueueBackpressureGate
{
    /**
     * @return array{blocked: bool, reason: string, issue: array<string, mixed>|null}
     */
    public function assessHeavySubmission(): array
    {
        if (! (bool) config('queue_slo.backpressure.enabled', true)) {
            return $this->allow('Queue backpressure is disabled.');
        }

        $reportKey = (string) config('queue_health.report_cache_key', 'queue_health:last_report');
        $report = Cache::get($reportKey);

        if (! is_array($report)) {
            return $this->allow('No queue health report found.');
        }

        $maxAgeSeconds = max(0, (int) config('queue_slo.backpressure.max_report_age_seconds', 180));
        $checkedAt = $this->parseTime($report['checked_at'] ?? null);

        if ($maxAgeSeconds > 0 && $checkedAt && $checkedAt->diffInSeconds(now()) > $maxAgeSeconds) {
            return $this->block('Queue health telemetry is stale. Heavy submissions are temporarily paused.');
        }

        $status = strtolower((string) ($report['status'] ?? 'healthy'));
        $blockStatuses = array_map('strtolower', (array) config('queue_slo.backpressure.block_on_statuses', ['critical']));

        if (! in_array($status, $blockStatuses, true)) {
            return $this->allow('Queue health is within allowed status.');
        }

        $heavyLanes = array_map('strtolower', (array) config('queue_slo.heavy_submission_lanes', []));
        $issues = collect((array) ($report['issues'] ?? []))
            ->filter(fn ($issue): bool => is_array($issue));

        $blockingIssue = $issues->first(function (array $issue) use ($heavyLanes): bool {
            $lane = strtolower((string) ($issue['lane'] ?? ''));
            $key = strtolower((string) ($issue['key'] ?? ''));

            if ($lane !== '' && in_array($lane, $heavyLanes, true)) {
                return true;
            }

            return str_contains($key, 'redis_unavailable')
                || str_contains($key, 'horizon_inactive')
                || str_contains($key, 'missing_supervisor')
                || str_contains($key, 'retry_contract');
        });

        if (! is_array($blockingIssue)) {
            return $this->allow('No blocking lane issue found.');
        }

        $title = trim((string) ($blockingIssue['title'] ?? 'Queue pressure detected'));
        $detail = trim((string) ($blockingIssue['detail'] ?? ''));
        $message = $detail !== '' ? $title.': '.$detail : $title;

        return $this->block($message, $blockingIssue);
    }

    /**
     * @return array{blocked: bool, reason: string, issue: array<string, mixed>|null}
     */
    private function allow(string $reason): array
    {
        return [
            'blocked' => false,
            'reason' => $reason,
            'issue' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $issue
     * @return array{blocked: bool, reason: string, issue: array<string, mixed>|null}
     */
    private function block(string $reason, ?array $issue = null): array
    {
        return [
            'blocked' => true,
            'reason' => $reason,
            'issue' => $issue,
        ];
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
