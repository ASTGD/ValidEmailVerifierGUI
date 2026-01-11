<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class JobShow extends Component
{
    use AuthorizesRequests;

    public VerificationJob $job;

    public function getShouldPollProperty(): bool
    {
        return in_array($this->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true);
    }

    public function mount(VerificationJob $job): void
    {
        $this->authorize('view', $job);

        $this->job = $job;
    }

    public function render()
    {
        $this->job = $this->job->fresh();

        return view('livewire.portal.job-show', [
            'job' => $this->job,
            'activityLogs' => $this->job->logs()
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
