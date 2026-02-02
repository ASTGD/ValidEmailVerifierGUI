<?php

namespace App\Console\Commands;

use App\Services\Metrics\SystemMetricsService;
use Illuminate\Console\Command;

class CollectSystemMetrics extends Command
{
    protected $signature = 'metrics:system';

    protected $description = 'Collect system health metrics for the admin dashboard.';

    public function handle(SystemMetricsService $service): int
    {
        $metric = $service->capture();

        if ($metric) {
            $this->info('System metrics captured.');
        }

        return self::SUCCESS;
    }
}
