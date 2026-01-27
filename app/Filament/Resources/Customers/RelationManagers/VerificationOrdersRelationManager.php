<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Models\VerificationOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VerificationOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'verificationOrders';

    protected static ?string $title = 'Products/Services';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-cube';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->verificationOrders()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->sortable()
                    ->color('warning')
                    ->weight('bold')
                    ->url(fn(VerificationOrder $record): string => VerificationOrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('order_number')
                    ->label('Order Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Requestor')
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->state(fn(VerificationOrder $record): string => $record->paymentMethodLabel()),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, VerificationOrder $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = $state !== null ? ((int) $state) / 100 : 0;
                        return sprintf('%s %.2f', $currency, $amount);
                    }),
                TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->badge()
                    ->state(fn(VerificationOrder $record): string => $record->paymentStatusLabel())
                    ->color(function (VerificationOrder $record): string {
                        return match ($record->paymentStatusKey()) {
                            'paid' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'warning',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('status')
                    ->label('Order Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof VerificationOrderStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn($state) => match ($state instanceof VerificationOrderStatus ? $state->value : (string) $state) {
                        VerificationOrderStatus::Pending->value => 'warning',
                        VerificationOrderStatus::Processing->value => 'info',
                        VerificationOrderStatus::Delivered->value => 'success',
                        VerificationOrderStatus::Failed->value, VerificationOrderStatus::Fraud->value => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->url(fn($record) => VerificationOrderResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
