<?php

namespace App\Filament\Resources\VerificationJobs\Schemas;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Filament\Resources\VerificationJobChunks\VerificationJobChunkResource;
use App\Models\SmtpDecisionTrace;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Support\JobProgressCalculator;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Job ID')
                            ->copyable(),
                        TextEntry::make('user.email')
                            ->label('User')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(function ($state): string {
                                if ($state instanceof VerificationJobStatus) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(function ($state): string {
                                $value = $state instanceof VerificationJobStatus ? $state->value : (string) $state;

                                return match ($value) {
                                    VerificationJobStatus::Pending->value => 'warning',
                                    VerificationJobStatus::Processing->value => 'info',
                                    VerificationJobStatus::Completed->value => 'success',
                                    VerificationJobStatus::Failed->value => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('verification_mode')
                            ->label('Mode')
                            ->badge()
                            ->formatStateUsing(function ($state): string {
                                if ($state instanceof VerificationMode) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(fn (): string => 'gray'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('started_at')
                            ->label('Started')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('finished_at')
                            ->label('Finished')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Files')
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('Original filename')
                            ->placeholder('-'),
                        TextEntry::make('input_disk')
                            ->label('Input disk')
                            ->placeholder('-'),
                        TextEntry::make('input_key')
                            ->label('Input key')
                            ->copyable(),
                        TextEntry::make('output_key')
                            ->label('Output key')
                            ->placeholder('-')
                            ->copyable(),
                    ])
                    ->columns(2),
                Section::make('Engine & Outputs')
                    ->schema([
                        TextEntry::make('engineServer.name')
                            ->label('Engine server')
                            ->formatStateUsing(function ($state, $record): string {
                                if (! $record?->engineServer) {
                                    return '-';
                                }

                                return sprintf('%s (%s)', $record->engineServer->name, $record->engineServer->ip_address);
                            }),
                        TextEntry::make('engineServer.last_heartbeat_at')
                            ->label('Last heartbeat')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('claimed_at')
                            ->label('Claimed at')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('claim_expires_at')
                            ->label('Lease expires')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('finalization_status')
                            ->label('Finalization status')
                            ->badge()
                            ->state(fn (VerificationJob $record): string => self::finalizationStatus($record))
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (VerificationJob $record): string => self::finalizationColor(self::finalizationStatus($record))),
                        TextEntry::make('output_disk')
                            ->label('Output disk')
                            ->placeholder('-'),
                        TextEntry::make('valid_key')
                            ->label('Valid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('invalid_key')
                            ->label('Invalid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('risky_key')
                            ->label('Risky key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('total_emails')
                            ->label('Total emails')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('valid_count')
                            ->label('Valid')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('invalid_count')
                            ->label('Invalid')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('risky_count')
                            ->label('Risky')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('finished_at')
                            ->label('Finished')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make('Progress & Metrics')
                    ->schema([
                        ViewEntry::make('progress')
                            ->label('Progress')
                            ->state(fn (VerificationJob $record): int => JobProgressCalculator::progressPercent($record))
                            ->view('filament.infolists.progress-bar')
                            ->columnSpanFull(),
                        TextEntry::make('metrics.phase')
                            ->label('Phase')
                            ->formatStateUsing(fn ($state, VerificationJob $record): string => JobProgressCalculator::phaseLabel($record))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('metrics.processed_emails')
                            ->label('Processed')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.total_emails')
                            ->label('Total')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.cache_hit_count')
                            ->label('Cache hits')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.cache_miss_count')
                            ->label('Cache misses')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.screening_total_count')
                            ->label('Screening total')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.probe_candidate_count')
                            ->label('Probe candidates')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.probe_completed_count')
                            ->label('Probe completed')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.probe_unknown_count')
                            ->label('Probe unknown')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.writeback_written_count')
                            ->label('Write-back written')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.writeback_attempted_count')
                            ->label('Write-back attempted')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('metrics.writeback_status')
                            ->label('Write-back status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? ucfirst((string) $state) : '-')
                            ->color(function ($state): string {
                                return match ((string) $state) {
                                    'completed' => 'success',
                                    'running' => 'warning',
                                    'queued' => 'info',
                                    'failed' => 'danger',
                                    'skipped', 'disabled' => 'gray',
                                    default => 'gray',
                                };
                            })
                            ->placeholder('-'),
                        TextEntry::make('metrics.writeback_finished_at')
                            ->label('Write-back finished')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('metrics.writeback_last_error')
                            ->label('Write-back error')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('metrics.peak_cpu_percent')
                            ->label('Peak CPU %')
                            ->placeholder('-'),
                        TextEntry::make('metrics.peak_memory_mb')
                            ->label('Peak memory (MB)')
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make('SMTP Decision Trace (Internal)')
                    ->schema([
                        TextEntry::make('trace_total')
                            ->label('Trace rows')
                            ->state(fn (VerificationJob $record): int => SmtpDecisionTrace::query()
                                ->where('verification_job_id', (string) $record->id)
                                ->count())
                            ->numeric(),
                        TextEntry::make('trace_unknown')
                            ->label('Unknown traces')
                            ->state(fn (VerificationJob $record): int => SmtpDecisionTrace::query()
                                ->where('verification_job_id', (string) $record->id)
                                ->where('decision_class', 'unknown')
                                ->count())
                            ->numeric(),
                        TextEntry::make('trace_undeliverable')
                            ->label('Undeliverable traces')
                            ->state(fn (VerificationJob $record): int => SmtpDecisionTrace::query()
                                ->where('verification_job_id', (string) $record->id)
                                ->where('decision_class', 'undeliverable')
                                ->count())
                            ->numeric(),
                        TextEntry::make('trace_latest_policy')
                            ->label('Latest policy version')
                            ->state(fn (VerificationJob $record): string => (string) (SmtpDecisionTrace::query()
                                ->where('verification_job_id', (string) $record->id)
                                ->whereNotNull('policy_version')
                                ->latest('observed_at')
                                ->value('policy_version') ?? '-')),
                        TextEntry::make('trace_latest_strategy')
                            ->label('Latest session strategy')
                            ->state(fn (VerificationJob $record): string => (string) (SmtpDecisionTrace::query()
                                ->where('verification_job_id', (string) $record->id)
                                ->whereNotNull('session_strategy_id')
                                ->latest('observed_at')
                                ->value('session_strategy_id') ?? '-')),
                        TextEntry::make('trace_top_reason_tags')
                            ->label('Top reason tags')
                            ->state(function (VerificationJob $record): string {
                                $tags = SmtpDecisionTrace::query()
                                    ->where('verification_job_id', (string) $record->id)
                                    ->whereNotNull('reason_tag')
                                    ->selectRaw('reason_tag, COUNT(*) as aggregate_count')
                                    ->groupBy('reason_tag')
                                    ->orderByDesc('aggregate_count')
                                    ->limit(3)
                                    ->get()
                                    ->map(fn ($row): string => sprintf('%s (%d)', (string) $row->reason_tag, (int) $row->aggregate_count))
                                    ->values()
                                    ->all();

                                if ($tags === []) {
                                    return '-';
                                }

                                return implode(', ', $tags);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Pipeline Breakdown (Internal)')
                    ->schema([
                        TextEntry::make('total_emails')
                            ->label('Total unique')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('cached_count')
                            ->label('Cache hits')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('unknown_count')
                            ->label('Sent to engine')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('cache_hit_rate')
                            ->label('Cache hit rate')
                            ->state(function (VerificationJob $record): string {
                                $total = (int) ($record->total_emails ?? 0);
                                if ($total <= 0) {
                                    return '-';
                                }

                                $cached = (int) ($record->cached_count ?? 0);
                                $percent = ($cached / $total) * 100;

                                return sprintf('%0.1f%% (%d/%d)', $percent, $cached, $total);
                            }),
                    ])
                    ->columns(4),
                Section::make('Cached Files')
                    ->schema([
                        TextEntry::make('cached_valid_key')
                            ->label('Cached valid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('cached_invalid_key')
                            ->label('Cached invalid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('cached_risky_key')
                            ->label('Cached risky key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('cached_notice')
                            ->label('Cached files')
                            ->state(function (VerificationJob $record): string {
                                $hasKeys = filled($record->cached_valid_key)
                                    || filled($record->cached_invalid_key)
                                    || filled($record->cached_risky_key);

                                return $hasKeys ? __('Cached files stored.') : __('No cached result files (counts only).');
                            })
                            ->visible(function (VerificationJob $record): bool {
                                return empty($record->cached_valid_key)
                                    && empty($record->cached_invalid_key)
                                    && empty($record->cached_risky_key);
                            }),
                    ])
                    ->columns(2),
                Section::make('Chunk Summary')
                    ->schema([
                        TextEntry::make('chunk_total')
                            ->label('Total chunks')
                            ->state(fn (VerificationJob $record): int => self::chunkSummary($record)['total'])
                            ->numeric(),
                        TextEntry::make('chunk_completed')
                            ->label('Completed')
                            ->state(fn (VerificationJob $record): int => self::chunkSummary($record)['completed'])
                            ->numeric(),
                        TextEntry::make('chunk_pending')
                            ->label('Pending')
                            ->state(fn (VerificationJob $record): int => self::chunkSummary($record)['pending'])
                            ->numeric(),
                        TextEntry::make('chunk_processing')
                            ->label('Processing')
                            ->state(fn (VerificationJob $record): int => self::chunkSummary($record)['processing'])
                            ->numeric(),
                        TextEntry::make('chunk_failed')
                            ->label('Failed')
                            ->state(fn (VerificationJob $record): int => self::chunkSummary($record)['failed'])
                            ->numeric(),
                        TextEntry::make('chunk_link')
                            ->label('Chunks')
                            ->state(__('View all chunks'))
                            ->url(function (VerificationJob $record): string {
                                return VerificationJobChunkResource::getUrl('index', [
                                    'tableFilters' => [
                                        'job_id' => [
                                            'job_id' => (string) $record->id,
                                        ],
                                    ],
                                ]);
                            })
                            ->openUrlInNewTab(),
                    ])
                    ->columns(3),
                Section::make('SG6 Seed Send')
                    ->schema([
                        TextEntry::make('seed_send_consent_status')
                            ->label('Consent status')
                            ->state(function (VerificationJob $record): string {
                                $consent = $record->seedSendConsents()
                                    ->latest('id')
                                    ->first();

                                if (! $consent) {
                                    return 'Not requested';
                                }

                                if ($consent->status === 'approved' && $consent->expires_at && $consent->expires_at->lte(now())) {
                                    return 'Expired';
                                }

                                return ucfirst($consent->status);
                            })
                            ->badge()
                            ->color(function (VerificationJob $record): string {
                                $consent = $record->seedSendConsents()->latest('id')->first();
                                $status = $consent?->status;

                                if ($status === 'approved' && $consent?->expires_at && $consent->expires_at->lte(now())) {
                                    return 'danger';
                                }

                                return match ($status) {
                                    'approved' => 'success',
                                    'requested' => 'warning',
                                    'revoked', 'rejected' => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('seed_send_campaign_status')
                            ->label('Campaign status')
                            ->state(function (VerificationJob $record): string {
                                $campaign = $record->seedSendCampaigns()
                                    ->latest('created_at')
                                    ->first();

                                return $campaign ? ucfirst($campaign->status) : 'Not started';
                            })
                            ->badge()
                            ->color(function (VerificationJob $record): string {
                                $status = $record->seedSendCampaigns()->latest('created_at')->value('status');

                                return match ($status) {
                                    'running', 'queued', 'pending' => 'warning',
                                    'completed' => 'success',
                                    'paused' => 'gray',
                                    'failed', 'cancelled' => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('seed_send_campaign_counts')
                            ->label('Delivered / Bounced / Deferred')
                            ->state(function (VerificationJob $record): string {
                                $campaign = $record->seedSendCampaigns()->latest('created_at')->first();
                                if (! $campaign) {
                                    return '-';
                                }

                                return sprintf(
                                    '%d / %d / %d',
                                    (int) $campaign->delivered_count,
                                    (int) $campaign->bounced_count,
                                    (int) $campaign->deferred_count
                                );
                            }),
                        TextEntry::make('seed_send_campaign_credits')
                            ->label('Credits reserved / used')
                            ->state(function (VerificationJob $record): string {
                                $campaign = $record->seedSendCampaigns()->latest('created_at')->first();
                                if (! $campaign) {
                                    return '-';
                                }

                                return sprintf(
                                    '%d / %d',
                                    (int) $campaign->credits_reserved,
                                    (int) $campaign->credits_used
                                );
                            }),
                        TextEntry::make('seed_send_report')
                            ->label('Evidence report')
                            ->state(function (VerificationJob $record): string {
                                $campaign = $record->seedSendCampaigns()->latest('created_at')->first();

                                return $campaign && $campaign->report_key ? 'Download SG6 report' : 'Not generated';
                            })
                            ->url(function (VerificationJob $record): ?string {
                                $campaign = $record->seedSendCampaigns()->latest('created_at')->first();
                                if (! $campaign || ! $campaign->report_key) {
                                    return null;
                                }

                                return route('portal.jobs.seed-send-report', [
                                    'job' => $record,
                                    'campaign_id' => $campaign->id,
                                ]);
                            })
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),
                Section::make('Recent Logs')
                    ->schema([
                        RepeatableEntry::make('activity')
                            ->label('Recent Logs')
                            ->state(function (VerificationJob $record): array {
                                $logs = $record->logs()
                                    ->latest()
                                    ->limit(50)
                                    ->get();

                                if (! $logs) {
                                    return [];
                                }

                                return $logs->map(function ($log): array {
                                    return [
                                        'level' => data_get($log->context, 'level', __('info')),
                                        'event' => $log->event,
                                        'message' => $log->message,
                                        'context' => $log->context,
                                        'actor' => $log->user?->email ?: __('System'),
                                        'created_at' => $log->created_at,
                                    ];
                                })->all();
                            })
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Time')
                                    ->dateTime(),
                                TextEntry::make('level')
                                    ->label('Level')
                                    ->badge(),
                                TextEntry::make('event')
                                    ->label('Event')
                                    ->badge(),
                                TextEntry::make('message')
                                    ->label('Message')
                                    ->placeholder('-')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('context')
                                    ->label('Context')
                                    ->formatStateUsing(function ($state): string {
                                        if (empty($state)) {
                                            return '-';
                                        }

                                        return json_encode($state, JSON_UNESCAPED_SLASHES);
                                    })
                                    ->limit(80)
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('actor')
                                    ->label('Actor')
                                    ->placeholder('-'),
                            ])
                            ->columns(['default' => 1, 'md' => 3])
                            ->placeholder('No logs recorded yet.'),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
                Section::make('Error')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Error message')
                            ->prose(),
                    ])
                    ->visible(fn ($record): bool => filled($record?->error_message)),
            ]);
    }

    private static function finalizationStatus(VerificationJob $record): string
    {
        $hasFinalKeys = filled($record->valid_key)
            && filled($record->invalid_key)
            && filled($record->risky_key);

        if ($record->status === VerificationJobStatus::Failed) {
            return 'failed';
        }

        if ($record->status === VerificationJobStatus::Completed) {
            return $hasFinalKeys ? 'completed' : 'incomplete';
        }

        if ($record->status === VerificationJobStatus::Processing) {
            return 'waiting';
        }

        return 'pending';
    }

    private static function finalizationColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'incomplete' => 'warning',
            'waiting' => 'info',
            default => 'gray',
        };
    }

    private static function chunkSummary(VerificationJob $record): array
    {
        static $cache = [];

        $jobId = (string) $record->id;

        if (isset($cache[$jobId])) {
            return $cache[$jobId];
        }

        $counts = VerificationJobChunk::query()
            ->where('verification_job_id', $record->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        return $cache[$jobId] = [
            'total' => (int) ($counts->total ?? 0),
            'completed' => (int) ($counts->completed ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'processing' => (int) ($counts->processing ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
        ];
    }
}
