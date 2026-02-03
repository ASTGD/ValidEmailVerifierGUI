<?php

namespace App\Filament\Widgets;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Support\JobProgressCalculator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpsActiveJobsTable extends TableWidget
{
    protected static ?string $heading = 'Active Jobs';

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('Job ID')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('status')
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
                ViewColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (VerificationJob $record): int => JobProgressCalculator::progressPercent($record))
                    ->view('filament.columns.progress-bar')
                    ->extraCellAttributes(['class' => 'w-32']),
                TextColumn::make('phase')
                    ->label('Phase')
                    ->getStateUsing(fn (VerificationJob $record): string => JobProgressCalculator::phaseLabel($record))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('engineServer.name')
                    ->label('Engine')
                    ->formatStateUsing(fn ($state, VerificationJob $record): string => $record->engineServer
                        ? sprintf('%s (%s)', $record->engineServer->name, $record->engineServer->ip_address)
                        : '-'),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->since()
                    ->placeholder('-'),
            ])
            ->defaultPaginationPageOption(10);
    }

    private function getQuery(): Builder
    {
        return VerificationJob::query()
            ->with(['metrics', 'engineServer', 'user'])
            ->withCount([
                'chunks',
                'chunks as chunks_completed_count' => fn (Builder $chunkQuery) => $chunkQuery->where('status', 'completed'),
            ])
            ->where('status', VerificationJobStatus::Processing)
            ->latest('started_at');
    }
}
