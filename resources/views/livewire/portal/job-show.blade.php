<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Job Details') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Track status, timestamps, and results for this job.') }}</p>
        </div>
    </x-slot>
    <x-slot name="headerAction">
        <a href="{{ route('portal.jobs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
            {{ __('Back to Jobs') }}
        </a>
    </x-slot>

    <div class="space-y-6" @if($this->shouldPoll) wire:poll.8s @endif>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-sm text-gray-500">{{ __('Job ID') }}</div>
                <div class="font-mono text-xs text-gray-700">{{ $job->id }}</div>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                {{ $job->status->label() }}
            </span>
        </div>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-gray-500">{{ __('Original filename') }}</dt>
                <dd class="font-medium text-gray-900">{{ $job->original_filename }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('Uploaded') }}</dt>
                <dd class="font-medium text-gray-900">{{ $job->created_at?->format('M d, Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('Started') }}</dt>
                <dd class="font-medium text-gray-900">{{ $job->started_at?->format('M d, Y H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('Finished') }}</dt>
                <dd class="font-medium text-gray-900">{{ $job->finished_at?->format('M d, Y H:i') ?? '-' }}</dd>
            </div>
        </dl>

        <div>
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Results summary') }}</h3>
            <div class="mt-3 grid grid-cols-2 gap-4 text-sm sm:grid-cols-5">
                <div>
                    <div class="text-gray-500">{{ __('Total') }}</div>
                    <div class="font-semibold text-gray-900">{{ $job->total_emails ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Valid') }}</div>
                    <div class="font-semibold text-gray-900">{{ $job->valid_count ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Invalid') }}</div>
                    <div class="font-semibold text-gray-900">{{ $job->invalid_count ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Risky') }}</div>
                    <div class="font-semibold text-gray-900">{{ $job->risky_count ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Unknown') }}</div>
                    <div class="font-semibold text-gray-900">{{ $job->unknown_count ?? '-' }}</div>
                </div>
            </div>
        </div>

        @if($job->error_message)
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                {{ $job->error_message }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                {{ __('Downloads are available when the job is completed.') }}
            </div>
            @if($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
                <a href="{{ route('portal.jobs.download', $job) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500">
                    {{ __('Download Results') }}
                </a>
            @endif
        </div>
    </div>
</x-portal-layout>
