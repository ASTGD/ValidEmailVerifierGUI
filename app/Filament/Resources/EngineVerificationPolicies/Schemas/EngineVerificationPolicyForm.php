<?php

namespace App\Filament\Resources\EngineVerificationPolicies\Schemas;

use App\Enums\VerificationMode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EngineVerificationPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Policy Settings')
                    ->schema([
                        Select::make('mode')
                            ->label('Mode')
                            ->options([
                                VerificationMode::Standard->value => VerificationMode::Standard->label(),
                                VerificationMode::Enhanced->value => VerificationMode::Enhanced->label(),
                            ])
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?object $record): bool => $record !== null),
                        Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true),
                        TextInput::make('dns_timeout_ms')
                            ->label('DNS timeout (ms)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('smtp_connect_timeout_ms')
                            ->label('SMTP connect timeout (ms)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('smtp_read_timeout_ms')
                            ->label('SMTP read timeout (ms)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('max_mx_attempts')
                            ->label('Max MX attempts')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('max_concurrency_default')
                            ->label('Max concurrency')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('per_domain_concurrency')
                            ->label('Per-domain concurrency')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('global_connects_per_minute')
                            ->label('Global connects per minute')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave blank to disable global rate limiting.'),
                        TextInput::make('tempfail_backoff_seconds')
                            ->label('Tempfail backoff (seconds)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave blank to use the worker default backoff.'),
                        TextInput::make('circuit_breaker_tempfail_rate')
                            ->label('Circuit breaker tempfail rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step('0.01')
                            ->helperText('Optional threshold for tempfail-rate protection.'),
                    ])
                    ->columns(2),
            ]);
    }
}
