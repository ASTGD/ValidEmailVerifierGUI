<?php

namespace App\Filament\Resources\EngineServerBlacklistEvents;

use App\Filament\Resources\EngineServerBlacklistEvents\Pages\ListEngineServerBlacklistEvents;
use App\Filament\Resources\EngineServerBlacklistEvents\Tables\EngineServerBlacklistEventsTable;
use App\Models\EngineServerBlacklistEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineServerBlacklistEventResource extends Resource
{
    protected static ?string $model = EngineServerBlacklistEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Blacklist Events';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return EngineServerBlacklistEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineServerBlacklistEvents::route('/'),
        ];
    }
}
