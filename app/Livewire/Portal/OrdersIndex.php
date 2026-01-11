<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationOrderStatus;
use App\Models\VerificationOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
class OrdersIndex extends Component
{
    use WithPagination;

    public ?string $status = null;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getOrdersProperty()
    {
        $query = VerificationOrder::query()
            ->where('user_id', Auth::id())
            ->with('job');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->latest()->paginate(10);
    }

    public function getShouldPollProperty(): bool
    {
        return VerificationOrder::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing])
            ->exists();
    }

    public function render()
    {
        return view('livewire.portal.orders-index');
    }
}
