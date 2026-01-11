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
        $intentId = $metadata['checkout_intent_id'] ?? null;

        if (! $intentId) {
            return $this->successMethod();
        }

        $intent = CheckoutIntent::query()->with('order')->find($intentId);

        if (! $intent) {
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

        if (! $user && isset($session['customer'])) {
            $user = User::query()->where('stripe_id', $session['customer'])->first();
        }

        if (! $user) {
            return $this->successMethod();
        }

        $intent->stripe_session_id = $session['id'] ?? $intent->stripe_session_id;
        $intent->stripe_payment_intent_id = $session['payment_intent'] ?? $intent->stripe_payment_intent_id;
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
        $intentId = $metadata['checkout_intent_id'] ?? null;

        if (! $intentId) {
            return $this->successMethod();
        }

        $intent = CheckoutIntent::find($intentId);

        if (! $intent || $intent->status !== CheckoutIntentStatus::Pending) {
            return $this->successMethod();
        }

        $intent->status = CheckoutIntentStatus::Expired;
        $intent->save();

        return $this->successMethod();
    }
}
