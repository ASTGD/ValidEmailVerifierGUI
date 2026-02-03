<?php

namespace App\Services;

use App\Models\EngineServer;
use App\Models\EngineServerReputationSample;
use App\Support\EngineSettings;

class EngineServerWarmupSummaryService
{
    /**
     * @return array{healthy: int, warming: int, critical: int, total_servers: int, overall_rate: float, overall_tempfail: int, overall_total: int, window_hours: int, min_samples: int, warn_rate: float, critical_rate: float}
     */
    public function summary(): array
    {
        $windowHours = EngineSettings::reputationWindowHours();
        $minSamples = EngineSettings::reputationMinSamples();
        $warnRate = EngineSettings::reputationTempfailWarnRate();
        $criticalRate = EngineSettings::reputationTempfailCriticalRate();

        $since = now()->subHours($windowHours);

        $samples = EngineServerReputationSample::query()
            ->where('recorded_at', '>=', $since)
            ->selectRaw('engine_server_id, SUM(total_count) as total_count, SUM(tempfail_count) as tempfail_count')
            ->groupBy('engine_server_id')
            ->get();

        $totalServers = EngineServer::query()->count();
        $covered = $samples->count();

        $healthy = 0;
        $warming = 0;
        $critical = 0;

        foreach ($samples as $sample) {
            $total = (int) ($sample->total_count ?? 0);
            $tempfail = (int) ($sample->tempfail_count ?? 0);
            $status = $this->classifyStatus($total, $tempfail, $minSamples, $warnRate, $criticalRate);

            if ($status === 'healthy') {
                $healthy++;
            } elseif ($status === 'critical') {
                $critical++;
            } else {
                $warming++;
            }
        }

        $warming += max(0, $totalServers - $covered);

        $overallTotal = (int) $samples->sum('total_count');
        $overallTempfail = (int) $samples->sum('tempfail_count');
        $overallRate = $overallTotal > 0 ? $overallTempfail / $overallTotal : 0.0;

        return [
            'healthy' => $healthy,
            'warming' => $warming,
            'critical' => $critical,
            'total_servers' => $totalServers,
            'overall_rate' => $overallRate,
            'overall_tempfail' => $overallTempfail,
            'overall_total' => $overallTotal,
            'window_hours' => $windowHours,
            'min_samples' => $minSamples,
            'warn_rate' => $warnRate,
            'critical_rate' => $criticalRate,
        ];
    }

    private function classifyStatus(int $total, int $tempfail, int $minSamples, float $warnRate, float $criticalRate): string
    {
        if ($total < $minSamples) {
            return 'warming';
        }

        $rate = $total > 0 ? $tempfail / $total : 0.0;

        if ($rate >= $criticalRate) {
            return 'critical';
        }

        if ($rate >= $warnRate) {
            return 'warming';
        }

        return 'healthy';
    }
}
