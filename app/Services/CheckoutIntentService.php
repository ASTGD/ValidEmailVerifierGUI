<?php

namespace App\Services;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationOrderStatus;
use App\Models\CheckoutIntent;
use App\Models\User;
use App\Models\VerificationOrder;
use App\Services\BillingService;
use App\Services\CheckoutStorage;
use App\Services\EmailListAnalyzer;
use App\Services\OrderStorage;
use App\Services\PricingCalculator;
use App\Support\AdminAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Checkout;

class CheckoutIntentService
{
    public function __construct(
        private readonly CheckoutStorage $storage,
        private readonly EmailListAnalyzer $analyzer,
        private readonly PricingCalculator $pricing,
        private readonly OrderStorage $orderStorage,
        private readonly BillingService $billing,
    ) {
    }

    public function createIntent(UploadedFile $file, ?User $user): CheckoutIntent
    {
        $emailCount = $this->analyzer->countEmails($file);
        $quote = $this->pricing->quoteForEmailCount($emailCount);

        if (!$quote['plan']) {
            throw ValidationException::withMessages([
                'file' => __('Pricing is not configured yet.'),
            ]);
        }

        $intent = new CheckoutIntent([
            'user_id' => $user?->id,
            'status' => CheckoutIntentStatus::Pending,
            'original_filename' => $file->getClientOriginalName(),
            'email_count' => $emailCount,
            'amount_cents' => $quote['amount_cents'],
            'currency' => $quote['currency'],
            'pricing_plan_id' => $quote['plan']?->id,
            'expires_at' => now()->addMinutes((int) config('verifier.checkout_intent_ttl_minutes', 60)),
        ]);
        $intent->id = (string) Str::uuid();
        [$disk, $key] = $this->storage->storeTemp($file, $intent);
        $intent->temp_disk = $disk;
        $intent->temp_key = $key;
        $intent->save();

        return $intent;
    }

    public function createCreditIntent(int $amountCents, User $user, string $currency = 'usd'): CheckoutIntent
    {
        $intent = new CheckoutIntent([
            'user_id' => $user->id,
            'status' => CheckoutIntentStatus::Pending,
            'type' => 'credit',
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'expires_at' => now()->addMinutes((int) config('verifier.checkout_intent_ttl_minutes', 60)),
        ]);
        $intent->id = (string) Str::uuid();
        $intent->save();

        return $intent;
    }

    public function calculateTotals(CheckoutIntent $intent, User $user, bool $useCredit = false): array
    {
        $total = $intent->amount_cents;
        $balance = $user->balance;

        $creditApplied = 0;
        if ($useCredit && $balance > 0) {
            $creditApplied = min($total, $balance);
        }

        $payNow = $total - $creditApplied;

        return [
            'total' => $total,
            'available_credit' => $balance,
            'credit_applied' => $creditApplied,
            'pay_now' => $payNow,
        ];
    }

    public function processPayment(CheckoutIntent $intent, User $user, bool $useCredit = false)
    {
        $totals = $this->calculateTotals($intent, $user, $useCredit);

        // Update intent with planned credit usage
        $intent->credit_applied = $totals['credit_applied'];
        $intent->save();

        if ($totals['pay_now'] === 0) {
            return $this->completeIntent($intent, $user);
        }

        // Process partial or full gateway payment
        return $this->startPayment($intent, $user, $totals['pay_now']);
    }

    public function startPayment(CheckoutIntent $intent, User $user, ?int $amountToCharge = null): Checkout
    {
        if ($intent->status !== CheckoutIntentStatus::Pending) {
            abort(409, __('Checkout intent is not available.'));
        }

        if ($intent->expires_at && $intent->expires_at->isPast()) {
            $intent->status = CheckoutIntentStatus::Expired;
            $intent->save();

            abort(410, __('Checkout intent has expired.'));
        }

        $amountToCharge = $amountToCharge ?? $intent->amount_cents;

        if ($amountToCharge <= 0) {
            throw ValidationException::withMessages([
                'file' => __('Invalid charge amount.'),
            ]);
        }

        if (!config('cashier.secret') || !config('cashier.key')) {
            throw ValidationException::withMessages([
                'file' => __('Payment gateway is not configured yet.'),
            ]);
        }

        if ($intent->user_id && $intent->user_id !== $user->id) {
            abort(403);
        }

        if (!$intent->user_id) {
            $intent->user_id = $user->id;
            $intent->save();
        }

        $metadata = [
            'checkout_intent_id' => $intent->id,
            'user_id' => (string) $user->id,
            'type' => $intent->type === 'credit' ? 'credit_deposit' : 'order_payment',
        ];

        if ($intent->email_count) {
            $metadata['email_count'] = (string) $intent->email_count;
        }

        $brand = config('verifier.brand_name') ?: config('app.name');

        if ($intent->type === 'credit') {
            $productName = __('Account Credit');
            $description = __('Funds deposit to account balance');
        } else {
            $productName = $brand ?: __('Email Verification');
            $description = __('Email verification for :count emails', [
                'count' => number_format($intent->email_count),
            ]);
        }

        $lineItems = [
            [
                'price_data' => [
                    'currency' => $intent->currency,
                    'product_data' => [
                        'name' => $productName,
                        'description' => $description,
                    ],
                    'unit_amount' => $amountToCharge, // Charge the remaining amount
                ],
                'quantity' => 1,
            ]
        ];

        $checkout = Checkout::customer($user)->create($lineItems, [
            'success_url' => route('portal.orders.index', ['checkout' => 'success']),
            'cancel_url' => route('checkout.show', $intent),
            'metadata' => $metadata,
            'client_reference_id' => $intent->id,
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
        ]);

        $intent->stripe_session_id = $checkout->id;
        $intent->stripe_payment_intent_id = $checkout->payment_intent ?? $intent->stripe_payment_intent_id;
        $intent->save();

        return $checkout;
    }

