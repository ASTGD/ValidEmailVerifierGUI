<div class="space-y-8" @if ($this->shouldPoll) wire:poll.8s @endif>

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('portal.jobs.index') }}"
                class="w-10 h-10 bg-white border border-[#E2E8F0] rounded-xl flex items-center justify-center text-[#64748B] hover:text-[#1E7CCF] transition-all shadow-sm"
                wire:navigate>
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-[#0F172A] tracking-tight">{{ __('Job Analysis') }}</h1>
                <p class="text-xs font-mono text-[#94A3B8]">{{ $job->id }}</p>
            </div>
        </div>

        @if ($job->status === \App\Enums\VerificationJobStatus::Completed && $job->output_key)
            <a href="{{ route('portal.jobs.download', $job) }}"
                class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-8 py-3.5 rounded-xl font-bold shadow-xl shadow-blue-100 transition-all flex items-center gap-2">
                <i data-lucide="download-cloud" class="w-5 h-5"></i> {{ __('Download Results') }}
            </a>
        @endif
    </div>

    <!-- MAIN GRID -->
    <div class="grid lg:grid-cols-3 gap-8">

        <!-- Result Breakdown (Left) -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                <h3 class="text-lg font-bold text-[#0F172A] mb-8">{{ __('Verification Summary') }}</h3>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div class="p-6 bg-[#F8FAFC] rounded-2xl border border-[#E2E8F0]">
                        <p class="text-[10px] font-black uppercase text-[#64748B] tracking-widest mb-1">
                            {{ __('Total') }}</p>
                        <p class="text-3xl font-black text-[#0F172A]">{{ number_format($job->total_emails ?? 0) }}</p>
                    </div>
                    <div class="p-6 bg-[#DCFCE7] rounded-2xl border border-[#16A34A]/10">
                        <p class="text-[10px] font-black uppercase text-[#16A34A] tracking-widest mb-1">
                            {{ __('Valid') }}</p>
                        <p class="text-3xl font-black text-[#16A34A]">{{ number_format($job->valid_count ?? 0) }}</p>
                    </div>
                    <div class="p-6 bg-[#FEE2E2] rounded-2xl border border-[#DC2626]/10">
                        <p class="text-[10px] font-black uppercase text-[#DC2626] tracking-widest mb-1">
                            {{ __('Invalid') }}</p>
                        <p class="text-3xl font-black text-[#DC2626]">{{ number_format($job->invalid_count ?? 0) }}</p>
                    </div>
                    <div class="p-6 bg-[#FEF3C7] rounded-2xl border border-[#F59E0B]/10">
                        <p class="text-[10px] font-black uppercase text-[#F59E0B] tracking-widest mb-1">
                            {{ __('Risky') }}</p>
                        <p class="text-3xl font-black text-[#F59E0B]">{{ number_format($job->risky_count ?? 0) }}</p>
                    </div>
                    <div class="p-6 bg-[#F1F5F9] rounded-2xl border border-[#E2E8F0]">
                        <p class="text-[10px] font-black uppercase text-[#64748B] tracking-widest mb-1">
                            {{ __('Unknown') }}</p>
                        <p class="text-3xl font-black text-[#0F172A]">{{ number_format($job->unknown_count ?? 0) }}</p>
                    </div>
                </div>
            </div>

            @if ($job->error_message)
                <div class="bg-[#FEE2E2] border border-[#DC2626]/20 p-6 rounded-2xl flex items-center gap-4">
                    <i data-lucide="alert-circle" class="text-[#DC2626] w-6 h-6"></i>
                    <p class="text-sm font-bold text-[#DC2626]">{{ $job->error_message }}</p>
                </div>
            @endif
        </div>

        <!-- Job Details Sidebar (Right) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                <h3 class="text-lg font-bold text-[#0F172A] mb-6">{{ __('Job Details') }}</h3>

                <div class="space-y-6">
                    <div>
                        <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">
                            {{ __('Current Status') }}</p>
                        <span
                            class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $job->status->badgeClasses() }}">
                            {{ $job->status->label() }}
                        </span>
                    </div>

                    <div class="pt-6 border-t border-[#F1F5F9]">
                        <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">{{ __('Timeline') }}
                        </p>
                        <ul class="mt-4 space-y-4">
                            <li class="flex justify-between text-sm">
                                <span class="text-[#64748B]">{{ __('Uploaded') }}</span>
                                <span
                                    class="font-bold text-[#0F172A]">{{ $job->created_at?->format('H:i, M d') }}</span>
                            </li>
                            <li class="flex justify-between text-sm">
                                <span class="text-[#64748B]">{{ __('Started') }}</span>
                                <span
                                    class="font-bold text-[#0F172A]">{{ $job->started_at?->format('H:i, M d') ?? '--' }}</span>
                            </li>
                            <li class="flex justify-between text-sm">
                                <span class="text-[#64748B]">{{ __('Finished') }}</span>
                                <span
                                    class="font-bold text-[#0F172A]">{{ $job->finished_at?->format('H:i, M d') ?? '--' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="pt-6 border-t border-[#F1F5F9]">
                        <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">
                            {{ __('Source File') }}</p>
                        <p class="mt-2 text-sm font-bold text-[#334155] truncate">{{ $job->original_filename }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    document.addEventListener('livewire:load', () => lucide.createIcons());
    document.addEventListener('livewire:update', () => lucide.createIcons());
</script>
