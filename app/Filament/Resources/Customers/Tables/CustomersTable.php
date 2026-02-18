<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchable(false)
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'last_name', 'name'])
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Company Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('verification_jobs_count')
                    ->label('Services')
                    ->state(fn(User $record) => $record->verificationOrders()->count()),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'closed' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Filter::make('advanced')
                    ->label('Search/Filter')
                    ->columnSpanFull()
                    ->form([
                        Group::make([
                            Group::make([
                                TextInput::make('client_id')
                                    ->label('Client ID')
                                    ->numeric(),
                                TextInput::make('client_name')
                                    ->label('Client/Company Name')
                                    ->placeholder('Enter name or company'),
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->placeholder('Enter email'),
                            ])->columnSpan(1),
                            Group::make([
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->placeholder('Enter phone number'),
                                Select::make('client_group')
                                    ->label('Client Group')
                                    ->placeholder('Any')
                                    ->options([
                                        'none' => 'None',
                                        'vip' => 'VIP',
                                        'reseller' => 'Reseller',
                                    ]),
                                Select::make('status')
                                    ->label('Status')
                                    ->placeholder('Any')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'closed' => 'Closed',
                                    ]),
                            ])->columnSpan(1),
                            // ACTIVE CLIENT TOGGLE - BEFORE SEARCH BUTTON (as requested)
                            Group::make([
                                Toggle::make('only_active')
                                    ->label('Only Active Clients')
                                    ->default(false)
                                    ->inline(false),
                            ])->columnSpanFull(),
                        ])
                            ->gridContainer()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'style' => 'max-width: 64rem; margin: 0 auto; gap: 2.5rem;',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['client_id'], fn(Builder $q, $val) => $q->where('id', $val))
                            ->when($data['client_name'], fn(Builder $q, $val) => $q->where(
                                fn($sq) =>
                                $sq->where('first_name', 'like', "%{$val}%")
                                    ->orWhere('last_name', 'like', "%{$val}%")
                                    ->orWhere('company_name', 'like', "%{$val}%")
                                    ->orWhere('name', 'like', "%{$val}%")
                            ))
                            ->when($data['email'], fn(Builder $q, $val) => $q->where('email', 'like', "%{$val}%"))
                            ->when($data['phone'], fn(Builder $q, $val) => $q->where('phone', 'like', "%{$val}%"))
                            ->when($data['client_group'], fn(Builder $q, $val) => $q->where('client_group', $val))
                            ->when($data['status'], fn(Builder $q, $val) => $q->where('status', $val))
                            ->when($data['only_active'], fn(Builder $q) => $q->where('status', 'active'));
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersTriggerAction(fn(Action $action): Action => $action->label(__('Search/Filter'))->button())
            ->filtersApplyAction(fn(Action $action): Action => $action->label(__('Search'))->color('warning'))
            ->filtersFormColumns(1)
            ->actions([
                Action::make('add_credit')
                    ->label('Add Credit')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->prefix('$'),
                        TextInput::make('notes')
                            ->label('Notes')
                            ->placeholder('e.g. Manual credit addition'),
                    ])
                    ->action(function (User $record, array $data, \App\Services\BillingService $billing) {
                        $amountCents = (int) ($data['amount'] * 100);

                        $invoice = $billing->createInvoice($record, [
                            [
                                'description' => 'Credit Addition: ' . ($data['notes'] ?: 'Manual'),
                                'amount' => $amountCents,
                                'type' => 'Credit',
                            ]
                        ], [
                            'status' => 'Paid',
                            'date' => now(),
                            'paid_at' => now(),
                            'notes' => $data['notes'],
                        ]);

                        $billing->recordPayment($invoice, $amountCents, 'Manual', 'Admin Adjustment');

                        \Filament\Notifications\Notification::make()
                            ->title('Credit added successfully.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No customers found')
            ->emptyStateDescription('Try adjusting your search or filters.');
    }
}

