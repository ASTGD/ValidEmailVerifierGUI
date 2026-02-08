<?php

namespace App\Filament\Resources\EngineSettings\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EngineSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $verificationSections = [
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
        ];

        $cacheSections = [
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
            Section::make('Cache Read Controls')
                ->description('Tune DynamoDB cache reads for On-Demand or Provisioned capacity.')
                ->schema([
                    View::make('filament.resources.engine-settings.partials.cache-connection')
                        ->columnSpanFull(),
                    ToggleButtons::make('cache_capacity_mode')
                        ->label('Capacity mode')
                        ->options([
                            'on_demand' => 'On-Demand',
                            'provisioned' => 'Provisioned',
                        ])
                        ->grouped()
                        ->inline()
                        ->default('on_demand')
                        ->afterStateHydrated(function (ToggleButtons $component, $state): void {
                            if (blank($state)) {
                                $component->state('on_demand');
                            }
                        })
                        ->live()
                        ->columnSpanFull()
                        ->helperText('Switch read profile between on-demand and provisioned behavior.'),
                    TextInput::make('cache_batch_size')
                        ->label('Batch size')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->helperText('Number of emails per DynamoDB batch call (max 100). Larger batches reduce calls but can throttle more easily.'),
                    Toggle::make('cache_consistent_read')
                        ->label('Consistent read')
                        ->helperText('Use strongly consistent reads. Costs more and can reduce throughput.'),
                    TextInput::make('cache_ondemand_max_batches_per_second')
                        ->label('On-demand max batches per second')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'on_demand')
                        ->helperText('Soft cap on batch calls per second. Leave blank to allow maximum throughput.'),
                    TextInput::make('cache_ondemand_sleep_ms_between_batches')
                        ->label('On-demand sleep between batches (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'on_demand')
                        ->helperText('Optional delay between batch calls to smooth spikes in On-Demand mode.'),
                    TextInput::make('cache_provisioned_max_batches_per_second')
                        ->label('Provisioned max batches per second')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Hard cap on batch calls per second to stay within RCU limits.'),
                    TextInput::make('cache_provisioned_sleep_ms_between_batches')
                        ->label('Provisioned sleep between batches (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Delay between batch calls to reduce RCU bursts.'),
                    TextInput::make('cache_provisioned_max_retries')
                        ->label('Provisioned max retries')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Number of retries when DynamoDB throttles requests.'),
                    TextInput::make('cache_provisioned_backoff_base_ms')
                        ->label('Provisioned backoff base (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Initial delay before retry (milliseconds). Doubles each retry.'),
                    TextInput::make('cache_provisioned_backoff_max_ms')
                        ->label('Provisioned backoff max (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Maximum delay between retries (milliseconds).'),
                    Toggle::make('cache_provisioned_jitter_enabled')
                        ->label('Provisioned jitter')
                        ->visible(fn (Get $get): bool => $get('cache_capacity_mode') === 'provisioned')
                        ->helperText('Randomizes backoff delays to reduce synchronized spikes.'),
                    Select::make('cache_failure_mode')
                        ->label('Failure handling')
                        ->options([
                            'fail_job' => 'Fail job',
                            'treat_miss' => 'Treat as cache miss',
                            'skip_cache' => 'Skip cache and continue',
                        ])
                        ->helperText('Choose behavior when DynamoDB is unavailable or throttled.'),
                ])
                ->columns(2),
            Section::make('Cache Write-back')
                ->description('Write verified cache-miss outcomes back to DynamoDB after finalization.')
                ->schema([
                    Toggle::make('cache_writeback_enabled')
                        ->label('Enable cache write-back')
                        ->helperText('When enabled, only verified cache-miss emails are written to DynamoDB.'),
                    CheckboxList::make('cache_writeback_statuses')
                        ->label('Write statuses')
                        ->options([
                            'valid' => 'Valid',
                            'invalid' => 'Invalid',
                        ])
                        ->columns(2)
                        ->default(['valid', 'invalid'])
                        ->helperText('Only Valid and Invalid are written. Risky is skipped.'),
                    TextInput::make('cache_writeback_batch_size')
                        ->label('Batch size')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(25)
                        ->required()
                        ->helperText('Number of items per DynamoDB batch write (max 25).'),
                    TextInput::make('cache_writeback_max_writes_per_second')
                        ->label('Max writes per second')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Soft cap for batch write calls per second. Leave blank to allow maximum throughput.'),
                    TextInput::make('cache_writeback_retry_attempts')
                        ->label('Retry attempts')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('How many times to retry unprocessed write items.'),
                    TextInput::make('cache_writeback_backoff_base_ms')
                        ->label('Backoff base (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Initial delay before retry (milliseconds). Doubles each retry.'),
                    TextInput::make('cache_writeback_backoff_max_ms')
                        ->label('Backoff max (ms)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Maximum delay between retries (milliseconds).'),
                    Select::make('cache_writeback_failure_mode')
                        ->label('Failure handling')
                        ->options([
                            'fail_job' => 'Fail job',
                            'skip_writes' => 'Skip write-back',
                            'continue' => 'Continue without failing',
                        ])
                        ->helperText('Choose behavior when write-back fails.'),
                    Toggle::make('cache_writeback_test_mode_enabled')
                        ->label('Enable cache write-back test mode')
                        ->helperText('Writes cache-miss emails to the test table with result Cache_miss when cache-only mode is enabled.'),
                    TextInput::make('cache_writeback_test_table')
                        ->label('Cache write-back test table')
                        ->helperText('DynamoDB table name for test writes. Leave blank to use the env default.')
                        ->visible(fn (Get $get): bool => (bool) $get('cache_writeback_test_mode_enabled')),
                ])
                ->columns(2),
        ];

        $monitoringSections = [
            Section::make('System Metrics')
                ->description('Controls the metrics source used for admin health dashboards.')
                ->schema([
                    Select::make('metrics_source')
                        ->label('Metrics source')
                        ->options([
                            'container' => 'Container (cgroup)',
                            'host' => 'Host (system)',
                        ])
                        ->default('container')
                        ->helperText('Container reads cgroup stats. Host reads system /proc metrics.'),
                ])
                ->columns(1),
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
        ];

        $queueSections = [
            Section::make('Queue Driver')
                ->description('Controls the Laravel queue driver. Restart queue workers after changes.')
                ->schema([
                    Select::make('queue_connection')
                        ->label('Queue connection')
                        ->options([
                            'redis' => 'Redis',
                            'database' => 'Database',
                            'sync' => 'Sync (no queue)',
                        ])
                        ->afterStateHydrated(function (Select $component, $state): void {
                            if (blank($state)) {
                                $component->state(config('queue.default', 'database'));
                            }
                        })
                        ->live()
                        ->helperText('Overrides QUEUE_CONNECTION for Laravel queues.'),
                    Select::make('cache_store')
                        ->label('App cache store')
                        ->options([
                            'redis' => 'Redis',
                            'database' => 'Database',
                            'file' => 'File',
                            'array' => 'Array (testing)',
                        ])
                        ->afterStateHydrated(function (Select $component, $state): void {
                            if (blank($state)) {
                                $component->state(config('cache.default', 'database'));
                            }
                        })
                        ->helperText('Overrides CACHE_STORE for Laravel cache only (verification cache is separate).'),
                    Placeholder::make('configured_driver')
                        ->label('Configured')
                        ->content(fn (Get $get): string => sprintf(
                            'Queue: %s | Cache: %s',
                            $get('queue_connection') ?: config('queue.default', 'database'),
                            $get('cache_store') ?: config('cache.default', 'database')
                        )),
                ])
                ->columns(2),
            Section::make('Horizon Dashboard')
                ->description('Horizon provides queue monitoring and requires Redis.')
                ->schema([
                    Toggle::make('horizon_enabled')
                        ->label('Enable Horizon dashboard')
                        ->helperText('Requires Redis queue and running `artisan horizon` on the server.')
                        ->disabled(fn (Get $get): bool => $get('queue_connection') !== 'redis'),
                    Placeholder::make('horizon_path')
                        ->label('Horizon path')
                        ->content(fn (): string => '/'.trim((string) config('horizon.path', 'horizon'), '/')),
                    Placeholder::make('runtime_queue')
                        ->label('Runtime queue driver')
                        ->content(fn (): string => (string) config('queue.default')),
                    Placeholder::make('runtime_cache')
                        ->label('Runtime cache store')
                        ->content(fn (): string => (string) config('cache.default')),
                    Placeholder::make('queue_note')
                        ->label('Note')
                        ->content('If Redis is offline, the app will fall back to the existing queue/cache config to avoid errors.'),
                ])
                ->columns(2),
            Section::make('Worker Tuning')
                ->description('Stored values are used for recommended commands. Active supervisors are segmented by lane; restart Horizon after changes.')
                ->schema([
                    TextInput::make('queue_worker_name')
                        ->label('Supervisor name')
                        ->helperText('Reference supervisor name for fallback queue:work command. Default: supervisor-finalize.'),
                    Placeholder::make('queue_supervisor_lanes')
                        ->label('Segmented supervisors')
                        ->content('supervisor-default, supervisor-prepare, supervisor-parse, supervisor-finalize, supervisor-imports, supervisor-cache-writeback'),
                    TextInput::make('queue_worker_processes')
                        ->label('Max processes')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Total worker processes (Horizon maxProcesses).'),
                    TextInput::make('queue_worker_memory')
                        ->label('Memory (MB)')
                        ->numeric()
                        ->minValue(64)
                        ->helperText('Worker memory limit before restart.'),
                    TextInput::make('queue_worker_timeout')
                        ->label('Timeout (sec)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Max seconds a job may run.'),
                    TextInput::make('queue_worker_tries')
                        ->label('Tries')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Max attempts before failing a job.'),
                    TextInput::make('queue_worker_sleep')
                        ->label('Sleep (sec)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Used by queue:work fallback.'),
                    Placeholder::make('queue_worker_configured')
                        ->label('Configured summary')
                        ->content(fn (Get $get): string => self::queueWorkerSummary($get, false)),
                    Placeholder::make('queue_worker_effective')
                        ->label('Effective defaults')
                        ->content(fn (): string => self::queueWorkerSummary(null, true)),
                ])
                ->columns(2),
            Section::make('Command Helpers')
                ->description('Copy these commands into production or server docs.')
                ->schema([
                    Placeholder::make('horizon_start')
                        ->label('Start Horizon')
                        ->content('php artisan horizon'),
                    Placeholder::make('horizon_terminate')
                        ->label('Terminate Horizon')
                        ->content('php artisan horizon:terminate'),
                    Placeholder::make('queue_work')
                        ->label('Queue worker fallback')
                        ->content(fn (Get $get): string => self::queueWorkCommand($get)),
                ])
                ->columns(1),
        ];

        return $schema
            ->components([
                Tabs::make('Verification Settings')
                    ->extraAttributes(['class' => 'w-full'])
                    ->tabs([
                        Tab::make('Verification')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema($verificationSections),
                            ]),
                        Tab::make('Cache (DynamoDB)')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema($cacheSections),
                            ]),
                        Tab::make('Monitoring')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema($monitoringSections),
                            ]),
                        Tab::make('Queue Engine')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema($queueSections),
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
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

    private static function queueWorkerSummary(?Get $get, bool $effective): string
    {
        $name = self::queueWorkerValue($get, 'queue_worker_name', self::queueReferenceSupervisor());
        $processes = self::queueWorkerValue($get, 'queue_worker_processes', self::horizonDefault('maxProcesses', 1));
        $memory = self::queueWorkerValue($get, 'queue_worker_memory', self::horizonDefault('memory', 128));
        $timeout = self::queueWorkerValue($get, 'queue_worker_timeout', self::horizonDefault('timeout', 60));
        $tries = self::queueWorkerValue($get, 'queue_worker_tries', self::horizonDefault('tries', 1));
        $sleep = self::queueWorkerValue($get, 'queue_worker_sleep', 3);

        if (! $effective && $get) {
            $configured = [
                $get('queue_worker_name'),
                $get('queue_worker_processes'),
                $get('queue_worker_memory'),
                $get('queue_worker_timeout'),
                $get('queue_worker_tries'),
                $get('queue_worker_sleep'),
            ];

            $hasConfig = collect($configured)
                ->filter(fn ($value): bool => $value !== null && $value !== '')
                ->isNotEmpty();

            if (! $hasConfig) {
                return 'No overrides set. Defaults will apply.';
            }
        }

        return sprintf(
            'Supervisor: %s | Processes: %s | Memory: %s MB | Timeout: %s s | Tries: %s | Sleep: %s s',
            $name,
            $processes,
            $memory,
            $timeout,
            $tries,
            $sleep
        );
    }

    private static function queueWorkCommand(Get $get): string
    {
        $driver = (string) config('queue.default', 'database');
        $queue = config("queue.connections.{$driver}.queue", 'default');
        $sleep = self::queueWorkerValue($get, 'queue_worker_sleep', 3);
        $tries = self::queueWorkerValue($get, 'queue_worker_tries', self::horizonDefault('tries', 1));
        $timeout = self::queueWorkerValue($get, 'queue_worker_timeout', self::horizonDefault('timeout', 60));
        $memory = self::queueWorkerValue($get, 'queue_worker_memory', self::horizonDefault('memory', 128));

        return sprintf(
            'php artisan queue:work --queue=%s --sleep=%s --tries=%s --timeout=%s --memory=%s',
            $queue,
            $sleep,
            $tries,
            $timeout,
            $memory
        );
    }

    private static function queueWorkerValue(?Get $get, string $key, mixed $fallback): mixed
    {
        if ($get) {
            $value = $get($key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private static function horizonDefault(string $key, mixed $fallback): mixed
    {
        $env = (string) config('app.env');
        $reference = self::queueReferenceSupervisor();

        return config("horizon.environments.{$env}.{$reference}.{$key}")
            ?? config("horizon.defaults.{$reference}.{$key}")
            ?? $fallback;
    }

    private static function queueReferenceSupervisor(): string
    {
        return 'supervisor-finalize';
    }
}
