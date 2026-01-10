<?php

namespace App\Filament\Resources\RetentionSettings\Tables;

use App\Models\RetentionSetting;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class RetentionSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('retention_days')
                    ->label('Retention days')
                    ->numeric(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime(),
            ])
            ->emptyStateHeading('No retention settings yet')
            ->emptyStateDescription('Create retention settings to control cleanup windows.')
            ->recordActions([
                Action::make('preview_purge')
                    ->label('Preview purge')
                    ->requiresConfirmation()
                    ->action(function (RetentionSetting $record): void {
                        Artisan::call('app:purge-verification-jobs', [
                            '--days' => $record->retention_days,
                            '--dry-run' => true,
                        ]);

                        $output = trim(Artisan::output());

                        Notification::make()
                            ->title('Purge preview')
                            ->body($output ?: 'Preview complete.')
                            ->success()
                            ->send();

                        AdminAuditLogger::log('retention_preview', $record, [
                            'retention_days' => $record->retention_days,
                        ]);
                    }),
                EditAction::make(),
            ]);
    }
}
