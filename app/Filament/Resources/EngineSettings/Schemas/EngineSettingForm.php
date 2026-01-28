<?php

namespace App\Filament\Resources\EngineSettings\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
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
                        Toggle::make('show_single_checks_in_admin')
                            ->label('Show single checks in admin')
                            ->helperText('Disabled hides single-email checks from job lists.'),
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
                Section::make('Cache Test Mode')
                    ->description('For testing only. Uses cache hits and assigns a status to cache misses without running verification.')
                    ->schema([
                        Toggle::make('cache_only_mode_enabled')
                            ->label('Enable cache-only mode')
                            ->helperText('When enabled, uploads skip worker verification and finalize using cache results.'),
                        Select::make('cache_only_miss_status')
                            ->label('Cache miss status')
                            ->options([
                                'valid' => 'Valid',
                                'invalid' => 'Invalid',
                                'risky' => 'Risky',
                            ])
                            ->default('risky')
                            ->helperText('Status assigned to emails not found in cache during test mode.'),
                    ])
                    ->columns(2),
                Section::make('Catche Server Health Check')
                    ->schema([
                        View::make('filament.resources.engine-settings.partials.cache-health-check')
                            ->viewData(fn ($livewire): array => method_exists($livewire, 'cacheHealthCheckViewData')
                                ? $livewire->cacheHealthCheckViewData()
                                : []),
                    ])
                    ->visible(fn ($livewire): bool => method_exists($livewire, 'cacheHealthCheckViewData')),
                Section::make('Standard Policy')
                    ->schema(self::policyFields('standard'))
                    ->columns(2),
                Section::make('Enhanced Policy')
                    ->schema(self::policyFields('enhanced'))
                    ->columns(2),
                Section::make('Tempfail Retry Queue')
                    ->description('Schedule delayed retries for tempfail results before finalizing outputs.')
                    ->schema([
                        Toggle::make('tempfail_retry_enabled')
                            ->label('Enable tempfail retries')
                            ->default(false),
                        TextInput::make('tempfail_retry_max_attempts')
                            ->label('Max retry attempts')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('tempfail_retry_backoff_minutes')
                            ->label('Backoff schedule (minutes)')
                            ->helperText('Comma-separated delays per attempt. Example: 10,30,60.'),
                        TextInput::make('tempfail_retry_reasons')
                            ->label('Retry reasons')
                            ->helperText('Comma-separated reason codes eligible for retry.'),
                    ])
                    ->columns(2),
                Section::make('Reputation Thresholds')
                    ->description('Used for server warm-up and tempfail rate status in Admin.')
                    ->schema([
                        TextInput::make('reputation_window_hours')
                            ->label('Window (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reputation_min_samples')
                            ->label('Minimum samples')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('reputation_tempfail_warn_rate')
                            ->label('Warn tempfail rate')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required(),
                        TextInput::make('reputation_tempfail_critical_rate')
                            ->label('Critical tempfail rate')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Blacklist Monitor')
                    ->description('Controls the external blacklist monitor service.')
                    ->schema([
                        Toggle::make('monitor_enabled')
                            ->label('Enable blacklist monitor')
                            ->default(false),
                        TextInput::make('monitor_interval_minutes')
                            ->label('Check interval (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('monitor_rbl_list')
                            ->label('RBL list')
                            ->rows(3)
                            ->helperText('Comma-separated RBL domains (no IP prefix).'),
                        Select::make('monitor_dns_mode')
                            ->label('Resolver mode')
                            ->options([
                                'system' => 'System DNS (host)',
                                'custom' => 'Custom DNS server',
                            ])
                            ->default('system')
                            ->live()
                            ->helperText('Use the host resolver or specify a custom DNS server.'),
                        TextInput::make('monitor_dns_server_ip')
                            ->label('Custom DNS IP')
                            ->placeholder('Resolver IP')
                            ->visible(fn (Get $get): bool => $get('monitor_dns_mode') === 'custom'),
                        TextInput::make('monitor_dns_server_port')
                            ->label('Custom DNS port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(53)
                            ->visible(fn (Get $get): bool => $get('monitor_dns_mode') === 'custom'),
                    ])
                    ->columns(2),
                Section::make('Provider Policies')
                    ->description('Optional overrides for specific mailbox providers to reduce tempfails.')
                    ->schema([
                        Repeater::make('provider_policies')
                            ->label('Provider overrides')
                            ->default([])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Provider name')
                                    ->required(),
                                Toggle::make('enabled')
                                    ->label('Enabled')
                                    ->default(true),
                                TagsInput::make('domains')
                                    ->label('Domains')
                                    ->helperText('Add root domains or subdomains (e.g. outlook.com).')
                                    ->required(),
                                TextInput::make('per_domain_concurrency')
                                    ->label('Per-domain concurrency')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('connects_per_minute')
                                    ->label('Connects per minute')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('tempfail_backoff_seconds')
                                    ->label('Tempfail backoff (seconds)')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('retryable_network_retries')
                                    ->label('Retryable network retries')
                                    ->numeric()
                                    ->minValue(0),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsed()
                            ->helperText('Overrides apply to any email domain that matches the list.'),
                    ]),
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
