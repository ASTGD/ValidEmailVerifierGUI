<?php

namespace App\Livewire\Portal;

use App\Models\VerificationJob;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class JobShow extends Component
{
    use AuthorizesRequests;

    public VerificationJob $job;

    public function mount(VerificationJob $job): void
    {
        $this->authorize('view', $job);

        $this->job = $job;
    }

    public function render()
    {
        return view('livewire.portal.job-show', [
            'job' => $this->job->fresh(),
        ]);
    }
}
