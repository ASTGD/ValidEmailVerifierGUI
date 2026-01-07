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
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-medium text-gray-700">{{ __('Status') }}</label>
            <select wire:model="status" class="rounded-md border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach(\App\Enums\VerificationJobStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-500 uppercase tracking-wider bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">File</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Uploaded</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($this->jobs as $job)
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
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                {{ __('No verification jobs yet. Upload a list to get started.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->jobs->links() }}
        </div>
    </div>
</x-portal-layout>
