<?php

namespace App\Console\Commands;

use App\Services\Metrics\QueueMetricsRollupService;
use Illuminate\Console\Command;

class QueueMetricsRollupCommand extends Command
{
    protected $signature = 'ops:queue-rollup {--hours=48 : Hourly rollup window} {--days=30 : Daily rollup window}';

    protected $description = 'Aggregate queue metrics into hourly and daily rollup buckets.';

    public function handle(QueueMetricsRollupService $service): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $days = max(1, (int) $this->option('days'));

        $upserts = $service->rollup($hours, $days);

        $this->info(sprintf('Queue metric rollups upserted: %d', $upserts));

        return self::SUCCESS;
    }
}
