<?php

namespace App\Filament\Resources\RetentionSettings;

use App\Filament\Resources\RetentionSettings\Pages\CreateRetentionSetting;
use App\Filament\Resources\RetentionSettings\Pages\EditRetentionSetting;
use App\Filament\Resources\RetentionSettings\Pages\ListRetentionSettings;
use App\Filament\Resources\RetentionSettings\Schemas\RetentionSettingForm;
use App\Filament\Resources\RetentionSettings\Tables\RetentionSettingsTable;
use App\Models\RetentionSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RetentionSettingResource extends Resource
{
    protected static ?string $model = RetentionSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Retention Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return RetentionSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RetentionSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetentionSettings::route('/'),
            'create' => CreateRetentionSetting::route('/create'),
            'edit' => EditRetentionSetting::route('/{record}/edit'),
        ];
    }
}
