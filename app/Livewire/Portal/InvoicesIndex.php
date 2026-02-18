<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
class InvoicesIndex extends Component
{
    use WithPagination;

    public ?string $status = null;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getInvoicesProperty()
    {
        $query = Invoice::query()
            ->where('user_id', Auth::id())
            ->where('is_published', true);

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->latest('date')->paginate(10);
    }

    public function download(Invoice $invoice)
    {
        if ($invoice->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->streamDownload(function () use ($invoice) {
            echo \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $invoice])->output();
        }, 'invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function render()
    {
        return view('livewire.portal.invoices-index');
    }
}
