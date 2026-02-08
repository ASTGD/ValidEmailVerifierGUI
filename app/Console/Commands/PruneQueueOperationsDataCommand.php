<?php

namespace App\Console\Commands;

use App\Models\QueueMetric;
use App\Models\QueueMetricsRollup;
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

        $metricsCutoff = now()->subDays($metricDays);
        $rollupsCutoff = now()->subDays($rollupDays);
        $failedCutoff = now()->subHours($failedHours);

        $metricsCount = QueueMetric::query()->where('captured_at', '<', $metricsCutoff)->count();
        $rollupsCount = QueueMetricsRollup::query()->where('period_start', '<', $rollupsCutoff)->count();
        $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');
        $failedJobsCount = (int) DB::table($failedJobsTable)->where('failed_at', '<', $failedCutoff)->count();

        if ($dryRun) {
            $this->line(sprintf(
                'Dry run: queue_metrics=%d, queue_metrics_rollups=%d, failed_jobs=%d',
                $metricsCount,
                $rollupsCount,
                $failedJobsCount
            ));

            return self::SUCCESS;
        }

        $deletedMetrics = QueueMetric::query()->where('captured_at', '<', $metricsCutoff)->delete();
        $deletedRollups = QueueMetricsRollup::query()->where('period_start', '<', $rollupsCutoff)->delete();
        $prunedFailed = $this->pruneFailedJobs($failedHours);

        $this->info(sprintf(
            'Pruned queue_metrics=%d, queue_metrics_rollups=%d, failed_jobs=%d',
            $deletedMetrics,
            $deletedRollups,
            $prunedFailed
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
