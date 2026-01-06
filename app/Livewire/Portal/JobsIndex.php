<?php

namespace App\Livewire\Portal;

use App\Models\VerificationJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JobsIndex extends Component
{
    public function getJobsProperty()
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.portal.jobs-index');
    }
}
