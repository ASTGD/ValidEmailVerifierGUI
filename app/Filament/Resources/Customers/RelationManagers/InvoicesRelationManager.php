<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Models\VerificationOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'verificationOrders';

    protected static ?string $title = 'Invoices';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-banknotes';

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
                TextColumn::make('order_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->weight('bold')
                    ->color('warning')
                    ->url(fn(VerificationOrder $record): string => VerificationOrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('created_at')
                    ->label('Invoice Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Due Date')
                    ->date()
                    ->color('gray'), // Placeholder as we don't have due_date in model
                TextColumn::make('refunded_at')
                    ->label('Date Paid')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('amount_cents')
                    ->label('Total')
                    ->formatStateUsing(function ($state, VerificationOrder $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = $state !== null ? ((int) $state) / 100 : 0;
                        return sprintf('%s %.2f', $currency, $amount);
                    })
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->state(fn(VerificationOrder $record): string => $record->paymentMethodLabel()),
                TextColumn::make('payment_status')
                    ->label('Status')
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
