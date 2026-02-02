<?php

namespace App\Filament\Widgets;

use App\Models\SystemMetric;
use App\Support\EngineSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsSystemHealthOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $source = EngineSettings::metricsSource();
        $metric = SystemMetric::query()
            ->where('source', $source)
            ->latest('captured_at')
            ->first();

        $cpuPercent = $metric?->cpu_percent;
        $cpuValue = $cpuPercent !== null ? sprintf('%.1f%%', $cpuPercent) : 'N/A';
        $cpuColor = $this->thresholdColor($cpuPercent, 60, 80);

        $memTotal = $metric?->mem_total_mb ?? 0;
        $memUsed = $metric?->mem_used_mb ?? 0;
        $memPercent = $memTotal > 0 ? ($memUsed / $memTotal) * 100 : null;
        $memValue = $memTotal > 0 ? sprintf('%d / %d MB', $memUsed, $memTotal) : 'N/A';
        $memColor = $this->thresholdColor($memPercent, 70, 85);

        $diskTotal = $metric?->disk_total_gb ?? 0;
        $diskUsed = $metric?->disk_used_gb ?? 0;
        $diskPercent = $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : null;
        $diskValue = $diskTotal > 0 ? sprintf('%d / %d GB', $diskUsed, $diskTotal) : 'N/A';
        $diskColor = $this->thresholdColor($diskPercent, 75, 90);

        $ioRead = $metric?->io_read_mb;
        $ioWrite = $metric?->io_write_mb;
        $ioValue = ($ioRead !== null || $ioWrite !== null)
            ? sprintf('%s / %s MB', $ioRead ?? '-', $ioWrite ?? '-')
            : 'N/A';

        return [
            Stat::make('CPU', $cpuValue)
                ->description(ucfirst($source).' source')
                ->color($cpuColor),
            Stat::make('RAM', $memValue)
                ->description($memPercent !== null ? sprintf('%.1f%% used', $memPercent) : 'No data')
                ->color($memColor),
            Stat::make('Disk', $diskValue)
                ->description($diskPercent !== null ? sprintf('%.1f%% used', $diskPercent) : 'No data')
                ->color($diskColor),
            Stat::make('I/O (R/W)', $ioValue)
                ->description('Last interval')
                ->color('gray'),
        ];
    }

    private function thresholdColor(?float $value, float $warning, float $danger): string
    {
        if ($value === null) {
            return 'gray';
        }

        if ($value >= $danger) {
            return 'danger';
        }

        if ($value >= $warning) {
            return 'warning';
        }

        return 'success';
    }
}
