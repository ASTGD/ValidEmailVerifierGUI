<?php

namespace App\Filament\Resources\EngineSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
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
                Section::make('Role Accounts Policy')
                    ->schema([
                        Select::make('role_accounts_behavior')
                            ->label('Role accounts behavior')
                            ->options([
                                'risky' => 'Mark as risky',
                                'allow' => 'Treat as normal',
                            ])
                            ->helperText('Controls whether role-based emails are auto-classified as risky.'),
                        Textarea::make('role_accounts_list')
                            ->label('Role accounts list')
                            ->rows(3)
                            ->helperText('Comma-separated local-parts (no domain). Example: info,admin,support.'),
                    ])
                    ->columns(2),
                Section::make('Catch-all Output Policy')
                    ->schema([
                        Select::make('catch_all_policy')
                            ->label('Catch-all policy')
                            ->options([
                                'risky_only' => 'Always risky',
                                'promote_if_score_gte' => 'Promote if score meets threshold',
                            ])
                            ->helperText('Controls how catch-all results are classified in final outputs.'),
                        TextInput::make('catch_all_promote_threshold')
                            ->label('Promotion threshold (score)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Used only when promotion is enabled. Leave blank to disable.')
                            ->visible(fn (Get $get): bool => $get('catch_all_policy') === 'promote_if_score_gte'),
                    ])
                    ->columns(2),
                Section::make('Standard Policy')
                    ->schema(self::policyFields('standard'))
                    ->columns(2),
                Section::make('Enhanced Policy')
                    ->schema(self::policyFields('enhanced'))
                    ->columns(2),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function policyFields(string $mode): array
    {
        $prefix = 'policy_'.$mode.'_';

        $fields = [
            Toggle::make($prefix.'enabled')
                ->label('Enabled')
                ->default(true),
            TextInput::make($prefix.'dns_timeout_ms')
                ->label('DNS timeout (ms)')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'smtp_connect_timeout_ms')
                ->label('SMTP connect timeout (ms)')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'smtp_read_timeout_ms')
                ->label('SMTP read timeout (ms)')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'max_mx_attempts')
                ->label('Max MX attempts')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'max_concurrency_default')
                ->label('Max concurrency')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'per_domain_concurrency')
                ->label('Per-domain concurrency')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make($prefix.'global_connects_per_minute')
                ->label('Global connects per minute')
                ->numeric()
                ->minValue(0)
                ->helperText('Leave blank to disable global rate limiting.'),
            TextInput::make($prefix.'tempfail_backoff_seconds')
                ->label('Tempfail backoff (seconds)')
                ->numeric()
                ->minValue(0)
                ->helperText('Leave blank to use the worker default backoff.'),
            TextInput::make($prefix.'circuit_breaker_tempfail_rate')
                ->label('Circuit breaker tempfail rate')
                ->numeric()
                ->minValue(0)
                ->maxValue(1)
                ->step('0.01')
                ->helperText('Optional threshold for tempfail-rate protection.'),
        ];

        if ($mode === 'enhanced') {
            $fields[] = Toggle::make($prefix.'catch_all_detection_enabled')
                ->label('Catch-all detection')
                ->helperText('Attempt a randomized RCPT test to flag catch-all domains.');
        } else {
            $fields[] = Hidden::make($prefix.'catch_all_detection_enabled')
                ->default((bool) config('engine.policy_defaults.standard.catch_all_detection_enabled', false));
        }

        return $fields;
    }
}
