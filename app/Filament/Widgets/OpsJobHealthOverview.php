<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsJobHealthOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $processing = VerificationJob::query()
            ->where('status', VerificationJobStatus::Processing)
            ->count();

        $pending = VerificationJob::query()
            ->where('status', VerificationJobStatus::Pending)
            ->count();

        $completedLastHour = VerificationJob::query()
            ->where('status', VerificationJobStatus::Completed)
            ->where('finished_at', '>=', now()->subHour())
            ->count();

        $failedLastDay = VerificationJob::query()
            ->where('status', VerificationJobStatus::Failed)
            ->where('finished_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make('Processing', (string) $processing)
                ->color($processing > 0 ? 'info' : 'gray'),
            Stat::make('Pending', (string) $pending)
                ->color($pending > 0 ? 'warning' : 'gray'),
            Stat::make('Completed (1h)', (string) $completedLastHour)
                ->color($completedLastHour > 0 ? 'success' : 'gray'),
            Stat::make('Failed (24h)', (string) $failedLastDay)
                ->color($failedLastDay > 0 ? 'danger' : 'gray'),
        ];
    }
}
