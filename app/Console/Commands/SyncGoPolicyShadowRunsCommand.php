<?php

namespace App\Console\Commands;

use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicyShadowRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

        if (! $dryRun) {
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
                ],
                'created_at' => now(),
            ]);
        }

        if ($dryRun) {
            $this->info(sprintf('Dry-run complete. eligible=%d', $processed));

            return self::SUCCESS;
        }

        $this->info(sprintf('Go shadow run sync complete. processed=%d upserted=%d', $processed, $upserted));

        return self::SUCCESS;
    }
}
