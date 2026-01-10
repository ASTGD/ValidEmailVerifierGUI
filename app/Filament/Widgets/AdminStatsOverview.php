<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
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
            ->where('finished_at', '>=', now()->subDay())
            ->count();

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
            Stat::make('Customers', $customers),
        ];
    }
}
