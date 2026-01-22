<?php

namespace App\Filament\Resources\EngineServers;

use App\Filament\Resources\EngineServers\Pages\CreateEngineServer;
use App\Filament\Resources\EngineServers\Pages\EditEngineServer;
use App\Filament\Resources\EngineServers\Pages\ListEngineServers;
use App\Filament\Resources\EngineServers\Schemas\EngineServerForm;
use App\Filament\Resources\EngineServers\Tables\EngineServersTable;
use App\Models\EngineServer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineServerResource extends Resource
{
    protected static ?string $model = EngineServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServer;

    protected static ?string $navigationLabel = 'Engine Servers';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return EngineServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngineServersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineServers::route('/'),
            'create' => CreateEngineServer::route('/create'),
            'edit' => EditEngineServer::route('/{record}/edit'),
        ];
    }
}
