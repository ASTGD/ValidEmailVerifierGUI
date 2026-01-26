<?php

namespace App\Filament\Resources\EngineServerDelistRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EngineServerDelistRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Delist Request')
                    ->schema([
                        Select::make('engine_server_id')
                            ->label('Engine server')
                            ->relationship('engineServer', 'name')
                            ->searchable()
                            ->required(),
                        TextInput::make('rbl')
                            ->label('RBL')
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Open',
                                'resolved' => 'Resolved',
                            ])
                            ->default('open')
                            ->required(),
                        DateTimePicker::make('resolved_at')
                            ->label('Resolved at')
                            ->visible(fn (Get $get): bool => $get('status') === 'resolved'),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        Hidden::make('requested_by'),
                    ])
                    ->columns(2),
            ]);
    }
}
