<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Credit;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreditsRelationManager extends RelationManager
{
    protected static string $relationship = 'credits';

    protected static ?string $title = 'Credits';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-credit-card';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->credits()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'manual' => 'info',
                        'purchase' => 'success',
                        'usage' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, Credit $record): string {
                        $user = $record->user;
                        $currency = strtoupper((string) ($user->currency ?: 'usd'));
                        $amount = ((int) $state) / 100;
                        return sprintf('%s %.2f', $currency, $amount);
                    })
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add/Remove Credit')
                    ->modalHeading('Manage Credits')
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount (in cents)')
                            ->helperText('Use positive value to add, negative to subtract. E.g. 1000 for $10.00')
                            ->numeric()
                            ->required(),
                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->placeholder('Manual adjustment'),
                        Select::make('type')
                            ->options([
                                'manual' => 'Manual Adjustment',
                                'purchase' => 'Purchase',
                                'usage' => 'Usage',
                            ])
                            ->default('manual')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $amount = (int) $data['amount'];
                        $user = $this->getOwnerRecord();

                        $credit = Credit::create([
                            'user_id' => $user->id,
                            'amount' => $amount,
                            'description' => $data['description'],
                            'type' => $data['type'],
                        ]);

                        $user->increment('balance', $amount);

                        \App\Support\AdminAuditLogger::log('credit_adjusted', $user, [
                            'amount' => $amount,
                            'description' => $data['description'],
                            'type' => $data['type'],
                            'credit_id' => $credit->id,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Credit updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->before(function (Credit $record) {
                        $user = $record->user;
                        $user->decrement('balance', $record->amount);

                        \App\Support\AdminAuditLogger::log('credit_deleted', $user, [
                            'amount' => $record->amount,
                            'description' => $record->description,
                            'credit_id' => $record->id,
                        ]);
                    })
                    ->successNotificationTitle('Credit entry deleted and balance restored'),
            ]);
    }
}
