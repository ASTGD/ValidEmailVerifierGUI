<?php

namespace App\Filament\Resources\RetentionSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RetentionSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Retention')
                    ->description('Controls how long completed/failed jobs are kept before cleanup.')
                    ->schema([
                        TextInput::make('retention_days')
                            ->label('Retention days')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
            ]);
    }
}
