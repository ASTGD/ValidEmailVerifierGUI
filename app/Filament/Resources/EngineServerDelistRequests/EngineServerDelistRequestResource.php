<?php

namespace App\Filament\Resources\EngineServerDelistRequests;

use App\Filament\Resources\EngineServerDelistRequests\Pages\CreateEngineServerDelistRequest;
use App\Filament\Resources\EngineServerDelistRequests\Pages\EditEngineServerDelistRequest;
use App\Filament\Resources\EngineServerDelistRequests\Pages\ListEngineServerDelistRequests;
use App\Filament\Resources\EngineServerDelistRequests\Schemas\EngineServerDelistRequestForm;
use App\Filament\Resources\EngineServerDelistRequests\Tables\EngineServerDelistRequestsTable;
use App\Models\EngineServerDelistRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineServerDelistRequestResource extends Resource
{
    protected static ?string $model = EngineServerDelistRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Delist Requests';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return EngineServerDelistRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngineServerDelistRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineServerDelistRequests::route('/'),
            'create' => CreateEngineServerDelistRequest::route('/create'),
            'edit' => EditEngineServerDelistRequest::route('/{record}/edit'),
        ];
    }
}
