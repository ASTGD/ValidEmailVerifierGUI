<?php

namespace App\Filament\Resources\EngineServers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EngineServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Server Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('ip_address')
                            ->label('IP address')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('environment')
                            ->maxLength(255),
                        TextInput::make('region')
                            ->maxLength(255),
                        DateTimePicker::make('last_heartbeat_at')
                            ->label('Last heartbeat')
                            ->seconds(false),
                        Toggle::make('is_active')
                            ->label('Enabled')
                            ->default(true),
                        Toggle::make('drain_mode')
                            ->label('Drain mode')
                            ->helperText('When enabled, the server stops receiving new work.'),
                        TextInput::make('max_concurrency')
                            ->label('Max concurrency')
                            ->numeric()
                            ->minValue(1),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('SMTP Identity')
                    ->schema([
                        TextInput::make('helo_name')
                            ->label('Host Name')
                            ->maxLength(255)
                            ->helperText('Must resolve and ideally match rDNS.'),
                        TextInput::make('mail_from_address')
                            ->label('MAIL FROM address')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Used for RCPT probing in Enhanced mode.'),
                        Select::make('verifier_domain_id')
                            ->label('Verifier domain')
                            ->relationship('verifierDomain', 'domain', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->helperText('Select the base domain used for SPF/DKIM/DMARC alignment.'),
                    ])
                    ->columns(2),
            ]);
    }
}
