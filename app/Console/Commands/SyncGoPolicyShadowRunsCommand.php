<?php

namespace App\Console\Commands;

use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicyShadowRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SyncGoPolicyShadowRunsCommand extends Command
{
    protected $signature = 'ops:go-policy-shadow-sync
        {--limit=50 : Max shadow runs to fetch from Go control-plane}
        {--dry-run : Print stats without persisting database rows}';

    protected $description = 'Sync Go control-plane shadow policy evaluations into Laravel shadow run records.';

    public function handle(): int
    {
        $baseUrl = trim((string) config('services.go_control_plane.base_url'));
        $token = trim((string) config('services.go_control_plane.token'));
        $timeout = max(1, (int) config('services.go_control_plane.timeout_seconds', 3));
        $limit = max(1, min(200, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        if ($baseUrl === '' || $token === '') {
            $this->warn('Go control-plane credentials are missing. Set GO_CONTROL_PLANE_BASE_URL and GO_CONTROL_PLANE_TOKEN to enable sync.');

            return self::SUCCESS;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($token)
            ->get(Str::finish($baseUrl, '/').'api/policies/shadow/runs', [
                'limit' => $limit,
            ]);

        if (! $response->successful()) {
            $this->error(sprintf('Go control-plane request failed (%d).', $response->status()));

            return self::FAILURE;
        }

        $rows = data_get($response->json(), 'data', []);
        if (! is_array($rows)) {
            $this->error('Unexpected shadow-runs response payload.');

            return self::FAILURE;
        }

        $processed = 0;
        $upserted = 0;
        $reviewRequiredRuns = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $runUuid = trim((string) data_get($row, 'run_uuid'));
            $candidateVersion = trim((string) data_get($row, 'candidate_version'));
            if ($runUuid === '' || $candidateVersion === '') {
                continue;
            }

            $processed++;
            if ($dryRun) {
                continue;
            }

            $providers = data_get($row, 'providers', []);
            $provider = is_array($providers) && isset($providers[0]) ? strtolower(trim((string) $providers[0])) : 'generic';
            if (! in_array($provider, ['gmail', 'microsoft', 'yahoo', 'generic'], true)) {
                $provider = 'generic';
            }

            $summary = data_get($row, 'summary', []);
            $evaluatedAt = data_get($row, 'evaluated_at');
            $status = data_get($row, 'summary.highest_risk_recommendation') === 'rollback_candidate'
                ? 'review_required'
                : 'evaluated';

            if ($status === 'review_required') {
                $reviewRequiredRuns[] = [
                    'run_uuid' => $runUuid,
                    'provider' => $provider,
                    'candidate_version' => $candidateVersion,
                    'active_version' => trim((string) data_get($row, 'active_version')) ?: null,
                    'recommendation' => (string) data_get($row, 'summary.highest_risk_recommendation', 'rollback_candidate'),
                    'evaluated_at' => $evaluatedAt ?: now()->toIso8601String(),
                ];
            }

            SmtpPolicyShadowRun::query()->updateOrCreate(
                ['run_uuid' => $runUuid],
                [
                    'candidate_version' => $candidateVersion,
                    'active_version' => trim((string) data_get($row, 'active_version')) ?: null,
                    'provider' => $provider,
                    'status' => $status,
                    'sample_size' => (int) data_get($summary, 'provider_count', 0),
                    'unknown_rate_delta' => (float) data_get($summary, 'unknown_rate_avg', 0),
                    'tempfail_recovery_delta' => (float) data_get($summary, 'tempfail_recovery_pct_avg', 0),
                    'policy_block_rate_delta' => (float) data_get($summary, 'policy_blocked_rate_avg', 0),
                    'drift_summary' => [
                        'summary' => $summary,
                        'results' => data_get($row, 'results', []),
                    ],
                    'evaluated_at' => $evaluatedAt ? Carbon::parse((string) $evaluatedAt) : now(),
                    'created_by' => trim((string) data_get($row, 'triggered_by')) ?: 'go-control-plane',
                    'notes' => trim((string) data_get($row, 'notes')) ?: null,
                ]
            );

            $upserted++;
        }

        $alertsSent = 0;
        if (! $dryRun) {
            $alertsSent = $this->notifyReviewRequiredRuns($reviewRequiredRuns);

            SmtpPolicyActionAudit::query()->create([
                'action' => 'shadow_run_sync',
                'policy_version' => null,
                'provider' => 'generic',
                'source' => 'automation',
                'actor' => 'ops:go-policy-shadow-sync',
                'result' => 'success',
                'context' => [
                    'processed' => $processed,
                    'upserted' => $upserted,
                    'limit' => $limit,
                    'review_required' => count($reviewRequiredRuns),
                    'alerts_sent' => $alertsSent,
                ],
                'created_at' => now(),
            ]);
        }

        if ($dryRun) {
            $this->info(sprintf('Dry-run complete. eligible=%d', $processed));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Go shadow run sync complete. processed=%d upserted=%d review_required=%d alerts_sent=%d',
            $processed,
            $upserted,
            count($reviewRequiredRuns),
            $alertsSent
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{run_uuid:string,provider:string,candidate_version:string,active_version:?string,recommendation:string,evaluated_at:string}>  $runs
     */
    private function notifyReviewRequiredRuns(array $runs): int
    {
        if (! (bool) config('engine.shadow_sync_alerts_enabled', true) || $runs === []) {
            return 0;
        }

        $cooldown = max(60, (int) config('engine.shadow_sync_alert_cooldown_seconds', 1800));
        $prefix = trim((string) config('engine.shadow_sync_alert_cache_prefix', 'engine:shadow-sync:alerts'));
        $cachePrefix = $prefix !== '' ? $prefix : 'engine:shadow-sync:alerts';
        $now = CarbonImmutable::now();

        $alertsSent = 0;
        foreach ($runs as $run) {
            $issueKey = sprintf(
                '%s|%s',
                strtolower(trim((string) ($run['provider'] ?? 'generic'))),
                strtolower(trim((string) ($run['candidate_version'] ?? 'unknown')))
            );

            $stateKey = $cachePrefix.':'.sha1($issueKey);
            $state = Cache::get($stateKey);
            $state = is_array($state) ? $state : [];
            $lastAlertedAt = $this->safeParseTime($state['last_alerted_at'] ?? null);

            if ($lastAlertedAt && $lastAlertedAt->diffInSeconds($now) < $cooldown) {
                continue;
            }

            $message = sprintf(
                'Go shadow policy review required. provider=%s candidate=%s active=%s recommendation=%s run_uuid=%s evaluated_at=%s',
                (string) ($run['provider'] ?? 'generic'),
                (string) ($run['candidate_version'] ?? 'unknown'),
                (string) ($run['active_version'] ?? 'n/a'),
                (string) ($run['recommendation'] ?? 'rollback_candidate'),
                (string) ($run['run_uuid'] ?? 'unknown'),
                (string) ($run['evaluated_at'] ?? $now->toIso8601String())
            );

            Log::warning('Go shadow policy run requires review', $run);
            $this->sendAlertEmail($message);
            $this->sendAlertSlack($message);

            Cache::put($stateKey, [
                'issue_key' => $issueKey,
                'last_alerted_at' => $now->toIso8601String(),
                'run_uuid' => (string) ($run['run_uuid'] ?? ''),
            ], now()->addDay());

            $alertsSent++;
        }

        return $alertsSent;
    }

    private function sendAlertEmail(string $message): void
    {
        $to = trim((string) config('engine.shadow_sync_alert_email', config('queue_health.alerts.email', '')));
        if ($to === '') {
            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($to): void {
                $mail->to($to)->subject('[Go Shadow Policy][REVIEW REQUIRED]');
            });
        } catch (Throwable $exception) {
            Log::warning('Go shadow policy review email failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendAlertSlack(string $message): void
    {
        $webhook = trim((string) config(
            'engine.shadow_sync_alert_slack_webhook_url',
            config('queue_health.alerts.slack_webhook_url', '')
        ));
        if ($webhook === '') {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'text' => '[Go Shadow Policy][REVIEW REQUIRED]'."\n".$message,
            ])->throw();
        } catch (Throwable $exception) {
            Log::warning('Go shadow policy review Slack alert failed', [
                'error' => $exception->getMessage(),
            ]);
        }
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
