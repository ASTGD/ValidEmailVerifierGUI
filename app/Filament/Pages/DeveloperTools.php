<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Models\GlobalSetting;
use App\Models\QueueMetric;
use App\Models\SystemMetric;
use App\Models\VerificationJob;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class DeveloperTools extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Developer Tools';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Developer Tools';

    protected static ?string $slug = 'developer-tools';

    protected string $view = 'filament.pages.developer-tools';

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $formData = [];

    public array $results = [];

    public array $queueResults = [];

    public array $pollResults = [];

    public array $costResults = [];

    public array $snapshot = [];

    public array $recentJobs = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::isEnabled();
    }

    public static function canAccess(): bool
    {
        return static::isEnabled();
    }

    public function mount(): void
    {
        $this->form->fill([
            'avg_emails_per_job' => 20000,
            'chunk_size' => config('engine.chunk_size_default', 5000),
            'verify_time_per_email_ms' => 120,
            'cache_hit_rate_pct' => 40,
            'overhead_per_chunk_seconds' => 1.0,
            'go_workers' => 5,
            'parallel_chunks_per_worker' => 1,
            'poll_interval_seconds' => 5,
            'incoming_jobs_per_hour' => 10,
            'current_queue_depth_jobs' => 0,
            'dynamodb_writes_per_month' => 0,
            'dynamodb_cost_per_million_writes' => 0,
            'dynamodb_storage_gb' => 0,
            'dynamodb_cost_per_gb_month' => 0,
            's3_storage_gb' => 0,
            's3_cost_per_gb_month' => 0,
            's3_put_requests_per_month' => 0,
            's3_cost_per_1k_put' => 0,
            's3_get_requests_per_month' => 0,
            's3_cost_per_1k_get' => 0,
            'queue_requests_per_month' => 0,
            'queue_cost_per_million_requests' => 0,
            'queue_fixed_monthly_cost' => 0,
        ]);

        $this->recalculate();
        $this->refreshSnapshot();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Capacity Inputs')
                    ->description('Approximate throughput and polling load. Results are directional and depend on real-world latency.')
                    ->schema([
                        TextInput::make('avg_emails_per_job')
                            ->label('Avg emails per job')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('emails')
                            ->required(),
                        TextInput::make('chunk_size')
                            ->label('Chunk size')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('emails')
                            ->required(),
                        TextInput::make('verify_time_per_email_ms')
                            ->label('Verify time per email')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.1)
                            ->suffix('ms')
                            ->required(),
                        TextInput::make('cache_hit_rate_pct')
                            ->label('Cache hit rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.1)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('overhead_per_chunk_seconds')
                            ->label('Overhead per chunk')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.1)
                            ->suffix('sec')
                            ->required(),
                        TextInput::make('go_workers')
                            ->label('Go workers')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('parallel_chunks_per_worker')
                            ->label('Parallel chunks/worker')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('poll_interval_seconds')
                            ->label('Poll interval')
                            ->numeric()
                            ->minValue(1)
                            ->step(0.1)
                            ->suffix('sec')
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Queue Pressure Inputs')
                    ->schema([
                        TextInput::make('incoming_jobs_per_hour')
                            ->label('Incoming jobs per hour')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('jobs')
                            ->required(),
                        TextInput::make('current_queue_depth_jobs')
                            ->label('Current queue depth')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('jobs')
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Cost Estimator Inputs')
                    ->description('Provide your current pricing values (USD or local currency). Results assume monthly totals.')
                    ->schema([
                        TextInput::make('dynamodb_writes_per_month')
                            ->label('DynamoDB writes per month')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('dynamodb_cost_per_million_writes')
                            ->label('DynamoDB cost / 1M writes')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('dynamodb_storage_gb')
                            ->label('DynamoDB storage (GB)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('dynamodb_cost_per_gb_month')
                            ->label('DynamoDB cost / GB-month')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('s3_storage_gb')
                            ->label('S3 storage (GB)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('s3_cost_per_gb_month')
                            ->label('S3 cost / GB-month')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('s3_put_requests_per_month')
                            ->label('S3 PUT requests / month')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('s3_cost_per_1k_put')
                            ->label('S3 cost / 1K PUTs')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('s3_get_requests_per_month')
                            ->label('S3 GET requests / month')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('s3_cost_per_1k_get')
                            ->label('S3 cost / 1K GETs')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('queue_requests_per_month')
                            ->label('Queue requests / month')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('queue_cost_per_million_requests')
                            ->label('Queue cost / 1M requests')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001),
                        TextInput::make('queue_fixed_monthly_cost')
                            ->label('Queue fixed monthly cost')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ])
                    ->columns(3),
            ])
            ->statePath('formData');
    }

    public function updatedFormData(): void
    {
        $this->recalculate();
    }

    public function refreshSnapshotAction(): void
    {
        $this->refreshSnapshot();
    }

    protected function recalculate(): void
    {
        $data = $this->form->getState();

        $avgEmailsPerJob = max(1, (int) ($data['avg_emails_per_job'] ?? 1));
        $chunkSize = max(1, (int) ($data['chunk_size'] ?? 1));
        $verifyTimeMs = max(0.0, (float) ($data['verify_time_per_email_ms'] ?? 0));
        $cacheHitRate = min(100.0, max(0.0, (float) ($data['cache_hit_rate_pct'] ?? 0)));
        $overheadSeconds = max(0.0, (float) ($data['overhead_per_chunk_seconds'] ?? 0));
        $goWorkers = max(1, (int) ($data['go_workers'] ?? 1));
        $parallelChunks = max(1, (int) ($data['parallel_chunks_per_worker'] ?? 1));
        $pollInterval = max(1.0, (float) ($data['poll_interval_seconds'] ?? 1));
        $incomingJobsPerHour = max(0.0, (float) ($data['incoming_jobs_per_hour'] ?? 0));
        $queueDepthJobs = max(0.0, (float) ($data['current_queue_depth_jobs'] ?? 0));

        $cacheMissRate = 1 - ($cacheHitRate / 100);
        $verifyTimeSeconds = $verifyTimeMs / 1000;

        $chunkTimeSeconds = $overheadSeconds + ($chunkSize * $cacheMissRate * $verifyTimeSeconds);
        $chunkThroughput = $chunkTimeSeconds > 0 ? ($goWorkers * $parallelChunks) / $chunkTimeSeconds : 0.0;
        $emailThroughput = $chunkThroughput * $chunkSize;
        $jobsPerHour = $avgEmailsPerJob > 0 ? ($emailThroughput * 3600) / $avgEmailsPerJob : 0.0;
        $jobSeconds = $emailThroughput > 0 ? $avgEmailsPerJob / $emailThroughput : 0.0;
        $pollRps = $pollInterval > 0 ? $goWorkers / $pollInterval : 0.0;

        $this->results = [
            'Cache miss rate' => $this->formatNumber($cacheMissRate * 100, 1) . '%',
            'Chunk time' => $this->formatDuration($chunkTimeSeconds),
            'Chunks/sec' => $this->formatNumber($chunkThroughput, 3),
            'Emails/sec' => $this->formatNumber($emailThroughput, 2),
            'Emails/min' => $this->formatNumber($emailThroughput * 60, 0),
            'Jobs/hour' => $this->formatNumber($jobsPerHour, 2),
            'Est. job duration' => $this->formatDuration($jobSeconds),
        ];

        $this->pollResults = [
            'Poll RPS' => $this->formatNumber($pollRps, 2),
            'Poll/min' => $this->formatNumber($pollRps * 60, 0),
            'Poll/hour' => $this->formatNumber($pollRps * 3600, 0),
            'Poll/day' => $this->formatNumber($pollRps * 86400, 0),
        ];

        $requiredWorkers = $jobsPerHour > 0 ? $goWorkers * ($incomingJobsPerHour / $jobsPerHour) : 0.0;
        $netCapacity = $jobsPerHour - $incomingJobsPerHour;

        if ($netCapacity <= 0) {
            $drainTime = 'Backlog grows';
        } else {
            $drainHours = $queueDepthJobs > 0 ? ($queueDepthJobs / $netCapacity) : 0.0;
            $drainTime = $this->formatDuration($drainHours * 3600);
        }

        $this->queueResults = [
            'Incoming jobs/hour' => $this->formatNumber($incomingJobsPerHour, 2),
            'Capacity jobs/hour' => $this->formatNumber($jobsPerHour, 2),
            'Net jobs/hour' => $this->formatNumber($netCapacity, 2),
            'Queue depth jobs' => $this->formatNumber($queueDepthJobs, 0),
            'Time to drain backlog' => $drainTime,
            'Workers needed (steady)' => $this->formatNumber($requiredWorkers, 1),
        ];

        $dynamoWrites = max(0.0, (float) ($data['dynamodb_writes_per_month'] ?? 0));
        $dynamoCostPerMillion = max(0.0, (float) ($data['dynamodb_cost_per_million_writes'] ?? 0));
        $dynamoStorageGb = max(0.0, (float) ($data['dynamodb_storage_gb'] ?? 0));
        $dynamoStorageCost = max(0.0, (float) ($data['dynamodb_cost_per_gb_month'] ?? 0));

        $s3StorageGb = max(0.0, (float) ($data['s3_storage_gb'] ?? 0));
        $s3StorageCost = max(0.0, (float) ($data['s3_cost_per_gb_month'] ?? 0));
        $s3PutRequests = max(0.0, (float) ($data['s3_put_requests_per_month'] ?? 0));
        $s3PutCost = max(0.0, (float) ($data['s3_cost_per_1k_put'] ?? 0));
        $s3GetRequests = max(0.0, (float) ($data['s3_get_requests_per_month'] ?? 0));
        $s3GetCost = max(0.0, (float) ($data['s3_cost_per_1k_get'] ?? 0));

        $queueRequests = max(0.0, (float) ($data['queue_requests_per_month'] ?? 0));
        $queueCostPerMillion = max(0.0, (float) ($data['queue_cost_per_million_requests'] ?? 0));
        $queueFixedCost = max(0.0, (float) ($data['queue_fixed_monthly_cost'] ?? 0));

        $dynamoWriteCost = ($dynamoWrites / 1_000_000) * $dynamoCostPerMillion;
        $dynamoStorageCostTotal = $dynamoStorageGb * $dynamoStorageCost;
        $s3StorageCostTotal = $s3StorageGb * $s3StorageCost;
        $s3PutCostTotal = ($s3PutRequests / 1000) * $s3PutCost;
        $s3GetCostTotal = ($s3GetRequests / 1000) * $s3GetCost;
        $queueRequestCost = ($queueRequests / 1_000_000) * $queueCostPerMillion;
        $queueTotalCost = $queueRequestCost + $queueFixedCost;

        $totalCost = $dynamoWriteCost + $dynamoStorageCostTotal + $s3StorageCostTotal + $s3PutCostTotal + $s3GetCostTotal + $queueTotalCost;

        $this->costResults = [
            'DynamoDB writes' => $this->formatCurrency($dynamoWriteCost),
            'DynamoDB storage' => $this->formatCurrency($dynamoStorageCostTotal),
            'S3 storage' => $this->formatCurrency($s3StorageCostTotal),
            'S3 PUT requests' => $this->formatCurrency($s3PutCostTotal),
            'S3 GET requests' => $this->formatCurrency($s3GetCostTotal),
            'Queue requests' => $this->formatCurrency($queueRequestCost),
            'Queue fixed cost' => $this->formatCurrency($queueFixedCost),
            'Estimated total / month' => $this->formatCurrency($totalCost),
        ];
    }

    protected function refreshSnapshot(): void
    {
        $lastSystem = SystemMetric::query()->latest('captured_at')->first();
        $lastQueue = QueueMetric::query()->latest('captured_at')->first();

        $this->snapshot = [
            'App env' => (string) config('app.env'),
            'Queue driver' => (string) config('queue.default'),
            'Cache driver' => (string) config('cache.default'),
            'Storage disk' => (string) config('filesystems.default'),
            'Metrics source' => (string) config('engine.metrics_source'),
            'System metrics sample' => $lastSystem?->captured_at?->toDateTimeString() ?? 'No samples',
            'Queue metrics sample' => $lastQueue?->captured_at?->toDateTimeString() ?? 'No samples',
            'Active jobs' => (string) VerificationJob::query()
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
        ];

        $this->recentJobs = VerificationJob::query()
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (VerificationJob $job): array {
                $status = $job->status instanceof BackedEnum ? $job->status->value : (string) $job->status;
                $start = $job->started_at ?? $job->created_at;
                $end = $job->finished_at ?? now();
                $durationSeconds = $start ? max(0, $start->diffInSeconds($end, false)) : 0;

                return [
                    'id' => $job->id,
                    'status' => $status ?: '-',
                    'created' => $job->created_at?->toDateTimeString() ?? '-',
                    'started' => $job->started_at?->toDateTimeString() ?? '-',
                    'finished' => $job->finished_at?->toDateTimeString() ?? '-',
                    'duration' => $job->finished_at ? $this->formatDuration($durationSeconds) : $this->formatDuration($durationSeconds) . ' (running)',
                    'total' => $job->total_emails ?? '-',
                    'cached' => $job->cached_count ?? '-',
                    'valid' => $job->valid_count ?? '-',
                    'invalid' => $job->invalid_count ?? '-',
                    'risky' => $job->risky_count ?? '-',
                ];
            })
            ->toArray();
    }

    protected static function isEnabled(): bool
    {
        $settings = GlobalSetting::query()->first();

        $enabled = $settings?->devtools_enabled;
        $envList = $settings?->devtools_environments;

        if ($enabled === null) {
            $enabled = (bool) config('devtools.enabled');
        }

        if ($envList === null || $envList === '') {
            $envList = config('devtools.allowed_environments', []);
        } else {
            $envList = array_values(array_filter(array_map('trim', explode(',', $envList))));
        }

        if (! $enabled) {
            return false;
        }

        if (! is_array($envList) || $envList === []) {
            return app()->environment('local');
        }

        return app()->environment($envList);
    }

    protected function formatNumber(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', ',');
    }

    protected function formatDuration(float $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        if ($seconds < 60) {
            return $this->formatNumber($seconds, 1) . ' s';
        }

        if ($seconds < 3600) {
            return $this->formatNumber($seconds / 60, 1) . ' min';
        }

        return $this->formatNumber($seconds / 3600, 2) . ' hr';
    }

    protected function formatCurrency(float $value): string
    {
        return $this->formatNumber($value, 2);
    }
}
