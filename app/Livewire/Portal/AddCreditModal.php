<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Laravel\Cashier\Checkout;

class AddCreditModal extends Component
{
    public $showModal = false;
    public $amount = 10; // Default amount

    #[On('openAddCreditModal')]

    public function open()
    {
        $this->showModal = true;
    }

    public function close()
    {
        $this->showModal = false;
        $this->resetErrorBag();
    }

    public function submit()
    {
        $this->validate([
            'amount' => 'required|numeric|min:5|max:1000',
        ]);

        if (!config('cashier.secret') && !config('verifier.allow_fake_payments')) {
            return $this->addError('amount', __('Stripe Secret Key is not configured in .env file.'));
        }

        $user = Auth::user();
        $amountCents = (int) ($this->amount * 100);

        /** @var \App\Services\CheckoutIntentService $service */
        $service = app(\App\Services\CheckoutIntentService::class);
        $intent = $service->createCreditIntent($amountCents, $user);

        return redirect()->route('checkout.show', $intent);
    }

    public function render()
    {
        return view('livewire.portal.add-credit-modal');
    }
}
