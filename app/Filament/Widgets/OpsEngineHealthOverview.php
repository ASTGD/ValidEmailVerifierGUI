<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
use App\Models\EngineServer;
use App\Models\VerificationJob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsEngineHealthOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $thresholdMinutes = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));

        $activeEngines = EngineServer::query()
            ->where('is_active', true)
            ->count();

        $onlineEngines = EngineServer::query()
            ->where('is_active', true)
            ->where('last_heartbeat_at', '>=', now()->subMinutes($thresholdMinutes))
            ->count();

        $offlineEngines = max(0, $activeEngines - $onlineEngines);
        $draining = EngineServer::query()->where('drain_mode', true)->count();

        $activeJobs = VerificationJob::query()
            ->where('status', VerificationJobStatus::Processing)
            ->count();

        return [
            Stat::make('Engines online', (string) $onlineEngines)
                ->description("{$activeEngines} total")
                ->color($onlineEngines > 0 ? 'success' : 'danger'),
            Stat::make('Engines offline', (string) $offlineEngines)
                ->color($offlineEngines > 0 ? 'danger' : 'success'),
            Stat::make('Draining', (string) $draining)
                ->color($draining > 0 ? 'warning' : 'gray'),
            Stat::make('Active jobs', (string) $activeJobs)
                ->color($activeJobs > 0 ? 'info' : 'gray'),
        ];
    }
}
