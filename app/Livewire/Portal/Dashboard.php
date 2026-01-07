<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class Dashboard extends Component
{
    public function getLatestJobProperty()
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->first();
    }

    public function getRecentJobsProperty()
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getQueueCountProperty(): int
    {
        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [VerificationJobStatus::Pending, VerificationJobStatus::Processing])
            ->count();
    }

    public function getShouldPollProperty(): bool
    {
        return $this->queueCount > 0;
    }

    public function getPlanNameProperty(): string
    {
        return config('services.stripe.price_name') ?: __('Plan');
    }

    public function getBillingConfiguredProperty(): bool
    {
        return (bool) config('services.stripe.price_id');
    }

    public function getBillingStatusProperty(): string
    {
        $user = Auth::user();
        $subscription = $user?->subscription('default');

        if (! $subscription) {
            return __('No subscription');
        }

        if ($subscription->onGracePeriod()) {
            return __('Grace period');
        }

        if ($user->subscribed('default')) {
            return __('Active');
        }

        return __('Inactive');
    }

    public function getCreditsUsedThisMonthProperty(): int
    {
        return (int) $this->completedThisMonthQuery()->sum('total_emails');
    }

    public function getVerifiedJobsThisMonthProperty(): int
    {
        return $this->completedThisMonthQuery()->count();
    }

    public function render()
    {
        return view('livewire.portal.dashboard');
    }

    private function completedThisMonthQuery(): Builder
    {
        $startOfMonth = now()->startOfMonth();

        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->where('status', VerificationJobStatus::Completed)
            ->where(function (Builder $query) use ($startOfMonth) {
                $query->where('finished_at', '>=', $startOfMonth)
                    ->orWhere(function (Builder $inner) use ($startOfMonth) {
                        $inner->whereNull('finished_at')
                            ->where('created_at', '>=', $startOfMonth);
                    });
            });
    }
}
