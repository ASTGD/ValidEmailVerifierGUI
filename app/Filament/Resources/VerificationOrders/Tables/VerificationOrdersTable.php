<?php

namespace App\Filament\Resources\VerificationOrders\Tables;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Jobs\PrepareVerificationJob;
use App\Models\VerificationJob;
use App\Models\VerificationOrder;
use App\Services\JobStorage;
use App\Services\OrderStorage;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class VerificationOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'checkoutIntent']))
            ->recordAction(null)
            ->recordUrl(null)
            ->searchable(false)
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->url(fn (VerificationOrder $record): string => VerificationOrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('order_number')
                    ->label('Order Number')
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('Click to copy order number')),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Client Name')
                    ->formatStateUsing(function ($state, VerificationOrder $record): string {
                        return $record->user?->name ?: ($record->user?->email ?: '-');
                    })
                    ->searchable(['user.name', 'user.email'])
                    ->url(function (VerificationOrder $record): ?string {
                        if (! $record->user) {
                            return null;
                        }

                        return CustomerResource::getUrl('view', ['record' => $record->user]);
                    }),
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->state(fn (VerificationOrder $record): string => $record->paymentMethodLabel()),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, VerificationOrder $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = $state !== null ? ((int) $state) / 100 : 0;

                        return sprintf('%s %.2f', $currency, $amount);
                    })
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->badge()
                    ->state(fn (VerificationOrder $record): string => $record->paymentStatusLabel())
                    ->color(function (VerificationOrder $record): string {
                        return match ($record->paymentStatusKey()) {
                            'paid' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('checkout_intents', 'verification_orders.checkout_intent_id', '=', 'checkout_intents.id')
                            ->orderByRaw("
                                case
                                    when verification_orders.refunded_at is not null then 4
                                    when checkout_intents.status = ? then 1
                                    when checkout_intents.status = ? then 2
                                    when checkout_intents.status in (?, ?) then 3
                                    else 5
                                end {$direction}
                            ", [
                                CheckoutIntentStatus::Completed->value,
                                CheckoutIntentStatus::Pending->value,
                                CheckoutIntentStatus::Expired->value,
                                CheckoutIntentStatus::Canceled->value,
                            ])
                            ->select('verification_orders.*');
                    }),
                TextColumn::make('status')
                    ->label('Order Status')
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
                    })
                    ->sortable(),
            ])
            ->filters([
                Filter::make('advanced')
                    ->label('Search/Filter')
                    ->columnSpanFull()
                    ->form([
                        Group::make([
                            Group::make([
                                TextInput::make('order_id')
                                    ->label('Order ID')
                                    ->numeric()
                                    ->inputMode('numeric'),
                                TextInput::make('order_number')
                                    ->label('Order Number')
                                    ->placeholder('Enter order number'),
                                Group::make([
                                    DatePicker::make('date_from')
                                        ->label('Date From'),
                                    DatePicker::make('date_to')
                                        ->label('Date To'),
                                ])->gridContainer()->columns(2),
                                Group::make([
                                    TextInput::make('amount_min')
                                        ->label('Amount Min')
                                        ->numeric()
                                        ->inputMode('decimal')
                                        ->placeholder('0.00'),
                                    TextInput::make('amount_max')
                                        ->label('Amount Max')
                                        ->numeric()
                                        ->inputMode('decimal')
                                        ->placeholder('0.00'),
                                ])->gridContainer()->columns(2),
                            ])->columnSpan(1),
                            Group::make([
                                Select::make('user_id')
                                    ->label('Client')
                                    ->searchable()
                                    ->placeholder('Start typing to search clients')
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return \App\Models\User::query()
                                            ->role(\App\Support\Roles::CUSTOMER)
                                            ->where(function (Builder $query) use ($search): void {
                                                $query->where('name', 'like', '%' . $search . '%')
                                                    ->orWhere('email', 'like', '%' . $search . '%');
                                            })
                                            ->limit(20)
                                            ->get()
                                            ->mapWithKeys(function (\App\Models\User $user): array {
                                                $label = $user->name ? "{$user->name} ({$user->email})" : $user->email;

                                                return [$user->id => $label];
                                            })
                                            ->all();
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (! $value) {
                                            return null;
                                        }

                                        $user = \App\Models\User::query()->find($value);

                                        if (! $user) {
                                            return null;
                                        }

                                        return $user->name ? "{$user->name} ({$user->email})" : $user->email;
                                    }),
                                Select::make('payment_status')
                                    ->label('Payment Status')
                                    ->placeholder('Any')
                                    ->options([
                                        'paid' => __('Paid'),
                                        'unpaid' => __('Unpaid'),
                                        'failed' => __('Failed'),
                                        'refunded' => __('Refunded'),
                                    ]),
                                Select::make('order_status')
                                    ->label('Order Status')
                                    ->placeholder('Any')
                                    ->options(self::statusOptions()),
                            ])->columnSpan(1),
                        ])
                            ->gridContainer()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'style' => 'max-width: 64rem; margin: 0 auto; gap: 2.5rem;',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $orderId = $data['order_id'] ?? null;
                        if (filled($orderId)) {
                            $query->where('verification_orders.id', (int) $orderId);
                        }

                        $orderNumber = trim((string) ($data['order_number'] ?? ''));
                        if ($orderNumber !== '') {
                            $query->where('order_number', 'like', '%' . $orderNumber . '%');
                        }

                        $dateFrom = $data['date_from'] ?? null;
                        if ($dateFrom) {
                            $query->whereDate('created_at', '>=', $dateFrom);
                        }

                        $dateTo = $data['date_to'] ?? null;
                        if ($dateTo) {
                            $query->whereDate('created_at', '<=', $dateTo);
                        }

                        $amountMin = $data['amount_min'] ?? null;
                        if (filled($amountMin)) {
                            $query->where('amount_cents', '>=', (int) round(((float) $amountMin) * 100));
                        }

                        $amountMax = $data['amount_max'] ?? null;
                        if (filled($amountMax)) {
                            $query->where('amount_cents', '<=', (int) round(((float) $amountMax) * 100));
                        }

                        $userId = $data['user_id'] ?? null;
                        if ($userId) {
                            $query->where('user_id', $userId);
                        }

                        $paymentStatus = $data['payment_status'] ?? null;
                        if ($paymentStatus) {
                            if ($paymentStatus === 'refunded') {
                                $query->whereNotNull('refunded_at');
                            } elseif ($paymentStatus === 'paid') {
                                $query->whereNull('refunded_at')
                                    ->whereHas('checkoutIntent', fn (Builder $intent) => $intent->where('status', CheckoutIntentStatus::Completed->value));
                            } elseif ($paymentStatus === 'failed') {
                                $query->whereHas('checkoutIntent', fn (Builder $intent) => $intent->whereIn('status', [
                                    CheckoutIntentStatus::Expired->value,
                                    CheckoutIntentStatus::Canceled->value,
                                ]));
                            } else {
                                $query->where(function (Builder $sub) {
                                    $sub->whereNull('checkout_intent_id')
                                        ->orWhereHas('checkoutIntent', fn (Builder $intent) => $intent->where('status', CheckoutIntentStatus::Pending->value));
                                });
                            }
                        }

                        $orderStatus = $data['order_status'] ?? null;
                        if ($orderStatus) {
                            $query->where('status', $orderStatus);
                        }

                        return $query;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersTriggerAction(fn (Action $action): Action => $action->label(__('Search/Filter'))->button())
            ->filtersApplyAction(fn (Action $action): Action => $action->label(__('Search')))
            ->filtersFormColumns(1)
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders will appear here once customers complete checkout.')
            ->recordActions([
                ViewAction::make(),
                Action::make('activate')
                    ->label('Activate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(function (VerificationOrder $record): bool {
                        return $record->verification_job_id
                            || ! $record->input_disk
                            || ! $record->input_key;
                    })
                    ->tooltip(function (VerificationOrder $record): ?string {
                        if ($record->verification_job_id) {
                            return 'Order already activated.';
                        }

                        if (! $record->input_disk || ! $record->input_key) {
                            return 'Upload an email list before activating.';
                        }

                        return null;
                    })
                    ->action(function (VerificationOrder $record): void {
                        if ($record->verification_job_id) {
                            Notification::make()
                                ->warning()
                                ->title('Order already activated')
                                ->body('This order is already linked to a verification job.')
                                ->send();

                            return;
                        }

                        if (! $record->input_disk || ! $record->input_key) {
                            Notification::make()
                                ->danger()
                                ->title('Input file missing')
                                ->body('Upload an email list before activating this order.')
                                ->send();

                            return;
                        }

                        $jobStorage = app(JobStorage::class);
                        $orderStorage = app(OrderStorage::class);

                        $job = new VerificationJob([
                            'user_id' => $record->user_id,
                            'status' => VerificationJobStatus::Pending,
                            'verification_mode' => VerificationMode::Standard->value,
                            'original_filename' => $record->original_filename,
                        ]);
                        $job->id = (string) Str::uuid();
                        $job->input_disk = $jobStorage->disk();
                        $job->input_key = $jobStorage->inputKey($job);
                        $job->save();

                        $orderStorage->moveToJob($record, $job, $jobStorage);

                        $job->addLog('created', 'Job activated by admin.', [
                            'order_id' => $record->id,
                        ], auth()->id());
                        $job->addLog('verification_mode_set', 'Verification mode set at job creation.', [
                            'from' => null,
                            'to' => VerificationMode::Standard->value,
                            'actor_id' => auth()->id(),
                        ], auth()->id());

                        PrepareVerificationJob::dispatch($job->id);

                        $record->update([
                            'verification_job_id' => $job->id,
                            'status' => VerificationOrderStatus::Processing,
                            'input_disk' => $job->input_disk,
                            'input_key' => $job->input_key,
                        ]);

                        AdminAuditLogger::log('order_activated', $record, [
                            'verification_job_id' => $job->id,
                        ]);
                    })
                    ->visible(fn (VerificationOrder $record): bool => $record->status === VerificationOrderStatus::Pending),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VerificationOrder $record): void {
                        if ($record->job && in_array($record->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)) {
                            $record->job->update([
                                'status' => VerificationJobStatus::Failed,
                                'error_message' => 'Order cancelled by admin.',
                                'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                                'failure_code' => 'cancelled',
                                'finished_at' => now(),
                            ]);

                            $record->job->addLog('cancelled', 'Order cancelled by admin.', [
                                'order_id' => $record->id,
                            ], auth()->id());
                        }

                        $record->update([
                            'status' => VerificationOrderStatus::Cancelled,
                        ]);

                        AdminAuditLogger::log('order_cancelled', $record);
                    })
                    ->visible(fn (VerificationOrder $record): bool => in_array($record->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)),
                Action::make('mark_fraud')
                    ->label('Mark Fraud')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VerificationOrder $record): void {
                        if ($record->job && in_array($record->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)) {
                            $record->job->update([
                                'status' => VerificationJobStatus::Failed,
                                'error_message' => 'Order flagged as fraud by admin.',
                                'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                                'failure_code' => 'fraud',
                                'finished_at' => now(),
                            ]);

                            $record->job->addLog('fraud', 'Order flagged as fraud by admin.', [
                                'order_id' => $record->id,
                            ], auth()->id());
                        }

                        $record->update([
                            'status' => VerificationOrderStatus::Fraud,
                        ]);

                        AdminAuditLogger::log('order_marked_fraud', $record);
                    })
                    ->visible(fn (VerificationOrder $record): bool => in_array($record->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)),
            ]);
    }

    private static function statusOptions(): array
    {
        $options = [];

        foreach (VerificationOrderStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
