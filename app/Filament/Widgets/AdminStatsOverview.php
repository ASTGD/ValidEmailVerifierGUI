<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
use App\Models\EngineServer;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $jobsToday = VerificationJob::query()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $queueCount = VerificationJob::query()
            ->whereIn('status', [VerificationJobStatus::Pending, VerificationJobStatus::Processing])
            ->count();

        $failedLastDay = VerificationJob::query()
            ->where('status', VerificationJobStatus::Failed)
            ->excludeAdminFailures()
            ->where('finished_at', '>=', now()->subDay())
            ->count();

        $activeServers = EngineServer::query()
            ->where('is_active', true)
            ->count();

        $heartbeatThreshold = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));
        $onlineServers = EngineServer::query()
            ->where('is_active', true)
            ->where('last_heartbeat_at', '>=', now()->subMinutes($heartbeatThreshold))
            ->count();

        $engineStatus = match (true) {
            $activeServers === 0 => 'No servers',
            $onlineServers > 0 => 'Online',
            default => 'Offline',
        };

        $engineColor = match (true) {
            $activeServers === 0 => 'gray',
            $onlineServers > 0 => 'success',
            default => 'danger',
        };

        $customers = User::query()
            ->role(Roles::CUSTOMER)
            ->count();

        return [
            Stat::make('Jobs today', $jobsToday)
                ->description('Created in the last 24 hours'),
            Stat::make('Queue', $queueCount)
                ->description('Pending + processing')
                ->color('warning'),
            Stat::make('Failures (24h)', $failedLastDay)
                ->color('danger'),
            Stat::make('Engine status', $engineStatus)
                ->description("{$onlineServers} of {$activeServers} online")
                ->color($engineColor),
            Stat::make('Customers', $customers),
        ];
    }
}
