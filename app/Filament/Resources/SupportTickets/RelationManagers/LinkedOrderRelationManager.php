<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;

class LinkedOrderRelationManager extends RelationManager
{
    protected static string $relationship = 'linkedOrder';

    protected static ?string $title = 'Referenced Order Details';

    protected static ?string $label = 'Linked Order';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Linked Order Information')
            ->description('This is the specific order the customer is requesting support for.')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order ID')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime(),

                Tables\Columns\TextColumn::make('email_count')
                    ->label('Emails')
                    ->numeric()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state->label()) // Calls the label() method on your Enum
                    ->color(fn($state): string => match ($state->value) { // Use ->value to get the string for matching
                        'pending'   => 'warning',
                        'completed' => 'success',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->money('usd', divideBy: 100)
                    ->alignment('right'),
            ])
            ->actions([
                Action::make('view_full_order')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn($record) => "/admin/verification-orders?tableSearch=" . urlencode($record->id))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false); // No pagination needed for a single order
    }
}
