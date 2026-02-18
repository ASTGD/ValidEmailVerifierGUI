<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class InvoiceShow extends Component
{
    public Invoice $invoice;

    public ?float $applyAmount = null;

    public function mount(Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id() || !$invoice->is_published) {
            abort(403);
        }
        $this->invoice = $invoice->load('items', 'transactions', 'user');
    }

    public function download()
    {
        return response()->streamDownload(function () {
            echo \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $this->invoice])->output();
        }, 'invoice-' . $this->invoice->invoice_number . '.pdf');
    }

    public function applyCredit()
    {
        $this->validate([
            'applyAmount' => 'required|numeric|min:0.01',
        ]);

        $amountCents = (int) round($this->applyAmount * 100);

        $billing = app(BillingService::class);

        try {
            $billing->applyCreditToInvoice($this->invoice, $amountCents);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->invoice = $this->invoice->fresh()->load('items', 'transactions', 'user');
        $this->applyAmount = null;
        session()->flash('status', __('Credit applied successfully.'));
    }

    public function payNow(\App\Services\CheckoutIntentService $service)
    {
        $user = Auth::user();

        if ($this->invoice->calculateBalanceDue() <= 0) {
            session()->flash('error', __('This invoice is already fully paid.'));
            return;
        }

        try {
            $intent = $service->createInvoiceIntent($this->invoice, $user);
            return redirect()->route('checkout.show', $intent);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return;
        }
    }

    public function render()
    {
        return view('livewire.portal.invoice-show');
    }
}
