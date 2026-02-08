<?php

namespace App\Services\QueueHealth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QueueHealthNotifier
{
    /**
     * @param  array{status: string, checked_at: string, issues: array<int, array{key: string, severity: string, title: string, detail: string, lane: string|null}>}  $report
     */
    public function notify(array $report): void
    {
        if (! (bool) config('queue_health.alerts.enabled', true)) {
            return;
        }

        $issues = collect((array) ($report['issues'] ?? []))
            ->filter(fn ($issue): bool => is_array($issue) && filled($issue['key'] ?? null))
            ->values();

        $currentIssues = $issues->mapWithKeys(fn (array $issue): array => [(string) $issue['key'] => $issue])->all();
        $activeKeys = $this->activeIssueKeys();
        $cooldown = max(0, (int) config('queue_health.alerts.cooldown_seconds', 600));
        $nextActiveKeys = [];
        $now = CarbonImmutable::now();

        foreach ($currentIssues as $issueKey => $issue) {
            $stateKey = $this->issueStateKey($issueKey);
            $state = Cache::get($stateKey);
            $state = is_array($state) ? $state : [];

            $isNew = $state === [];
            $lastAlertedAt = $this->safeParseTime($state['last_alerted_at'] ?? null);
            $elapsedSeconds = $lastAlertedAt ? $lastAlertedAt->diffInSeconds($now) : null;

            $shouldAlert = $isNew || $elapsedSeconds === null || $elapsedSeconds >= $cooldown;
            $eventType = $isNew ? 'new' : 'reminder';

            if ($shouldAlert) {
                $this->sendIncidentAlert($issue, $report, $eventType);
            }

            $nextState = [
                'key' => $issueKey,
                'severity' => (string) ($issue['severity'] ?? 'warning'),
                'title' => (string) ($issue['title'] ?? 'Queue incident'),
                'detail' => (string) ($issue['detail'] ?? ''),
                'lane' => $issue['lane'] ?? null,
                'first_seen_at' => (string) ($state['first_seen_at'] ?? $now->toIso8601String()),
                'last_seen_at' => $now->toIso8601String(),
                'last_alerted_at' => $shouldAlert
                    ? $now->toIso8601String()
                    : (string) ($state['last_alerted_at'] ?? ''),
            ];

            Cache::put($stateKey, $nextState, now()->addDay());
            $nextActiveKeys[] = $issueKey;
        }

        $resolvedKeys = array_values(array_diff($activeKeys, $nextActiveKeys));

        foreach ($resolvedKeys as $resolvedKey) {
            $stateKey = $this->issueStateKey($resolvedKey);
            $state = Cache::get($stateKey);
            $state = is_array($state) ? $state : ['key' => $resolvedKey];

            $this->sendRecoveryAlert($resolvedKey, $state, $report);
            Cache::forget($stateKey);
        }

        Cache::put($this->activeSetKey(), array_values(array_unique($nextActiveKeys)), now()->addDay());
    }

    /**
     * @param  array{key: string, severity: string, title: string, detail: string, lane: string|null}  $issue
     * @param  array{status: string, checked_at: string}  $report
     */
    private function sendIncidentAlert(array $issue, array $report, string $eventType): void
    {
        $severity = strtoupper((string) ($issue['severity'] ?? 'warning'));
        $title = (string) ($issue['title'] ?? 'Queue incident');
        $subject = sprintf('[Queue Health][%s] %s', $severity, $title);

        $message = $this->formatIncidentMessage($issue, $report, $eventType);

        Log::warning('Queue health incident', [
            'event' => $eventType,
            'issue' => $issue,
            'status' => $report['status'] ?? null,
            'checked_at' => $report['checked_at'] ?? null,
        ]);

        $this->sendEmail($subject, $message);
        $this->sendSlack($subject, $message);
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array{status: string, checked_at: string}  $report
     */
    private function sendRecoveryAlert(string $issueKey, array $state, array $report): void
    {
        $title = (string) ($state['title'] ?? $issueKey);
        $subject = sprintf('[Queue Health][RECOVERED] %s', $title);

        $message = implode("\n", [
            'Queue health incident resolved.',
            'Issue key: '.$issueKey,
            'Last known detail: '.(string) ($state['detail'] ?? '-'),
            'Recovered at: '.(string) ($report['checked_at'] ?? now()->toIso8601String()),
        ]);

        Log::info('Queue health recovery', [
            'issue_key' => $issueKey,
            'state' => $state,
            'status' => $report['status'] ?? null,
            'checked_at' => $report['checked_at'] ?? null,
        ]);

        $this->sendEmail($subject, $message);
        $this->sendSlack($subject, $message);
    }

    /**
     * @param  array{key: string, severity: string, title: string, detail: string, lane: string|null}  $issue
     * @param  array{status: string, checked_at: string}  $report
     */
    private function formatIncidentMessage(array $issue, array $report, string $eventType): string
    {
        return implode("\n", [
            'Queue health incident detected.',
            'Event: '.strtoupper($eventType),
            'Severity: '.strtoupper((string) ($issue['severity'] ?? 'warning')),
            'Issue key: '.(string) ($issue['key'] ?? '-'),
            'Title: '.(string) ($issue['title'] ?? '-'),
            'Lane: '.(string) ($issue['lane'] ?? '-'),
            'Detail: '.(string) ($issue['detail'] ?? '-'),
            'Overall status: '.strtoupper((string) ($report['status'] ?? 'unknown')),
            'Checked at: '.(string) ($report['checked_at'] ?? now()->toIso8601String()),
        ]);
    }

    private function sendEmail(string $subject, string $message): void
    {
        $to = trim((string) config('queue_health.alerts.email', ''));

        if ($to === '') {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($to, $subject): void {
                $mail->to($to)->subject($subject);
            });
        } catch (Throwable $exception) {
            Log::warning('Queue health email notification failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendSlack(string $subject, string $message): void
    {
        $webhook = trim((string) config('queue_health.alerts.slack_webhook_url', ''));

        if ($webhook === '') {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'text' => $subject."\n".$message,
            ])->throw();
        } catch (Throwable $exception) {
            Log::warning('Queue health Slack notification failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function activeIssueKeys(): array
    {
        $keys = Cache::get($this->activeSetKey(), []);

        if (! is_array($keys)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($key): string => trim((string) $key), $keys)));
    }

    private function activeSetKey(): string
    {
        return $this->cachePrefix().':active';
    }

    private function issueStateKey(string $issueKey): string
    {
        return $this->cachePrefix().':issue:'.sha1($issueKey);
    }

    private function cachePrefix(): string
    {
        $prefix = trim((string) config('queue_health.alerts.cache_prefix', 'queue_health:alerts'));

        return $prefix !== '' ? $prefix : 'queue_health:alerts';
    }

    private function safeParseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
