<?php

namespace App\Filament\Resources\VerificationOrders\Schemas;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\VerificationOrder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make('Order Summary')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order Number')
                            ->copyable()
                            ->tooltip(__('Click to copy order number')),
                        TextEntry::make('id')
                            ->label('Order ID')
                            ->copyable(),
                        TextEntry::make('status')
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
                            }),
                        TextEntry::make('payment_status')
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
                            }),
                        TextEntry::make('email_count')
                            ->label('Email Count')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('amount_cents')
                            ->label('Amount')
                            ->formatStateUsing(function ($state, VerificationOrder $record): string {
                                $currency = strtoupper((string) ($record->currency ?: 'usd'));
                                $amount = $state !== null ? ((int) $state) / 100 : 0;

                                return sprintf('%s %.2f', $currency, $amount);
                            }),
                        TextEntry::make('pricingPlan.name')
                            ->label('Pricing Plan')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime(),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->columnSpanFull(),
                Section::make('Files')
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('Original File')
                            ->placeholder('-'),
                        TextEntry::make('input_disk')
                            ->label('Storage Disk')
                            ->placeholder('-'),
                        TextEntry::make('input_key')
                            ->label('Storage Key')
                            ->copyable()
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpan(['lg' => 2]),
                Section::make('Client')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Client')
                            ->formatStateUsing(function ($state, VerificationOrder $record): string {
                                return $record->user?->name ?: ($record->user?->email ?: '-');
                            })
                            ->url(function (VerificationOrder $record): ?string {
                                if (! $record->user) {
                                    return null;
                                }

                                return CustomerResource::getUrl('view', ['record' => $record->user]);
                            }),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('-'),
                    ])
                    ->columns(1)
                    ->columnSpan(['lg' => 1]),
                Section::make('Verification Job')
                    ->schema([
                        TextEntry::make('verification_job_id')
                            ->label('Job ID')
                            ->state(function (VerificationOrder $record): string {
                                return $record->verification_job_id ?: __('Not activated yet');
                            })
                            ->copyable(fn (VerificationOrder $record): bool => (bool) $record->verification_job_id)
                            ->url(function (VerificationOrder $record): ?string {
                                if (! $record->job) {
                                    return null;
                                }

                                return VerificationJobResource::getUrl('view', ['record' => $record->job]);
                            }),
                        TextEntry::make('job.status')
                            ->label('Job Status')
                            ->badge()
                            ->formatStateUsing(function ($state, VerificationOrder $record): string {
                                if (! $record->job) {
                                    return __('Not activated');
                                }

                                if ($state instanceof VerificationJobStatus) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(function ($state, VerificationOrder $record): string {
                                if (! $record->job) {
                                    return 'gray';
                                }

                                $value = $state instanceof VerificationJobStatus ? $state->value : (string) $state;

                                return match ($value) {
                                    VerificationJobStatus::Pending->value => 'warning',
                                    VerificationJobStatus::Processing->value => 'info',
                                    VerificationJobStatus::Completed->value => 'success',
                                    VerificationJobStatus::Failed->value => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('job.started_at')
                            ->label('Started')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('job.finished_at')
                            ->label('Finished')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('job.error_message')
                            ->label('Error Message')
                            ->prose()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(['lg' => 2]),
                Section::make('Payment')
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->state(fn (VerificationOrder $record): string => $record->paymentMethodLabel()),
                        TextEntry::make('checkout_intent_id')
                            ->label('Checkout Intent')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('refunded_at')
                            ->label('Refunded At')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(1)
                    ->columnSpan(['lg' => 1]),
                Section::make('Activity')
                    ->schema([
                        RepeatableEntry::make('activity')
                            ->label('Recent Activity')
                            ->state(function (VerificationOrder $record): array {
                                $logs = $record->job?->logs()
                                    ->latest()
                                    ->limit(20)
                                    ->get();

                                if (! $logs) {
                                    return [];
                                }

                                return $logs->map(function ($log): array {
                                    return [
                                        'event' => $log->event,
                                        'message' => $log->message,
                                        'actor' => $log->user?->email ?: __('System'),
                                        'created_at' => $log->created_at,
                                    ];
                                })->all();
                            })
                            ->schema([
                                TextEntry::make('event')
                                    ->label('Event')
                                    ->badge()
                                    ->color(function ($state): string {
                                        return match ((string) $state) {
                                            'created' => 'info',
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'warning',
                                            'fraud' => 'danger',
                                            default => 'gray',
                                        };
                                    }),
                                TextEntry::make('message')
                                    ->label('Message')
                                    ->placeholder('-')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('actor')
                                    ->label('Actor')
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label('Time')
                                    ->dateTime(),
                            ])
                            ->columns(['default' => 1, 'md' => 3])
                            ->placeholder('No activity yet.'),
                    ])
                    ->columnSpan(['lg' => 2]),
            ]);
    }
}
