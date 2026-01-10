<div class="space-y-8" @if ($this->shouldPoll) wire:poll.8s @endif>

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Verification Jobs') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Track and manage all your historical verification data.') }}</p>
        </div>
        <a href="{{ route('portal.upload') }}"
            class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all flex items-center gap-2"
            wire:navigate>
            <i data-lucide="plus" class="w-5 h-5"></i> {{ __('New Verification') }}
        </a>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-[#E2E8F0] flex items-center gap-4">
        <div class="flex items-center gap-3 px-4 py-2 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
            <i data-lucide="filter" class="w-4 h-4 text-[#64748B]"></i>
            <select wire:model.live="status"
                class="bg-transparent border-none text-sm font-bold text-[#334155] focus:ring-0 cursor-pointer">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach (\App\Enums\VerificationJobStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- JOBS TABLE -->
    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('File Details') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Status') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Uploaded Date') }}</th>
                        <th
                            class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">
                            {{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($this->jobs as $job)
                        <tr class="hover:bg-[#F8FAFC] transition-colors group">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 bg-[#F1F5F9] rounded-xl flex items-center justify-center text-[#1E7CCF]">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#0F172A]">{{ $job->original_filename }}</p>
                                        <p class="text-[10px] font-mono text-[#94A3B8]">{{ $job->id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $job->status->badgeClasses() }}">
                                    {{ $job->status->label() }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-sm font-medium text-[#334155]">
                                {{ $job->created_at?->format('M d, Y') }}
                                <span
                                    class="block text-[10px] text-[#94A3B8]">{{ $job->created_at?->format('H:i A') }}</span>
                            </td>
                            <td class="px-8 py-5 text-right space-x-2">
                                <a href="{{ route('portal.jobs.show', $job) }}"
                                    class="inline-flex bg-[#F1F5F9] group-hover:bg-[#1E7CCF] group-hover:text-white text-[#334155] px-4 py-2 rounded-lg text-[11px] font-black uppercase transition-all"
                                    wire:navigate>
                                    {{ __('View Result') }}
                                </a>
                                @if ($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
                                    <a href="{{ route('portal.jobs.download', $job) }}"
                                        class="inline-flex bg-[#E9F2FB] text-[#1E7CCF] px-4 py-2 rounded-lg text-[11px] font-black uppercase transition-all hover:bg-[#1E7CCF] hover:text-white">
                                        <i data-lucide="download" class="w-3.5 h-3.5 mr-1"></i> {{ __('Get CSV') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-8 py-20 text-center">
                                <i data-lucide="inbox" class="w-12 h-12 text-[#CBD5E1] mx-auto mb-4"></i>
                                <h3 class="text-lg font-bold text-[#0F172A]">{{ __('No jobs found') }}</h3>
                                <p class="text-sm text-[#64748B]">
                                    {{ __('Try changing your filters or upload a new list.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-8 py-6 bg-[#F8FAFC] border-t border-[#E2E8F0]">
            {{ $this->jobs->links() }}
        </div>
    </div>
</div>


{{-- <x-portal-layout>
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

    <div class="space-y-6" @if ($this->shouldPoll) wire:poll.8s @endif>
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-medium text-gray-700">{{ __('Status') }}</label>
            <select wire:model="status" class="rounded-md border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach (\App\Enums\VerificationJobStatus::cases() as $statusOption)
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
                                @if ($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
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
</x-portal-layout> --}}
