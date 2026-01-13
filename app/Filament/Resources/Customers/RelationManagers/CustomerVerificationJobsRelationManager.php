<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\VerificationJobStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerVerificationJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'verificationJobs';

    protected static ?string $title = 'Verification Jobs';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->excludeAdminFailures())
            ->columns([
                TextColumn::make('id')
                    ->label('Job ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('original_filename')
                    ->label('Filename')
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
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
