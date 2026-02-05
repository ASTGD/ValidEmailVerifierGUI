<?php

namespace App\Http\Controllers;

use App\Enums\CheckoutIntentStatus;
use App\Models\CheckoutIntent;
use App\Services\CheckoutIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController
{
    public function store(Request $request, CheckoutIntentService $service): RedirectResponse
    {
        $maxMb = (int) config('verifier.checkout_upload_max_mb', 10);
        $maxKb = $maxMb * 1024;

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:' . $maxKb],
        ]);

        $file = $validated['file'];
        $intent = $service->createIntent($file, $request->user());
        $request->session()->put('checkout_intent_id', $intent->id);

        return redirect()->route('checkout.show', $intent);
    }

    public function show(Request $request, CheckoutIntent $intent, CheckoutIntentService $service): View|RedirectResponse
    {
        if ($intent->status === CheckoutIntentStatus::Completed && $request->user()) {
            return redirect()
                ->route('portal.orders.index')
                ->with('status', __('Order is ready in your portal.'));
        }

        if ($intent->status !== CheckoutIntentStatus::Pending) {
            abort(409, __('Checkout intent is not available.'));
        }

        if ($intent->expires_at && $intent->expires_at->isPast()) {
            abort(410, __('Checkout intent has expired.'));
        }

        $user = $request->user();
        if ($intent->user_id && $user && $intent->user_id !== $user->id) {
            abort(403);
        }

        if ($user && !$intent->user_id) {
            $intent->user_id = $user->id;
            $intent->save();
        }

        $intent->load('pricingPlan');

        $totals = $user ? $service->calculateTotals($intent, $user, true) : null;

        return view('checkout', [
            'intent' => $intent,
            'formattedTotal' => number_format($intent->amount_cents / 100, 2),
            'totals' => $totals,
            'canFakePay' => (bool) config('verifier.allow_fake_payments') && app()->environment(['local', 'testing']),
        ]);
    }

    public function login(CheckoutIntent $intent): RedirectResponse
    {
        session(['url.intended' => route('checkout.show', $intent)]);

        return redirect()->route('login');
    }

    public function register(CheckoutIntent $intent): RedirectResponse
    {
        session(['url.intended' => route('checkout.show', $intent)]);

        return redirect()->route('register');
    }

    public function pay(Request $request, CheckoutIntent $intent, CheckoutIntentService $service)
    {
        if (!$request->user()) {
            return redirect()->route('checkout.show', $intent);
        }

        if (!config('cashier.secret') || !config('cashier.key')) {
            return redirect()
                ->route('checkout.show', $intent)
                ->with('status', __('Payment gateway is not configured yet.'));
        }

        if ($intent->amount_cents <= 0) {
            return redirect()
                ->route('checkout.show', $intent)
                ->with('status', __('Pricing is not configured yet.'));
        }

        return $service->processPayment($intent, $request->user(), $request->boolean('use_credit'));
    }

    public function fakePay(Request $request, CheckoutIntent $intent, CheckoutIntentService $service): RedirectResponse
    {
        if (!config('verifier.allow_fake_payments') || !app()->environment(['local', 'testing'])) {
            abort(403);
        }

        $order = $service->completeIntent($intent, $request->user());

        return redirect()
            ->route('portal.orders.index')
            ->with('status', __('Order :id created and awaiting activation.', ['id' => $order->id]));
    }
}