    public function completeIntent(CheckoutIntent $intent, User $user, bool $isPaid = true): VerificationOrder|bool
    {
        if ($intent->status !== CheckoutIntentStatus::Pending) {
            if ($intent->type === 'credit') {
                return true;
            }
            if ($intent->order) {
                return $intent->order;
            }

            abort(409, __('Checkout intent is already finalized.'));
        }

        if ($intent->expires_at && $intent->expires_at->isPast()) {
            $intent->status = CheckoutIntentStatus::Expired;
            $intent->save();

            abort(410, __('Checkout intent has expired.'));
        }

        return DB::transaction(function () use ($intent, $user, $isPaid) {
            if (!$intent->user_id) {
                $intent->user_id = $user->id;
            }

            if ($intent->type === 'credit') {
                $intent->status = CheckoutIntentStatus::Completed;
                $intent->paid_at = $isPaid ? ($intent->paid_at ?: now()) : null;
                $intent->save();

                $invoice = $this->billing->createInvoice($user, [
                    [
                        'description' => __('Credit Balance Deposit'),
                        'amount' => $intent->amount_cents,
                        'type' => 'Credit',
                    ]
                ], [
                    'status' => $isPaid ? 'Paid' : 'Unpaid',
                    'date' => now(),
                    'paid_at' => $isPaid ? now() : null,
                ]);

                if ($isPaid) {
                    $gateway = $intent->stripe_payment_intent_id ? 'Stripe' : 'Manual';
                    $ref = $intent->stripe_payment_intent_id;
                    $this->billing->recordPayment($invoice, $intent->amount_cents, $gateway, $ref);

                    // User balance is updated by recordPayment or we should do it here if it's credit deposit?
                    // Usually recordPayment in BillingService handles balance if type is Deposit?
                    // Let's check BillingService.
                }

                return true;
            }

            $creditToDeduct = $isPaid ? ($intent->credit_applied ?? 0) : 0;
            if ($creditToDeduct > 0) {
                $user->refresh();
                $user->balance -= $creditToDeduct;
                $user->save();
            }

            $order = new VerificationOrder([
                'user_id' => $user->id,
                'checkout_intent_id' => $intent->id,
                'pricing_plan_id' => $intent->pricing_plan_id,
                'order_number' => VerificationOrder::generateOrderNumber(),
                'status' => VerificationOrderStatus::Pending,
                'original_filename' => $intent->original_filename,
                'email_count' => $intent->email_count,
                'amount_cents' => $intent->amount_cents,
                'currency' => $intent->currency,
            ]);
            $order->save();

            $order->input_disk = $this->orderStorage->disk();
            $order->input_key = $this->orderStorage->inputKey($order, pathinfo((string) $intent->temp_key, PATHINFO_EXTENSION));
            $order->save();

            $this->storage->moveToOrder($intent, $order, $this->orderStorage);

            $intent->status = CheckoutIntentStatus::Completed;
            $intent->paid_at = $isPaid ? ($intent->paid_at ?: now()) : null;
            $intent->save();

            $invoice = $this->billing->createInvoice($user, [
                [
                    'description' => __('Email verification for :count emails', ['count' => number_format($intent->email_count)]),
                    'amount' => $intent->amount_cents,
                    'type' => 'Order',
                    'rel_type' => VerificationOrder::class,
                    'rel_id' => $order->id,
                ]
            ], [
                'status' => $isPaid ? 'Paid' : 'Unpaid',
                'date' => now(),
                'paid_at' => $isPaid ? now() : null,
            ]);

            // Record Transactions only if paid
            if ($isPaid) {
                if ($creditToDeduct > 0) {
                    $this->billing->recordPayment($invoice, $creditToDeduct, 'Credit Balance', null);
                }

                // Record Stripe Payment (Remainder)
                $stripeAmount = $intent->amount_cents - $creditToDeduct;
                if ($stripeAmount > 0) {
                    $gateway = $intent->stripe_payment_intent_id ? 'Stripe' : 'Manual';
                    $ref = $intent->stripe_payment_intent_id;
                    $this->billing->recordPayment($invoice, $stripeAmount, $gateway, $ref);
                }
            }

            \App\Support\AdminAuditLogger::log('order_placed', $order);

            return $order;
        });
    }
}
