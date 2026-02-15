<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Credit;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\InvoiceResource\RelationManagers;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),

            Actions\Action::make('process_payment')
                ->label('Add Payment')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->visible(fn($record) => $record->balance_due > 0 || $record->calculateBalanceDue() > 0)
                ->form([
                    Forms\Components\Placeholder::make('balance_info')
                        ->label('Current Balance Due')
                        ->content(fn($record) => $record->formatted_balance_due),
                    Forms\Components\TextInput::make('amount')
                        ->label('Payment Amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('$')
                        ->maxValue(fn($record) => ($record->balance_due ?? $record->calculateBalanceDue()) / 100)
                        ->helperText(fn($record) => 'Maximum amount you can process: $' . number_format(($record->balance_due ?? $record->calculateBalanceDue()) / 100, 2)),
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment Method')
                        ->options([
                            'Stripe' => 'Stripe',
                            'PayPal' => 'PayPal',
                            'Bank Transfer' => 'Bank Transfer',
                            'Cash' => 'Cash',
                            'Check' => 'Check',
                            'Manual' => 'Manual',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('transaction_id')
                        ->label('Transaction ID')
                        ->helperText('Optional: Gateway transaction reference number'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Payment Notes')
                        ->rows(3),
                ])
                ->action(function (array $data, $record) {
                    $amountInCents = (int) ($data['amount'] * 100);

                    $success = $record->processPayment(
                        $amountInCents,
                        $data['payment_method'],
                        $data['transaction_id'] ?? null
                    );

                    if ($success) {
                        if (!empty($data['payment_notes'])) {
                            $record->notes = ($record->notes ? $record->notes . "\n\n" : '') .
                                "Payment of \${$data['amount']} via {$data['payment_method']}: " . $data['payment_notes'];
                            $record->save();
                        }

                        Notification::make()
                            ->title('Payment Processed')
                            ->success()
                            ->body("Payment of \${$data['amount']} has been recorded successfully.")
                            ->send();

                        $this->refreshFormData([
                            'balance_due',
                            'status',
                            'paid_at',
                        ]);
                    } else {
                        Notification::make()
                            ->title('Payment Failed')
                            ->danger()
                            ->body('Unable to process payment. Please check the amount and try again.')
                            ->send();
                    }
                }),

            Actions\Action::make('apply_credit')
                ->label('Apply Credit')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn($record) => $record->balance_due > 0 || $record->calculateBalanceDue() > 0)
                ->form([
                    Forms\Components\Placeholder::make('available_credit')
                        ->label('Available Customer Credit')
                        ->content(function ($record) {
                            $availableCredit = Credit::where('user_id', $record->user_id)
                                ->whereNull('invoice_id')
                                ->where('amount', '>', 0)
                                ->sum('amount');
                            return '$' . number_format($availableCredit / 100, 2);
                        }),
                    Forms\Components\Placeholder::make('balance_info')
                        ->label('Invoice Balance Due')
                        ->content(fn($record) => $record->formatted_balance_due),
                    Forms\Components\TextInput::make('credit_amount')
                        ->label('Credit Amount to Apply')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('$')
                        ->helperText('Enter the amount of credit to apply to this invoice'),
                    Forms\Components\Textarea::make('credit_description')
                        ->label('Description')
                        ->default(fn($record) => "Credit applied to Invoice #{$record->invoice_number}")
                        ->rows(2),
                ])
                ->action(function (array $data, $record) {
                    $amountInCents = (int) ($data['credit_amount'] * 100);

                    // Check if user has enough available credit
                    $availableCredit = Credit::where('user_id', $record->user_id)
                        ->whereNull('invoice_id')
                        ->where('amount', '>', 0)
                        ->sum('amount');

                    if ($availableCredit < $amountInCents) {
                        Notification::make()
                            ->title('Insufficient Credit')
                            ->danger()
                            ->body("Customer only has \$" . number_format($availableCredit / 100, 2) . " available credit.")
                            ->send();
                        return;
                    }

                    $success = $record->applyCredit($amountInCents, $data['credit_description']);

                    if ($success) {
                        Notification::make()
                            ->title('Credit Applied')
                            ->success()
                            ->body("Credit of \${$data['credit_amount']} has been applied to this invoice.")
                            ->send();

                        $this->refreshFormData([
                            'credit_applied',
                            'balance_due',
                            'status',
                            'paid_at',
                        ]);
                    } else {
                        Notification::make()
                            ->title('Credit Application Failed')
                            ->danger()
                            ->body('Unable to apply credit. Please check the amount and try again.')
                            ->send();
                    }
                }),

            // Actions\Action::make('process_refund')
            //     ->label('Process Refund')
            //     ->icon('heroicon-o-arrow-uturn-left')
            //     ->color('warning')
            //     ->visible(fn($record) => $record->status === 'Paid' || $record->total_paid > 0)
            //     ->requiresConfirmation()
            //     ->form([
            //         Forms\Components\Placeholder::make('total_paid_info')
            //             ->label('Total Paid')
            //             ->content(fn($record) => '$' . number_format($record->total_paid / 100, 2)),
            //         Forms\Components\TextInput::make('refund_amount')
            //             ->label('Refund Amount')
            //             ->numeric()
            //             ->required()
            //             ->minValue(0.01)
            //             ->prefix('$')
            //             ->maxValue(fn($record) => $record->total_paid / 100)
            //             ->helperText(fn($record) => 'Maximum refund amount: $' . number_format($record->total_paid / 100, 2)),
            //         Forms\Components\Textarea::make('refund_reason')
            //             ->label('Refund Reason')
            //             ->required()
            //             ->rows(3),
            //     ])
            //     ->action(function (array $data, $record) {
            //         $amountInCents = (int) ($data['refund_amount'] * 100);

            //         $success = $record->processRefund($amountInCents, $data['refund_reason']);

            //         if ($success) {
            //             Notification::make()
            //                 ->title('Refund Processed')
            //                 ->success()
            //                 ->body("Refund of \${$data['refund_amount']} has been processed.")
            //                 ->send();

            //             $this->refreshFormData([
            //                 'balance_due',
            //                 'status',
            //                 'notes',
            //             ]);
            //         } else {
            //             Notification::make()
            //                 ->title('Refund Failed')
            //                 ->danger()
            //                 ->body('Unable to process refund. Please check the amount and try again.')
            //                 ->send();
            //         }
            //     }),

            Actions\DeleteAction::make(),
        ];
    }

    // public function getRelationManagers(): array
    // {
    //     return [
    //         RelationManagers\TransactionsRelationManager::class,
    //     ];
    // }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Totals will be synced in afterSave to ensure relationship items are included
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $record->refresh(); // Load saved relationship items

        // Always sync the database totals to match the items
        $record->total = $record->calculateTotal();
        $record->balance_due = $record->calculateBalanceDue();
        $record->syncStatus();
        $record->saveQuietly();

        $data = $this->data;
        $hasChanged = false;

        // 1. Process Manual Payment from "Add Payment" Tab
        if (!empty($data['payment_amount']) && (float) $data['payment_amount'] > 0) {
            $amountInCents = (int) ($data['payment_amount'] * 100);

            $success = $record->processPayment(
                $amountInCents,
                $data['payment_gateway'] ?? 'Manual',
                $data['transaction_id_payment'] ?? null
            );

            if ($success) {
                if (!empty($data['payment_notes'])) {
                    $record->notes = ($record->notes ? $record->notes . "\n\n" : '') .
                        "Payment of \${$data['payment_amount']} via " . ($data['payment_gateway'] ?? 'Manual') . ": " . $data['payment_notes'];
                    $record->saveQuietly();
                }
                $hasChanged = true;
            }
        }

        // 2. Apply Credit from "Credit" Tab
        if (!empty($data['credit_to_apply']) && (float) $data['credit_to_apply'] > 0) {
            $amountInCents = (int) ($data['credit_to_apply'] * 100);
            $success = $record->applyCredit($amountInCents);
            if ($success) {
                $hasChanged = true;
            }
        }

        // Recalculate balance if any payment or credit was processed
        if ($hasChanged) {
            $record->refresh();
            $record->balance_due = $record->calculateBalanceDue();
            $record->syncStatus();
            $record->saveQuietly();

            Notification::make()
                ->title('Financials Updated')
                ->success()
                ->body('Manual payments or credits have been successfully applied.')
                ->send();

            $this->refreshFormData([
                'balance_due',
                'status',
                'paid_at',
                'credit_applied',
                'total',
                'subtotal',
            ]);

            // Reset manual entry fields
            $this->data['payment_amount'] = null;
            $this->data['payment_gateway'] = null;
            $this->data['transaction_id_payment'] = null;
            $this->data['payment_notes'] = null;
            $this->data['credit_to_apply'] = null;
        } else {
            // Standard balance sync
            $record->balance_due = $record->calculateBalanceDue();
            $record->syncStatus();
            $record->saveQuietly();
        }
    }
}
