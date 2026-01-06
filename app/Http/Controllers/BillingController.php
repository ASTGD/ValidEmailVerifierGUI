<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Cashier\Checkout;

class BillingController
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('billing.index', [
            'subscription' => $user->subscription('default'),
            'isActive' => $user->subscribed('default'),
            'priceName' => config('services.stripe.price_name'),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse|Checkout
    {
        $user = $request->user();

        if ($user->subscribed('default')) {
            return redirect()->route('billing.index');
        }

        $priceId = config('services.stripe.price_id');
        if (! $priceId) {
            return redirect()
                ->route('billing.index')
                ->with('status', __('Billing is not configured yet.'));
        }

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
        ]);
    }

    public function success(): RedirectResponse
    {
        return redirect()
            ->route('billing.index')
            ->with('status', __('Subscription activated.'));
    }

    public function cancel(): RedirectResponse
    {
        return redirect()
            ->route('billing.index')
            ->with('status', __('Subscription canceled.'));
    }
}
