<?php

namespace App\Filament\Resources\FeedbackImports\Tables;

use App\Models\EmailVerificationOutcomeImport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeedbackImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Import ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmailVerificationOutcomeImport::STATUS_COMPLETED => 'success',
                        EmailVerificationOutcomeImport::STATUS_FAILED => 'danger',
                        EmailVerificationOutcomeImport::STATUS_PROCESSING => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('source')
                    ->label('Source')
                    ->toggleable(),
                TextColumn::make('imported_count')
                    ->label('Imported')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('skipped_count')
                    ->label('Skipped')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('error_sample')
                    ->label('Error sample')
                    ->formatStateUsing(function ($state): string {
                        if (empty($state)) {
                            return '-';
                        }

                        return json_encode($state, JSON_UNESCAPED_SLASHES);
                    })
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Finished')
                    ->since()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No feedback imports yet')
            ->emptyStateDescription('Imports will appear here once queued.');
    }
}
