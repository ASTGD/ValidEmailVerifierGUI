<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Jobs') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Track all verification jobs in one place.') }}</p>
        </div>
    </x-slot>
    <x-slot name="headerAction">
        <a href="{{ route('portal.upload') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500" wire:navigate>
            {{ __('Verify List') }}
        </a>
    </x-slot>

    <div class="space-y-6" @if($this->shouldPoll) wire:poll.8s @endif>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Search') }}</label>
                    <div class="mt-1">
                        <input
                            type="text"
                            wire:model.debounce.400ms="search"
                            placeholder="{{ __('Search filename or job ID') }}"
                            class="w-64 rounded-md border-gray-300 text-sm"
                        />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Status') }}</label>
                    <div class="mt-1">
                        <select wire:model="status" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('All') }}</option>
                            @foreach(\App\Enums\VerificationJobStatus::cases() as $statusOption)
                                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if($search || $status)
                <button type="button" wire:click="clearFilters" class="text-xs font-semibold uppercase tracking-widest text-gray-500 hover:text-gray-700">
                    {{ __('Clear filters') }}
                </button>
            @endif
        </div>

        @if($search || $status)
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if($search)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-gray-600">
                        {{ __('Search: :term', ['term' => $search]) }}
                    </span>
                @endif
                @if($status)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-gray-600">
                        {{ __('Status: :status', ['status' => ucfirst($status)]) }}
                    </span>
                @endif
            </div>
        @endif

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 uppercase tracking-wider bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">File</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">
                                {{ __('Status') }}
                                @if($sortField === 'status')
                                    <span class="text-xs text-gray-400">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1">
                                {{ __('Uploaded') }}
                                @if($sortField === 'created_at')
                                    <span class="text-xs text-gray-400">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @if($this->jobs->count() > 0)
                        @foreach($this->jobs as $job)
                            <tr class="text-gray-700">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $job->original_filename }}</div>
                                    <div class="text-xs text-gray-500">{{ $job->id }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                                        {{ $job->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $job->created_at?->format('M d, Y H:i') }}</td>
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
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="px-4 py-8">
                                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-6 text-center">
                                    <p class="text-sm font-medium text-gray-700">
                                        {{ $search || $status ? __('No jobs match your filters.') : __('No verification jobs yet.') }}
                                    </p>
                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ $search || $status ? __('Try clearing filters or upload a new list.') : __('Upload a list to get started.') }}
                                    </p>
                                    <div class="mt-4 flex justify-center gap-3">
                                        @if($search || $status)
                                            <button type="button" wire:click="clearFilters" class="inline-flex items-center rounded-md border border-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-600 hover:bg-gray-100">
                                                {{ __('Clear filters') }}
                                            </button>
                                        @endif
                                        <a href="{{ route('portal.upload') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-500" wire:navigate>
                                            {{ __('Upload list') }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->jobs->links() }}
        </div>
    </div>
</x-portal-layout>
