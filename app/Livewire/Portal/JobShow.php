<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\SeedSendCampaign;
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
        if (in_array($this->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)) {
            return true;
        }

        if ((bool) config('seed_send.enabled', false) && $this->job->status === VerificationJobStatus::Completed) {
            return $this->job->seedSendCampaigns()
                ->whereIn('status', [SeedSendCampaign::STATUS_QUEUED, SeedSendCampaign::STATUS_RUNNING])
                ->exists();
        }

        return false;
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
            'latestSeedSendConsent' => $this->job->seedSendConsents()->latest('id')->first(),
            'latestSeedSendCampaign' => $this->job->seedSendCampaigns()->latest('created_at')->first(),
            'activityLogs' => $this->job->logs()
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
