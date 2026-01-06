<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Verification Jobs') }}
            </h2>
            <a href="{{ route('portal.upload') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
                {{ __('New Upload') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900" wire:poll.8s>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="pb-3">File</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Uploaded</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($this->jobs as $job)
                                    <tr class="text-gray-700">
                                        <td class="py-3">
                                            <div class="font-medium">{{ $job->original_filename }}</div>
                                            <div class="text-xs text-gray-500">{{ $job->id }}</div>
                                        </td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $job->status->badgeClasses() }}">
                                                {{ $job->status->label() }}
                                            </span>
                                        </td>
                                        <td class="py-3">{{ $job->created_at?->format('M d, Y H:i') }}</td>
                                        <td class="py-3 text-right">
                                            <a href="{{ route('portal.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-500" wire:navigate>
                                                {{ __('View') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-sm text-gray-500">
                                            {{ __('No verification jobs yet. Upload a list to get started.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
