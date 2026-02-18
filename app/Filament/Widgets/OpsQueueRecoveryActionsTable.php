<?php

namespace App\Filament\Widgets;

use App\Models\QueueRecoveryAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpsQueueRecoveryActionsTable extends TableWidget
{
    protected static ?string $heading = 'Recent Queue Recovery Actions';

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('executed_at')
                    ->label('Executed')
                    ->since(),
                TextColumn::make('strategy')
                    ->label('Strategy')
                    ->badge(),
                TextColumn::make('lane')
                    ->label('Lane')
                    ->placeholder('any')
                    ->badge(),
                TextColumn::make('job_class')
                    ->label('Job Filter')
                    ->placeholder('any')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'success' => 'success',
                            'partial', 'dry_run' => 'warning',
                            default => 'danger',
                        };
                    }),
                TextColumn::make('target_count')
                    ->label('Target'),
                TextColumn::make('processed_count')
                    ->label('Processed'),
                TextColumn::make('failed_count')
                    ->label('Failed'),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultPaginationPageOption(10);
    }

    private function getQuery(): Builder
    {
        return QueueRecoveryAction::query()
            ->where('executed_at', '>=', now()->subDays(7))
            ->latest('executed_at');
    }
}
