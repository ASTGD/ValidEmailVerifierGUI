<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class InvoiceShow extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id()) {
            abort(403);
        }
        $this->invoice = $invoice->load('items');
    }

    public function download()
    {
        return response()->streamDownload(function () {
            echo \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $this->invoice])->output();
        }, 'invoice-' . $this->invoice->invoice_number . '.pdf');
    }

    public function render()
    {
        return view('livewire.portal.invoice-show');
    }
}
