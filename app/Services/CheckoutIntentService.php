<?php

namespace App\Services;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationOrderStatus;
use App\Models\CheckoutIntent;
use App\Models\User;
use App\Models\VerificationOrder;
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
    ) {
    }

    public function createIntent(UploadedFile $file, ?User $user): CheckoutIntent
    {
        $emailCount = $this->analyzer->countEmails($file);
        $quote = $this->pricing->quoteForEmailCount($emailCount);

        if (! $quote['plan']) {
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

    public function startPayment(CheckoutIntent $intent, User $user): Checkout
    {
        if ($intent->status !== CheckoutIntentStatus::Pending) {
            abort(409, __('Checkout intent is not available.'));
        }

        if ($intent->expires_at && $intent->expires_at->isPast()) {
            $intent->status = CheckoutIntentStatus::Expired;
            $intent->save();

            abort(410, __('Checkout intent has expired.'));
        }

        if ($intent->amount_cents <= 0) {
            throw ValidationException::withMessages([
                'file' => __('Unable to calculate pricing for this list.'),
            ]);
        }

        if (! config('cashier.secret') || ! config('cashier.key')) {
            throw ValidationException::withMessages([
                'file' => __('Payment gateway is not configured yet.'),
            ]);
        }

        if ($intent->user_id && $intent->user_id !== $user->id) {
            abort(403);
        }

        if (! $intent->user_id) {
            $intent->user_id = $user->id;
            $intent->save();
        }

        $metadata = [
            'checkout_intent_id' => $intent->id,
            'user_id' => (string) $user->id,
            'email_count' => (string) $intent->email_count,
        ];

        $brand = config('verifier.brand_name') ?: config('app.name');
        $productName = $brand ?: __('Email Verification');
        $description = __('Email verification for :count emails', [
            'count' => number_format($intent->email_count),
        ]);

        $lineItems = [[
            'price_data' => [
                'currency' => $intent->currency,
                'product_data' => [
                    'name' => $productName,
                    'description' => $description,
                ],
                'unit_amount' => $intent->amount_cents,
            ],
            'quantity' => 1,
        ]];

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

    public function completeIntent(CheckoutIntent $intent, User $user): VerificationOrder
    {
        if ($intent->status !== CheckoutIntentStatus::Pending) {
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

        return DB::transaction(function () use ($intent, $user) {
            if (! $intent->user_id) {
                $intent->user_id = $user->id;
            }

            $order = new VerificationOrder([
                'user_id' => $user->id,
                'checkout_intent_id' => $intent->id,
                'pricing_plan_id' => $intent->pricing_plan_id,
                'status' => VerificationOrderStatus::Pending,
                'original_filename' => $intent->original_filename,
                'email_count' => $intent->email_count,
                'amount_cents' => $intent->amount_cents,
                'currency' => $intent->currency,
            ]);
            $order->id = (string) Str::uuid();
            $order->input_disk = $this->orderStorage->disk();
            $order->input_key = $this->orderStorage->inputKey($order, pathinfo((string) $intent->temp_key, PATHINFO_EXTENSION));
            $order->save();

            $this->storage->moveToOrder($intent, $order, $this->orderStorage);

            $intent->status = CheckoutIntentStatus::Completed;
            $intent->paid_at = $intent->paid_at ?: now();
            $intent->save();

            return $order;
        });
    }
}
