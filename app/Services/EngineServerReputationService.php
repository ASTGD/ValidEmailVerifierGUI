<?php

namespace App\Services;

use App\Models\EngineServer;
use App\Models\EngineServerReputationSample;
use App\Support\EngineSettings;

class EngineServerReputationService
{
    /**
     * @return array{status: string, status_color: string, tempfail_rate: float, tempfail_count: int, total_count: int, window_hours: int, min_samples: int}
     */
    public function summaryFor(EngineServer $server): array
    {
        $windowHours = EngineSettings::reputationWindowHours();
        $minSamples = EngineSettings::reputationMinSamples();
        $warnRate = EngineSettings::reputationTempfailWarnRate();
        $criticalRate = EngineSettings::reputationTempfailCriticalRate();

        $since = now()->subHours($windowHours);

        $stats = EngineServerReputationSample::query()
            ->where('engine_server_id', $server->id)
            ->where('recorded_at', '>=', $since)
            ->selectRaw('SUM(total_count) as total_count, SUM(tempfail_count) as tempfail_count')
            ->first();

        $total = (int) ($stats->total_count ?? 0);
        $tempfail = (int) ($stats->tempfail_count ?? 0);
        $rate = $total > 0 ? $tempfail / $total : 0.0;

        $status = 'warming';
        $color = 'warning';

        if ($total >= $minSamples) {
            if ($rate >= $criticalRate) {
                $status = 'critical';
                $color = 'danger';
            } elseif ($rate >= $warnRate) {
                $status = 'warming';
                $color = 'warning';
            } else {
                $status = 'healthy';
                $color = 'success';
            }
        }

        return [
            'status' => $status,
            'status_color' => $color,
            'tempfail_rate' => $rate,
            'tempfail_count' => $tempfail,
            'total_count' => $total,
            'window_hours' => $windowHours,
            'min_samples' => $minSamples,
        ];
    }
}
