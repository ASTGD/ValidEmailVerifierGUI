<?php

namespace App\Filament\Resources\EngineSettings\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EngineSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Global Controls')
                    ->schema([
                        Toggle::make('engine_paused')
                            ->label('Engine paused')
                            ->helperText('When paused, workers will not claim new chunks.'),
                        Toggle::make('enhanced_mode_enabled')
                            ->label('Enhanced mode enabled')
                            ->helperText('Enables Enhanced mode selection (pipeline remains standard for now).'),
                    ])
                    ->columns(2),
            ]);
    }
}
