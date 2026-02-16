<?php

namespace App\Console\Commands;

use App\Models\QueueMetric;
use App\Models\QueueMetricsRollup;
use App\Models\SmtpDecisionTrace;
use App\Models\SmtpPolicyShadowRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PruneQueueOperationsDataCommand extends Command
{
    protected $signature = 'ops:queue-prune {--dry-run : Show records that would be removed without deleting}';

    protected $description = 'Prune queue metrics, queue rollups, and failed jobs using SLO retention settings.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $metricDays = max(1, (int) config('queue_slo.rollups.queue_metrics_retention_days', 14));
        $rollupDays = max(1, (int) config('queue_slo.rollups.queue_metrics_rollup_retention_days', 90));
        $failedHours = max(1, (int) config('queue_slo.rollups.failed_jobs_retention_hours', 168));
        $traceDays = max(1, (int) config('engine.smtp_decision_trace_retention_days', 30));
        $shadowRunDays = max(1, (int) config('engine.smtp_policy_shadow_run_retention_days', 60));

        $metricsCutoff = now()->subDays($metricDays);
        $rollupsCutoff = now()->subDays($rollupDays);
        $failedCutoff = now()->subHours($failedHours);
        $traceCutoff = now()->subDays($traceDays);
        $shadowRunsCutoff = now()->subDays($shadowRunDays);

        $metricsCount = QueueMetric::query()->where('captured_at', '<', $metricsCutoff)->count();
        $rollupsCount = QueueMetricsRollup::query()->where('period_start', '<', $rollupsCutoff)->count();
        $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');
        $failedJobsCount = (int) DB::table($failedJobsTable)->where('failed_at', '<', $failedCutoff)->count();
        $traceCount = SmtpDecisionTrace::query()
            ->where(function ($query) use ($traceCutoff): void {
                $query
                    ->where('observed_at', '<', $traceCutoff)
                    ->orWhere(function ($fallback) use ($traceCutoff): void {
                        $fallback->whereNull('observed_at')->where('created_at', '<', $traceCutoff);
                    });
            })
            ->count();
        $shadowRunsCount = SmtpPolicyShadowRun::query()
            ->where(function ($query) use ($shadowRunsCutoff): void {
                $query
                    ->where('evaluated_at', '<', $shadowRunsCutoff)
                    ->orWhere(function ($fallback) use ($shadowRunsCutoff): void {
                        $fallback->whereNull('evaluated_at')->where('created_at', '<', $shadowRunsCutoff);
                    });
            })
            ->count();

        if ($dryRun) {
            $this->line(sprintf(
                'Dry run: queue_metrics=%d, queue_metrics_rollups=%d, failed_jobs=%d, smtp_decision_traces=%d, smtp_policy_shadow_runs=%d',
                $metricsCount,
                $rollupsCount,
                $failedJobsCount,
                $traceCount,
                $shadowRunsCount
            ));

            return self::SUCCESS;
        }

        $deletedMetrics = QueueMetric::query()->where('captured_at', '<', $metricsCutoff)->delete();
        $deletedRollups = QueueMetricsRollup::query()->where('period_start', '<', $rollupsCutoff)->delete();
        $prunedFailed = $this->pruneFailedJobs($failedHours);
        $deletedTraceRows = SmtpDecisionTrace::query()
            ->where(function ($query) use ($traceCutoff): void {
                $query
                    ->where('observed_at', '<', $traceCutoff)
                    ->orWhere(function ($fallback) use ($traceCutoff): void {
                        $fallback->whereNull('observed_at')->where('created_at', '<', $traceCutoff);
                    });
            })
            ->delete();
        $deletedShadowRuns = SmtpPolicyShadowRun::query()
            ->where(function ($query) use ($shadowRunsCutoff): void {
                $query
                    ->where('evaluated_at', '<', $shadowRunsCutoff)
                    ->orWhere(function ($fallback) use ($shadowRunsCutoff): void {
                        $fallback->whereNull('evaluated_at')->where('created_at', '<', $shadowRunsCutoff);
                    });
            })
            ->delete();

        $this->info(sprintf(
            'Pruned queue_metrics=%d, queue_metrics_rollups=%d, failed_jobs=%d, smtp_decision_traces=%d, smtp_policy_shadow_runs=%d',
            $deletedMetrics,
            $deletedRollups,
            $prunedFailed,
            $deletedTraceRows,
            $deletedShadowRuns
        ));

        return self::SUCCESS;
    }

    private function pruneFailedJobs(int $hours): int
    {
        $driver = (string) config('queue.failed.driver', 'database-uuids');
        $table = (string) config('queue.failed.table', 'failed_jobs');

        if (! in_array($driver, ['database', 'database-uuids'], true)) {
            return 0;
        }

        $cutoff = now()->subHours($hours);
        $before = (int) DB::table($table)->where('failed_at', '<', $cutoff)->count();

        Artisan::call('queue:prune-failed', [
            '--hours' => $hours,
        ]);

        $after = (int) DB::table($table)->where('failed_at', '<', $cutoff)->count();

        return max(0, $before - $after);
    }
}
