<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EngineWarmupOverview;
use App\Filament\Widgets\OpsEngineActivityTable;
use App\Filament\Widgets\OpsEngineHealthOverview;
use App\Filament\Widgets\OpsVerifierEngineLinks;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class VerifierEngine extends Dashboard
{
    protected static string $routePath = '/verifier-engine';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?string $navigationLabel = 'Verifier Engine Room';

    protected static ?string $title = 'Verifier Engine Room';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'verifier-engine';

    public function getWidgets(): array
    {
        return [
            OpsVerifierEngineLinks::class,
            OpsEngineHealthOverview::class,
            EngineWarmupOverview::class,
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
