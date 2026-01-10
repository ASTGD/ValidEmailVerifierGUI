<x-portal-layout>
    <x-slot name="header">
        <div>
            <nav class="text-xs text-gray-500">
                <a href="{{ route('portal.jobs.index') }}" class="hover:text-gray-700" wire:navigate>
                    {{ __('Jobs') }}
                </a>
                <span class="mx-2">/</span>
                <span class="text-gray-700">{{ __('Job Details') }}</span>
            </nav>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Job Details') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Track status, timestamps, and results for this job.') }}</p>
        </div>
    </x-slot>
    <x-slot name="headerAction">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('portal.upload') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500" wire:navigate>
                {{ __('Upload list') }}
            </a>
            <a href="{{ route('portal.jobs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
                {{ __('Back to Jobs') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6" @if($this->shouldPoll) wire:poll.8s @endif>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-sm text-gray-500">{{ __('Job ID') }}</div>
                <div class="flex items-center gap-2">
                    <div class="font-mono text-xs text-gray-700">{{ $job->id }}</div>
                    <button type="button" class="text-xs text-indigo-600 hover:text-indigo-500" x-data="{ copied: false }" @click="navigator.clipboard.writeText('{{ $job->id }}').then(() => { copied = true; setTimeout(() => copied = false, 1500); })">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                {{ $job->status->label() }}
            </span>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $job->status === \App\Enums\VerificationJobStatus::Pending ? 'bg-indigo-600' : 'bg-gray-300' }}"></span>
                    <span class="text-xs font-semibold text-gray-600">{{ __('Created') }}</span>
                </div>
                <div class="h-px w-6 bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ in_array($job->status, [\App\Enums\VerificationJobStatus::Processing, \App\Enums\VerificationJobStatus::Completed, \App\Enums\VerificationJobStatus::Failed], true) ? 'bg-indigo-600' : 'bg-gray-300' }}"></span>
                    <span class="text-xs font-semibold text-gray-600">{{ __('Processing') }}</span>
                </div>
                <div class="h-px w-6 bg-gray-200"></div>
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $job->status === \App\Enums\VerificationJobStatus::Completed ? 'bg-green-500' : ($job->status === \App\Enums\VerificationJobStatus::Failed ? 'bg-red-500' : 'bg-gray-300') }}"></span>
                    <span class="text-xs font-semibold text-gray-600">
                        {{ $job->status === \App\Enums\VerificationJobStatus::Failed ? __('Failed') : __('Completed') }}
                    </span>
                </div>
            </div>
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
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Total') }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $job->total_emails ?? '-' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Valid') }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $job->valid_count ?? '-' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Invalid') }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $job->invalid_count ?? '-' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Risky') }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $job->risky_count ?? '-' }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Unknown') }}</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $job->unknown_count ?? '-' }}</div>
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

        <div class="rounded-lg border border-gray-200">
            <div class="border-b border-gray-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Job Activity') }}</h3>
                <p class="text-xs text-gray-500">{{ __('Most recent job events and status updates.') }}</p>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($activityLogs as $log)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                                    {{ str_replace('_', ' ', ucfirst((string) $log->event)) }}
                                </span>
                                <span class="text-xs text-gray-500" title="{{ $log->created_at?->format('M d, Y H:i') }}">
                                    {{ $log->created_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                        @if($log->message)
                            <p class="mt-2 text-sm text-gray-700">{{ $log->message }}</p>
                        @endif
                        @if(! empty($log->context))
                            <details class="mt-2 text-xs text-gray-500">
                                <summary class="cursor-pointer">{{ __('View context') }}</summary>
                                <pre class="mt-2 whitespace-pre-wrap">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-gray-500">
                        {{ __('No activity yet for this job.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-portal-layout>
