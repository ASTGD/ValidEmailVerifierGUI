<?php

namespace App\Filament\Widgets;

use App\Models\EngineServer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpsEngineActivityTable extends TableWidget
{
    protected static ?string $heading = 'Engine Activity';

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Engine')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_heartbeat_at')
                    ->label('Last heartbeat')
                    ->since()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (EngineServer $record): string => $record->isOnline() ? 'Online' : 'Offline')
                    ->color(fn (EngineServer $record): string => $record->isOnline() ? 'success' : 'danger'),
                TextColumn::make('drain_mode')
                    ->label('Drain mode')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'On' : 'Off')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('latestActiveJob.id')
                    ->label('Current job')
                    ->formatStateUsing(fn ($state, EngineServer $record): string => $record->latestActiveJob?->id ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('active_jobs_count')
                    ->label('Active jobs')
                    ->getStateUsing(fn (EngineServer $record): int => (int) $record->active_jobs_count),
            ])
            ->defaultPaginationPageOption(10);
    }

    private function getQuery(): Builder
    {
        return EngineServer::query()
            ->with('latestActiveJob')
            ->withCount([
                'jobs as active_jobs_count' => fn (Builder $jobQuery) => $jobQuery->where('status', 'processing'),
            ])
            ->orderByDesc('last_heartbeat_at');
    }
}
