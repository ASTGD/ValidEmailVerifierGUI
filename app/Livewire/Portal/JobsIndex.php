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
    public ?string $search = null;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
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

        if ($this->search) {
            $search = trim($this->search);

            $query->where(function ($builder) use ($search) {
                $builder->where('original_filename', 'like', '%'.$search.'%')
                    ->orWhere('id', 'like', '%'.$search.'%');
            });
        }

        $allowedSorts = ['created_at', 'status'];
        $sortField = in_array($this->sortField, $allowedSorts, true) ? $this->sortField : 'created_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortDirection)->paginate(15);
    }

    public function getShouldPollProperty(): bool
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [VerificationJobStatus::Pending, VerificationJobStatus::Processing])
            ->exists();
    }

    public function sortBy(string $field): void
    {
        $allowedSorts = ['created_at', 'status'];

        if (! in_array($field, $allowedSorts, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'search']);
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.portal.jobs-index');
    }
}
