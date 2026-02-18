<?php

namespace App\Http\Controllers;

use App\Enums\CheckoutIntentStatus;
use App\Models\CheckoutIntent;
use App\Models\User;
use App\Services\CheckoutIntentService;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];

        // 1. Check for Invoice Payments (Credit deposits OR regular invoice payments)
        $isInvoicePayment = isset($metadata['type']) && in_array($metadata['type'], ['credit_deposit', 'invoice_payment']);

        if ($isInvoicePayment && isset($metadata['invoice_id'])) {
            $invoice = \App\Models\Invoice::find($metadata['invoice_id']);
            if ($invoice && $invoice->status !== 'Paid') {
                $billing = app(\App\Services\BillingService::class);
                $billing->recordPayment(
                    $invoice,
                    $session['amount_total'],
                    'Stripe',
                    $session['payment_intent'] ?? $session['id']
                );
            }
            return $this->successMethod();
        }

        // 2. Original logic for Verification Job Checkout Intents
        $intentId = $metadata['checkout_intent_id']
            ?? ($session['client_reference_id'] ?? null);

        $intent = $intentId
            ? CheckoutIntent::query()->with('order')->find($intentId)
            : CheckoutIntent::query()->with('order')->where('stripe_session_id', $session['id'] ?? null)->first();

        if (!$intent) {
            return $this->successMethod();
        }

        if ($intent->status !== CheckoutIntentStatus::Pending && $intent->order) {
            return $this->successMethod();
        }

        if ($intent->expires_at && $intent->expires_at->isPast()) {
            $intent->status = CheckoutIntentStatus::Expired;
            $intent->save();

            return $this->successMethod();
        }

        $user = $intent->user;

        if (!$user && isset($session['customer'])) {
            $user = User::query()->where('stripe_id', $session['customer'])->first();
        }

        if (!$user) {
            return $this->successMethod();
        }

        $paymentMethod = null;
        if (!empty($session['payment_method_types'][0])) {
            $paymentMethod = $session['payment_method_types'][0];
        } elseif (!empty($session['payment_method_details']['type'])) {
            $paymentMethod = $session['payment_method_details']['type'];
        }

        $intent->stripe_session_id = $session['id'] ?? $intent->stripe_session_id;
        $intent->stripe_payment_intent_id = $session['payment_intent'] ?? $intent->stripe_payment_intent_id;
        $intent->payment_method = $paymentMethod ?: $intent->payment_method;
        $intent->paid_at = $intent->paid_at ?: now();
        $intent->save();

        $service = app(CheckoutIntentService::class);
        $service->completeIntent($intent, $user);

        return $this->successMethod();
    }

    protected function handleCheckoutSessionExpired(array $payload)
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        $intentId = $metadata['checkout_intent_id']
            ?? ($session['client_reference_id'] ?? null);

        $intent = $intentId
            ? CheckoutIntent::find($intentId)
            : CheckoutIntent::query()->where('stripe_session_id', $session['id'] ?? null)->first();

        if (!$intent) {
            return $this->successMethod();
        }

        if ($intent->status === CheckoutIntentStatus::Pending) {
            $intent->status = CheckoutIntentStatus::Expired;
            $intent->save();
        }
        return $this->successMethod();
    }
}
