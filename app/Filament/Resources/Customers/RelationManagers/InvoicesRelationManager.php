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
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-banknotes';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->invoices()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice Number')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('warning'),
                TextColumn::make('date')
                    ->label('Invoice Date')
                    ->sortable()
                    ->dateTime(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Date Paid')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(function ($state, \App\Models\Invoice $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = ((int) $state) / 100;
                        return sprintf('%s %.2f', $currency, $amount);
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Unpaid' => 'warning',
                        'Cancelled' => 'danger',
                        'Refunded' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
