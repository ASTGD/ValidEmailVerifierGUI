<?php

namespace App\Filament\Resources\EngineServerReputationChecks;

use App\Filament\Resources\EngineServerReputationChecks\Pages\ListEngineServerReputationChecks;
use App\Filament\Resources\EngineServerReputationChecks\Tables\EngineServerReputationChecksTable;
use App\Models\EngineServerReputationCheck;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineServerReputationCheckResource extends Resource
{
    protected static ?string $model = EngineServerReputationCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Reputation Checks';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return EngineServerReputationChecksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineServerReputationChecks::route('/'),
        ];
    }
}
