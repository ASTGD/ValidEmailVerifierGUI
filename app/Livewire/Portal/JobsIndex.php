<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
class JobsIndex extends Component
{
    use WithPagination;

    public ?string $status = null;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getJobsProperty()
    {
        $query = VerificationJob::query()
            ->where('user_id', Auth::id());

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->latest()->paginate(15);
    }

    public function getShouldPollProperty(): bool
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [VerificationJobStatus::Pending, VerificationJobStatus::Processing])
            ->exists();
    }

    public function render()
    {
        return view('livewire.portal.jobs-index');
    }
}
