<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OpsActiveJobsTable;
use App\Filament\Widgets\OpsEngineActivityTable;
use App\Filament\Widgets\OpsEngineHealthOverview;
use App\Filament\Widgets\OpsJobHealthOverview;
use App\Filament\Widgets\OpsOpenQueueIncidentsTable;
use App\Filament\Widgets\OpsQueueDepthChart;
use App\Filament\Widgets\OpsQueueFallbackAlert;
use App\Filament\Widgets\OpsQueueHealthOverview;
use App\Filament\Widgets\OpsQueueIncidentStatus;
use App\Filament\Widgets\OpsQueueRecoveryActionsTable;
use App\Filament\Widgets\OpsQueueStatusOverview;
use App\Filament\Widgets\OpsSeedSendHealthOverview;
use App\Filament\Widgets\OpsSystemHealthOverview;
use App\Filament\Widgets\OpsSystemTrendChart;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class OpsOverview extends Dashboard
{
    protected static string $routePath = '/ops-overview';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Ops Overview';

    protected static ?string $title = 'Ops Overview';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'ops-overview';

    public function getWidgets(): array
    {
        return [
            OpsQueueFallbackAlert::class,
            OpsSystemHealthOverview::class,
            OpsQueueStatusOverview::class,
            OpsQueueIncidentStatus::class,
            OpsSeedSendHealthOverview::class,
            OpsQueueHealthOverview::class,
            OpsEngineHealthOverview::class,
            OpsJobHealthOverview::class,
            OpsSystemTrendChart::class,
            OpsQueueDepthChart::class,
            OpsOpenQueueIncidentsTable::class,
            OpsQueueRecoveryActionsTable::class,
            OpsActiveJobsTable::class,
            OpsEngineActivityTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
