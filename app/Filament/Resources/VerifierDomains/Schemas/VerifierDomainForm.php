<?php

namespace App\Filament\Resources\VerifierDomains\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerifierDomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domain Details')
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Base domain used for SMTP identity alignment.'),
                        Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
