<div class="space-y-10" @if ($this->shouldPoll) wire:poll.8s @endif>

    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Dashboard Overview') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Monitor your verification activity and credit usage at a glance.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('portal.upload') }}"
                class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all flex items-center gap-2"
                wire:navigate>
                <i data-lucide="plus-circle" class="w-5 h-5 text-white"></i> {{ __('Upload a list') }}
            </a>
        </div>
    </div>

    <!-- 2. STATS CARDS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

        <!-- Latest Order -->
        <div class="bg-white p-6 rounded-[2rem] border border-[#E2E8F0] shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-[#E9F2FB] text-[#1E7CCF] rounded-2xl flex items-center justify-center">
                    <i data-lucide="receipt" class="w-6 h-6"></i>
                </div>
                <a href="{{ route('portal.orders.index') }}" class="text-[#1E7CCF] text-xs font-bold hover:underline"
                    wire:navigate>{{ __('View orders') }}</a>
            </div>
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest">{{ __('Latest order') }}</p>
            @if ($this->latestOrder)
                @php
                    $orderCurrency = strtolower($this->latestOrder->currency) === 'usd' ? '$' : strtoupper($this->latestOrder->currency).' ';
                @endphp
                <h3 class="text-2xl font-black text-[#0F172A] mt-1">
                    {{ number_format($this->latestOrder->email_count) }} {{ __('emails') }}
                </h3>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-bold text-[#0F172A]">
                        {{ $orderCurrency }}{{ number_format($this->latestOrder->amount_cents / 100, 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase {{ $this->latestOrder->status->badgeClasses() }}">
                        {{ $this->latestOrder->status->label() }}
                    </span>
                </div>
                <p class="mt-2 text-[11px] font-bold text-[#94A3B8] uppercase truncate">
                    {{ __('Order') }}: <span class="font-mono text-[#334155]">{{ $this->latestOrder->id }}</span>
                </p>
            @else
                <h3 class="text-2xl font-black text-[#94A3B8] mt-1">{{ __('No orders yet') }}</h3>
                <p class="mt-3 text-[11px] font-bold text-[#94A3B8] uppercase">{{ __('Upload a list to get started') }}</p>
            @endif
        </div>

        <!-- Jobs in Queue -->
        <div class="bg-white p-6 rounded-[2rem] border border-[#E2E8F0] shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-[#FEF3C7] text-[#F59E0B] rounded-2xl flex items-center justify-center">
                    <i data-lucide="refresh-cw"
                        class="w-6 h-6 @if ($this->queueCount > 0) animate-spin @endif"></i>
                </div>
                <span
                    class="text-[#F59E0B] text-[10px] font-black uppercase tracking-tighter">{{ __('Active Queue') }}</span>
            </div>
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest">{{ __('Jobs in queue') }}</p>
            <h3 class="text-3xl font-black text-[#0F172A] mt-1">{{ $this->queueCount }}</h3>
            <p class="mt-3 text-[11px] font-bold text-[#94A3B8] uppercase">{{ __('Pending + Processing') }}</p>
        </div>

        <!-- Verified this month -->
        <div class="bg-white p-6 rounded-[2rem] border border-[#E2E8F0] shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-[#DCFCE7] text-[#16A34A] rounded-2xl flex items-center justify-center">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
            </div>
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest">{{ __('Verified this month') }}</p>
            <h3 class="text-3xl font-black text-[#0F172A] mt-1">{{ number_format($this->verifiedJobsThisMonth) }}</h3>
            <p class="mt-3 text-[11px] font-bold text-[#94A3B8] uppercase">{{ __('Completed Jobs') }}</p>
        </div>

        <!-- Latest Job Status -->
        <div class="bg-white p-6 rounded-[2rem] border border-[#E2E8F0] shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-[#F1F5F9] text-[#334155] rounded-2xl flex items-center justify-center">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                @if ($this->latestJob)
                    <span
                        class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-black uppercase {{ $this->latestJob->status->badgeClasses() }}">
                        {{ $this->latestJob->status->label() }}
                    </span>
                @endif
            </div>
            <p class="text-[#64748B] text-xs font-bold uppercase tracking-widest">{{ __('Latest Job') }}</p>
            @if ($this->latestJob)
                <h3 class="text-sm font-bold text-[#0F172A] mt-2 truncate">{{ $this->latestJob->original_filename }}
                </h3>
                <p class="mt-1 text-[11px] font-bold text-[#94A3B8]">
                    {{ $this->latestJob->created_at?->format('M d, Y H:i') }}</p>
            @else
                <h3 class="text-sm font-bold text-[#94A3B8] mt-2 italic">{{ __('No jobs yet') }}</h3>
            @endif
        </div>
    </div>

    <!-- 3. RECENT JOBS TABLE SECTION -->
    <div class="bg-white rounded-[2.5rem] border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="px-8 py-6 border-b border-[#E2E8F0] flex items-center justify-between bg-white">
            <h3 class="text-xl font-bold text-[#0F172A]">{{ __('Recent Verification Jobs') }}</h3>
            <a href="{{ route('portal.jobs.index') }}"
                class="text-sm font-bold text-[#1E7CCF] hover:underline flex items-center gap-1" wire:navigate>
                {{ __('View All') }} <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#F8FAFC] border-b border-[#E2E8F0]">
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('File Name / ID') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Created At') }}</th>
                        <th class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest">
                            {{ __('Status') }}</th>
                        <th
                            class="px-8 py-4 text-[11px] font-black text-[#64748B] uppercase tracking-widest text-right">
                            {{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E2E8F0]">
                    @forelse($this->recentJobs as $job)
                        <tr class="hover:bg-[#F8FAFC] transition-colors group">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 bg-[#F1F5F9] rounded-lg flex items-center justify-center text-[#1E7CCF]">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#0F172A]">{{ $job->original_filename }}</p>
                                        <p class="text-xs text-[#94A3B8]">{{ __('ID') }}: {{ $job->id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-sm font-medium text-[#334155]">
                                {{ $job->created_at?->format('M d, Y H:i') }}
                            </td>
                            <td class="px-8 py-5">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $job->status->badgeClasses() }}">
                                    {{ $job->status->label() }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right space-x-2">
                                <a href="{{ route('portal.jobs.show', $job) }}"
                                    class="inline-flex bg-[#F1F5F9] group-hover:bg-[#1E7CCF] group-hover:text-white text-[#334155] px-4 py-2 rounded-lg text-[11px] font-black uppercase transition-all"
                                    wire:navigate>
                                    {{ __('View Details') }}
                                </a>
                                @if ($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
                                    <a href="{{ route('portal.jobs.download', $job) }}"
                                        class="inline-flex bg-[#E9F2FB] text-[#1E7CCF] px-4 py-2 rounded-lg text-[11px] font-black uppercase transition-all hover:bg-[#1E7CCF] hover:text-white">
                                        {{ __('Download') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-8 py-16 text-center">
                                <div class="max-w-xs mx-auto">
                                    <i data-lucide="search-x" class="w-12 h-12 text-[#CBD5E1] mx-auto mb-4"></i>
                                    <h3 class="text-lg font-bold text-[#0F172A]">{{ __('No jobs yet') }}</h3>
                                    <p class="text-sm text-[#64748B] mt-1">
                                        {{ __('Upload a list to start your first verification scan.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. HELP SECTION -->
    <div
        class="rounded-3xl border border-[#1E7CCF]/20 bg-[#E9F2FB] p-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center text-[#1E7CCF] shadow-sm">
                <i data-lucide="help-circle" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="text-lg font-bold text-[#0F172A]">{{ __('Need help with your list?') }}</h4>
                <p class="text-sm text-[#1E7CCF] font-medium">
                    {{ __('Upload CSV or TXT files with one email per line for the best results.') }}</p>
            </div>
        </div>
        <a href="{{ route('portal.support') }}"
            class="bg-white text-[#1E7CCF] px-6 py-3 rounded-xl font-bold shadow-sm hover:shadow-md transition-all"
            wire:navigate>
            {{ __('Contact Support') }}
        </a>
    </div>
</div>

<script>
    // Refresh icons on Livewire load/update
    lucide.createIcons();
    document.addEventListener('livewire:load', () => lucide.createIcons());
    document.addEventListener('livewire:update', () => lucide.createIcons());
</script>
