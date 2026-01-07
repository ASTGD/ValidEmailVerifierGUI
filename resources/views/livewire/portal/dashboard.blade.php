<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Dashboard') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Monitor your verification activity at a glance.') }}</p>
        </div>
    </x-slot>
    <x-slot name="headerAction">
        <a href="{{ route('portal.upload') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500" wire:navigate>
            {{ __('Upload a list') }}
        </a>
    </x-slot>

    <div class="space-y-8" @if($this->shouldPoll) wire:poll.8s @endif>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Plan / Credits') }}</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $this->planName }}</p>
                <p class="text-xs text-gray-500">
                    @if($this->billingConfigured)
                        {{ __('Status: :status', ['status' => $this->billingStatus]) }}
                    @else
                        {{ __('Billing not configured') }}
                    @endif
                </p>
                <p class="mt-2 text-xs text-gray-500">
                    {{ __('Credits used this month: :count', ['count' => number_format($this->creditsUsedThisMonth)]) }}
                </p>
                <a href="{{ route('billing.index') }}" class="mt-3 inline-flex text-xs text-indigo-600 hover:text-indigo-500" wire:navigate>
                    {{ __('Manage billing') }}
                </a>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Jobs in queue') }}</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $this->queueCount }}</p>
                <p class="text-xs text-gray-500">{{ __('Pending + Processing') }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Verified this month') }}</p>
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ number_format($this->verifiedJobsThisMonth) }}</p>
                <p class="text-xs text-gray-500">{{ __('Completed jobs') }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Latest job status') }}</p>
                @if($this->latestJob)
                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $this->latestJob->status->badgeClasses() }}">
                            {{ $this->latestJob->status->label() }}
                        </span>
                        <span class="text-xs text-gray-500">{{ $this->latestJob->created_at?->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">{{ $this->latestJob->original_filename }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-500">{{ __('No jobs yet') }}</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Recent jobs') }}</h3>
                <a href="{{ route('portal.jobs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
                    {{ __('View all') }}
                </a>
            </div>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-gray-500 uppercase tracking-wider bg-gray-50">
                        <tr>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($this->recentJobs as $job)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $job->original_filename }}</div>
                                    <div class="text-xs text-gray-500">{{ $job->id }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $job->created_at?->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                                        {{ $job->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-3">
                                    <a href="{{ route('portal.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-500" wire:navigate>
                                        {{ __('View') }}
                                    </a>
                                    @if($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
                                        <a href="{{ route('portal.jobs.download', $job) }}" class="text-gray-700 hover:text-gray-900">
                                            {{ __('Download') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                    {{ __('No jobs yet. Upload a list to get started.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <h4 class="text-sm font-semibold text-gray-900">{{ __('Need help?') }}</h4>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('Upload CSV or TXT files with one email per line.') }}
                <a href="{{ route('portal.support') }}" class="text-indigo-600 hover:text-indigo-500" wire:navigate>
                    {{ __('Contact support') }}
                </a>
                {{ __('for formatting help.') }}
            </p>
        </div>
    </div>
</x-portal-layout>
