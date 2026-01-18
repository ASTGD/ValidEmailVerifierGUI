<?php

namespace App\Filament\Resources\EngineSettings;

use App\Filament\Resources\EngineSettings\Pages\CreateEngineSetting;
use App\Filament\Resources\EngineSettings\Pages\EditEngineSetting;
use App\Filament\Resources\EngineSettings\Pages\ListEngineSettings;
use App\Filament\Resources\EngineSettings\Schemas\EngineSettingForm;
use App\Filament\Resources\EngineSettings\Tables\EngineSettingsTable;
use App\Models\EngineSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EngineSettingResource extends Resource
{
    protected static ?string $model = EngineSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Engine Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Engine';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return EngineSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EngineSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEngineSettings::route('/'),
            'create' => CreateEngineSetting::route('/create'),
            'edit' => EditEngineSetting::route('/{record}/edit'),
        ];
    }
}
