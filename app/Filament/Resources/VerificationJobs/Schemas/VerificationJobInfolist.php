<?php

namespace App\Filament\Resources\VerificationJobs\Schemas;

use App\Enums\VerificationJobStatus;
use Filament\Infolists\Components\TextEntry;
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
                        TextEntry::make('output_disk')
                            ->label('Output disk')
                            ->placeholder('-'),
                        TextEntry::make('output_key')
                            ->label('Output key')
                            ->placeholder('-')
                            ->copyable(),
                    ])
                    ->columns(2),
                Section::make('Results')
                    ->schema([
                        TextEntry::make('total_emails')
                            ->label('Total emails')
                            ->placeholder('-'),
                        TextEntry::make('valid_count')
                            ->label('Valid')
                            ->placeholder('-'),
                        TextEntry::make('invalid_count')
                            ->label('Invalid')
                            ->placeholder('-'),
                        TextEntry::make('risky_count')
                            ->label('Risky')
                            ->placeholder('-'),
                        TextEntry::make('unknown_count')
                            ->label('Unknown')
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make('Error')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Error message')
                            ->prose(),
                    ])
                    ->visible(fn ($record): bool => filled($record?->error_message)),
            ]);
    }
}
