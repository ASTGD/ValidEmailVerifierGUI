<?php

namespace App\Filament\Resources\SmtpPolicyVersions;

use App\Filament\Resources\SmtpPolicyVersions\Pages\ManageSmtpPolicyVersions;
use App\Models\SmtpPolicyVersion;
use App\Services\VerifierPolicy\SmtpPolicyVersionLifecycleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SmtpPolicyVersionResource extends Resource
{
    protected static ?string $model = SmtpPolicyVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'SMTP Policy Versions';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Managed by rollout APIs and validation action; no manual form fields in this phase.
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Version')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('validation_status')
                    ->label('Validation')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'valid' => 'success',
                        'invalid' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('validated_by')
                    ->label('Validated By')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('validated_at')
                    ->label('Validated At')
                    ->since()
                    ->placeholder('-'),
                TextColumn::make('promoted_at')
                    ->label('Promoted At')
                    ->since()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
            ])
            ->recordActions([
                Action::make('validate_payload')
                    ->label('Validate Payload')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (SmtpPolicyVersion $record): void {
                        $result = app(SmtpPolicyVersionLifecycleService::class)->validateAndPersist(
                            $record,
                            auth()->user()?->email
                        );

                        $errors = $result['errors'];
                        if ($errors === []) {
                            Notification::make()
                                ->title('Policy payload validated')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Policy payload is invalid')
                            ->body(implode(' ', $errors))
                            ->danger()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSmtpPolicyVersions::route('/'),
        ];
    }
}
