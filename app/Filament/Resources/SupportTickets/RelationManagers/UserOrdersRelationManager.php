<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
// The correct import for your project structure
use Filament\Actions\Action;
use App\Enums\VerificationOrderStatus;

class UserOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'userOrders';

    protected static ?string $title = 'Customer Order History';

    /**
     * This makes the whole section collapsible in the View page
     */
    public static function isCollapsedByDefault(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Customer Orders')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->fontFamily('mono')
                    ->searchable()
                    // This links to the Verification Orders List and filters by this ID
                    ->url(fn($record) => "/admin/verification-orders?tableSearch=" . urlencode($record->id))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('email_count')
                    ->label('Emails')
                    ->numeric()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof VerificationOrderStatus) {
                            return $state->label();
                        }

                        return ucfirst((string) $state);
                    })
                    ->color(function ($state): string {
                        $value = $state instanceof VerificationOrderStatus ? $state->value : (string) $state;

                        return match ($value) {
                            VerificationOrderStatus::Pending->value => 'warning',
                            VerificationOrderStatus::Processing->value => 'info',
                            VerificationOrderStatus::Delivered->value => 'success',
                            VerificationOrderStatus::Failed->value => 'danger',
                            VerificationOrderStatus::Cancelled->value => 'gray',
                            VerificationOrderStatus::Fraud->value => 'danger',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->money('usd', divideBy: 100)
                    ->alignment('right'),
            ])
            ->actions([
                // Using the corrected Action class
                Action::make('view_order')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn($record) => route('filament.admin.resources.verification-orders.view', $record)),
            ]);
    }
    public function isCollapsible(): bool
    {
        return true;
    }
}
