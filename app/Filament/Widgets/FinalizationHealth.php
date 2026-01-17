<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
use App\Filament\Resources\VerificationJobChunks\VerificationJobChunkResource;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinalizationHealth extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $windowDays = max(1, (int) config('engine.health_window_days', 7));
        $since = now()->subDays($windowDays);

        $failedChunkJobs = VerificationJob::query()
            ->whereHas('chunks', fn ($query) => $query->where('status', 'failed'))
            ->where('updated_at', '>=', $since)
            ->count();

        $missingOutputs = VerificationJob::query()
            ->where('status', VerificationJobStatus::Completed)
            ->where(function ($query) {
                $query->whereNull('valid_key')
                    ->orWhereNull('invalid_key')
                    ->orWhereNull('risky_key');
            })
            ->where('updated_at', '>=', $since)
            ->count();

        $readyForFinalization = VerificationJob::query()
            ->where('status', VerificationJobStatus::Processing)
            ->whereHas('chunks')
            ->whereDoesntHave('chunks', fn ($query) => $query->where('status', '!=', 'completed'))
            ->where(function ($query) {
                $query->whereNull('valid_key')
                    ->orWhereNull('invalid_key')
                    ->orWhereNull('risky_key');
            })
            ->where('updated_at', '>=', $since)
            ->count();

        $stuckChunks = VerificationJobChunk::query()
            ->where('status', 'processing')
            ->whereNotNull('claim_expires_at')
            ->where('claim_expires_at', '<', now())
            ->where('updated_at', '>=', $since)
            ->count();

        return [
            Stat::make('Jobs with failed chunks', $failedChunkJobs)
                ->description('Needs requeue or review.')
                ->color($failedChunkJobs > 0 ? 'danger' : 'gray')
                ->url(VerificationJobResource::getUrl('index', [
                    'tableFilters' => [
                        'failed_chunks' => [
                            'isActive' => true,
                        ],
                    ],
                ])),
            Stat::make('Completed jobs missing outputs', $missingOutputs)
                ->description('Final outputs incomplete.')
                ->color($missingOutputs > 0 ? 'warning' : 'gray')
                ->url(VerificationJobResource::getUrl('index', [
                    'tableFilters' => [
                        'missing_outputs' => [
                            'isActive' => true,
                        ],
                    ],
                ])),
            Stat::make('Ready for finalization', $readyForFinalization)
                ->description('Chunks complete, outputs not generated.')
                ->color($readyForFinalization > 0 ? 'warning' : 'gray')
                ->url(VerificationJobResource::getUrl('index', [
                    'tableFilters' => [
                        'ready_for_finalization' => [
                            'isActive' => true,
                        ],
                    ],
                ])),
            Stat::make('Stuck chunks (lease expired)', $stuckChunks)
                ->description('Requeue stuck chunk leases.')
                ->color($stuckChunks > 0 ? 'danger' : 'gray')
                ->url(VerificationJobChunkResource::getUrl('index', [
                    'tableFilters' => [
                        'lease_expired' => [
                            'isActive' => true,
                        ],
                    ],
                ])),
        ];
    }
}
