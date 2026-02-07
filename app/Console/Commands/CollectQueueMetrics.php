<?php

namespace App\Console\Commands;

use App\Services\Metrics\QueueMetricsService;
use Illuminate\Console\Command;

class CollectQueueMetrics extends Command
{
    protected $signature = 'metrics:queue';

    protected $description = 'Collect queue health metrics for the admin dashboard.';

    public function handle(QueueMetricsService $service): int
    {
        $captured = $service->capture();

        if ($captured > 0) {
            $this->info("Queue metrics captured for {$captured} queue lane(s).");
        }

        return self::SUCCESS;
    }
}
