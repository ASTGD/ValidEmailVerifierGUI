<?php

namespace App\Console\Commands;

use App\Models\QueueMetric;
use App\Models\SmtpDecisionTrace;
use App\Models\SmtpPolicyShadowRun;
use App\Models\VerificationJobMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportGoProbeWeeklyReportCommand extends Command
{
    protected $signature = 'ops:go-probe-weekly-report {--json : Output report as JSON only}';

    protected $description = 'Export a weekly PM/Ops report for probe quality and queue impact.';

    public function handle(): int
    {
        $now = now()->utc();
        $since = $now->copy()->subDays(7);

        $probeTotals = VerificationJobMetric::query()
            ->where('phase_updated_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(probe_completed_count), 0) as probe_completed_total')
            ->selectRaw('COALESCE(SUM(probe_unknown_count), 0) as probe_unknown_total')
            ->first();

        $probeCompleted = (int) ($probeTotals?->probe_completed_total ?? 0);
        $probeUnknown = (int) ($probeTotals?->probe_unknown_total ?? 0);
        $probeUnknownRate = $probeCompleted > 0 ? $probeUnknown / $probeCompleted : 0.0;
        $finalizeQueue = (string) config('queue.connections.redis_finalize.queue', 'finalize');
        $smtpProbeQueue = (string) config('queue.connections.redis_smtp_probe.queue', 'smtp_probe');
        $queueNames = array_values(array_unique(array_filter([$finalizeQueue, $smtpProbeQueue])));

        $queueAggregate = QueueMetric::query()
            ->where('captured_at', '>=', $since)
            ->whereIn('queue', $queueNames)
            ->selectRaw('queue')
            ->selectRaw('COALESCE(AVG(oldest_age_seconds), 0) as avg_oldest_age_seconds')
            ->selectRaw('COALESCE(MAX(oldest_age_seconds), 0) as max_oldest_age_seconds')
            ->selectRaw('COALESCE(AVG(depth), 0) as avg_depth')
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $report = [
            'generated_at' => $now->toIso8601String(),
            'window' => [
                'from' => $since->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'probe' => [
                'completed_total' => $probeCompleted,
                'unknown_total' => $probeUnknown,
                'unknown_rate' => round($probeUnknownRate, 6),
            ],
            'queues' => [
                'finalize' => [
                    'avg_oldest_age_seconds' => round((float) ($queueAggregate[$finalizeQueue]->avg_oldest_age_seconds ?? 0), 2),
                    'max_oldest_age_seconds' => (int) ($queueAggregate[$finalizeQueue]->max_oldest_age_seconds ?? 0),
                    'avg_depth' => round((float) ($queueAggregate[$finalizeQueue]->avg_depth ?? 0), 2),
                ],
                'smtp_probe' => [
                    'avg_oldest_age_seconds' => round((float) ($queueAggregate[$smtpProbeQueue]->avg_oldest_age_seconds ?? 0), 2),
                    'max_oldest_age_seconds' => (int) ($queueAggregate[$smtpProbeQueue]->max_oldest_age_seconds ?? 0),
                    'avg_depth' => round((float) ($queueAggregate[$smtpProbeQueue]->avg_depth ?? 0), 2),
                ],
            ],
            'shadow_policy' => [
                'latest_candidate_version' => null,
                'latest_evaluated_at' => null,
                'latest_status' => null,
            ],
            'unknown_reasons_top' => [],
        ];

        $latestShadowRun = SmtpPolicyShadowRun::query()
            ->where('evaluated_at', '>=', $since)
            ->latest('evaluated_at')
            ->first();
        if ($latestShadowRun) {
            $report['shadow_policy'] = [
                'latest_candidate_version' => $latestShadowRun->candidate_version,
                'latest_evaluated_at' => optional($latestShadowRun->evaluated_at)?->toIso8601String(),
                'latest_status' => $latestShadowRun->status,
            ];
        }

        $unknownReasons = SmtpDecisionTrace::query()
            ->where('observed_at', '>=', $since)
            ->whereNotNull('reason_tag')
            ->selectRaw('reason_tag, COUNT(*) as aggregate_count')
            ->groupBy('reason_tag')
            ->orderByDesc('aggregate_count')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'reason_tag' => (string) $row->reason_tag,
                'count' => (int) $row->aggregate_count,
            ])
            ->values()
            ->all();
        $report['unknown_reasons_top'] = $unknownReasons;

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $disk = (string) config('queue_slo.weekly_report.disk', config('filesystems.default', 'local'));
        $prefix = trim((string) config('queue_slo.weekly_report.prefix', 'reports/ops'), '/');
        $path = $prefix.'/go-probe-weekly-'.$now->format('Ymd-His').'.json';

        Storage::disk($disk)->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info(sprintf('Weekly probe report exported to %s:%s', $disk, $path));

        return self::SUCCESS;
    }
}
