<?php

namespace App\Filament\Widgets;

use App\Models\QueueIncident;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpsOpenQueueIncidentsTable extends TableWidget
{
    protected static ?string $heading = 'Open Queue Incidents';

    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('issue_key')
                    ->label('Issue Key')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'critical' ? 'danger' : 'warning'),
                TextColumn::make('status')
                    ->label('Lifecycle')
                    ->badge(),
                TextColumn::make('lane')
                    ->label('Lane')
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label('Title')
                    ->wrap(),
                TextColumn::make('last_detected_at')
                    ->label('Last Seen')
                    ->since(),
                TextColumn::make('first_detected_at')
                    ->label('First Seen')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultPaginationPageOption(10);
    }

    private function getQuery(): Builder
    {
        return QueueIncident::query()
            ->whereNull('resolved_at')
            ->latest('last_detected_at');
    }
}
