<div class="space-y-8" @if ($this->shouldPoll) wire:poll.8s @endif>

    <!-- HEADER -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('portal.jobs.index') }}"
                class="w-10 h-10 bg-white border border-[#E2E8F0] rounded-xl flex items-center justify-center text-[#64748B] hover:text-[#1E7CCF] transition-all shadow-sm"
                wire:navigate>
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black text-[#0F172A] tracking-tight">{{ __('Job Analysis') }}</h1>
                <div class="mt-1 flex items-center gap-2">
                    <p class="text-xs font-mono text-[#94A3B8]">{{ $job->id }}</p>
                    <button type="button" class="text-[10px] font-black text-[#1E7CCF] uppercase" x-data="{ copied: false }"
                        @click="navigator.clipboard.writeText('{{ $job->id }}').then(() => { copied = true; setTimeout(() => copied = false, 1500); })">
                        <span x-show="!copied">{{ __('Copy ID') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-black uppercase {{ $job->status->badgeClasses() }}">
                {{ $job->status->label() }}
            </span>
            @if ($job->status === \App\Enums\VerificationJobStatus::Completed)
                @php
                    $hasFinalKeys = $job->valid_key || $job->invalid_key || $job->risky_key;
                @endphp
                @if ($hasFinalKeys)
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($job->valid_key || $job->output_key)
                            <a href="{{ route('portal.jobs.download', $job) }}?type=valid"
                                class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-4 py-2 rounded-xl text-sm font-bold shadow-xl shadow-blue-100 transition-all flex items-center gap-2">
                                <i data-lucide="download-cloud" class="w-4 h-4"></i> {{ __('Download Valid') }}
                            </a>
                        @endif
                        @if ($job->invalid_key)
                            <a href="{{ route('portal.jobs.download', $job) }}?type=invalid"
                                class="bg-white border border-[#E2E8F0] text-[#0F172A] px-4 py-2 rounded-xl text-sm font-bold shadow-sm transition-all flex items-center gap-2 hover:border-[#CBD5E1]">
                                <i data-lucide="download-cloud" class="w-4 h-4"></i> {{ __('Download Invalid') }}
                            </a>
                        @endif
                        @if ($job->risky_key)
                            <a href="{{ route('portal.jobs.download', $job) }}?type=risky"
                                class="bg-white border border-[#E2E8F0] text-[#0F172A] px-4 py-2 rounded-xl text-sm font-bold shadow-sm transition-all flex items-center gap-2 hover:border-[#CBD5E1]">
                                <i data-lucide="download-cloud" class="w-4 h-4"></i> {{ __('Download Risky') }}
                            </a>
                        @endif
                    </div>
                @elseif ($job->output_key)
                    <a href="{{ route('portal.jobs.download', $job) }}"
                        class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-6 py-3 rounded-xl font-bold shadow-xl shadow-blue-100 transition-all flex items-center gap-2">
                        <i data-lucide="download-cloud" class="w-5 h-5"></i> {{ __('Download Results') }}
                    </a>
                @endif
            @endif
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid lg:grid-cols-3 gap-8">

        <!-- Result Breakdown (Left) -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                <h3 class="text-lg font-bold text-[#0F172A] mb-8">{{ __('Verification Summary') }}</h3>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
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
                </div>
                <p class="mt-6 text-xs text-[#64748B]">
                    {{ __('Deliverability Confidence Score (0–100) appears in your downloaded CSV and reflects how strongly each result is supported.') }}
                </p>
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
                        <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">
                            {{ __('Status Flow') }}</p>
                        <div class="mt-4 flex items-center gap-3 text-[10px] font-black uppercase text-[#64748B]">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-[#1E7CCF]"></span>
                                <span>{{ __('Created') }}</span>
                            </div>
                            <div class="h-px w-6 bg-[#E2E8F0]"></div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ in_array($job->status, [\App\Enums\VerificationJobStatus::Processing, \App\Enums\VerificationJobStatus::Completed, \App\Enums\VerificationJobStatus::Failed], true) ? 'bg-[#1E7CCF]' : 'bg-[#CBD5E1]' }}"></span>
                                <span>{{ __('Processing') }}</span>
                            </div>
                            <div class="h-px w-6 bg-[#E2E8F0]"></div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $job->status === \App\Enums\VerificationJobStatus::Completed ? 'bg-[#16A34A]' : ($job->status === \App\Enums\VerificationJobStatus::Failed ? 'bg-[#DC2626]' : 'bg-[#CBD5E1]') }}"></span>
                                <span>{{ $job->status === \App\Enums\VerificationJobStatus::Failed ? __('Failed') : __('Completed') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-[#F1F5F9]">
                        <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">
                            {{ __('Verification Pipeline') }}</p>
                        <div class="mt-3">
                            <span class="inline-flex items-center rounded-full bg-[#F1F5F9] px-3 py-1 text-[10px] font-black uppercase text-[#64748B]">
                                {{ __('Deep Verification') }}
                            </span>
                            <p class="mt-2 text-xs text-[#64748B]">
                                {{ __('Verification runs automatically. Mailbox probe stages are applied based on internal policy gates.') }}
                            </p>
                        </div>
                    </div>

                    @if ((bool) config('seed_send.enabled', false) && $job->status === \App\Enums\VerificationJobStatus::Completed)
                        <div class="pt-6 border-t border-[#F1F5F9]">
                            <p class="text-[10px] font-black uppercase text-[#94A3B8] tracking-widest">
                                {{ __('SG6 Seed-Send Verification') }}</p>
                            <div class="mt-3 space-y-3">
                                @if (! $latestSeedSendConsent)
                                    <p class="text-xs text-[#64748B]">
                                        {{ __('Optional premium check: request SG6 consent for slow seed-send evidence after finalization.') }}
                                    </p>
                                    <form method="POST" action="{{ route('portal.jobs.seed-send-consent', $job) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center rounded-xl bg-[#0F172A] px-4 py-2 text-xs font-bold text-white hover:bg-[#1E293B] transition-all">
                                            {{ __('Request SG6 Consent') }}
                                        </button>
                                    </form>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-[#475569]">{{ __('Consent') }}:</span>
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase
                                            {{ $latestSeedSendConsent->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($latestSeedSendConsent->status === 'requested' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                            {{ ucfirst($latestSeedSendConsent->status) }}
                                        </span>
                                    </div>

                                    @if ($latestSeedSendCampaign)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-[#475569]">{{ __('Campaign') }}:</span>
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase
                                                {{ in_array($latestSeedSendCampaign->status, ['queued', 'running'], true) ? 'bg-amber-100 text-amber-700' : ($latestSeedSendCampaign->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                                {{ ucfirst($latestSeedSendCampaign->status) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="text-xs text-[#64748B]">
                                            {{ __('Campaign has not started yet. Admin approval and start are required.') }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif

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
                                <span class="text-[#64748B]">{{ __('Prepared') }}</span>
                                <span
                                    class="font-bold text-[#0F172A]">{{ $job->prepared_at?->format('H:i, M d') ?? '--' }}</span>
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

            <div class="bg-white p-6 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-[#0F172A]">{{ __('Job Activity') }}</h3>
                    <p class="text-xs text-[#94A3B8]">{{ __('Most recent job events and status updates.') }}</p>
                </div>
                <div class="space-y-4">
                    @forelse($activityLogs as $log)
                        <div class="rounded-2xl border border-[#F1F5F9] p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="inline-flex items-center rounded-full bg-[#F1F5F9] px-3 py-1 text-[10px] font-black uppercase text-[#64748B]">
                                    {{ str_replace('_', ' ', ucfirst((string) $log->event)) }}
                                </span>
                                <span class="text-[10px] text-[#94A3B8]" title="{{ $log->created_at?->format('M d, Y H:i') }}">
                                    {{ $log->created_at?->diffForHumans() }}
                                </span>
                            </div>
                            @if($log->message)
                                <p class="mt-2 text-sm text-[#334155]">{{ $log->message }}</p>
                            @endif
                            @if(! empty($log->context))
                                <details class="mt-2 text-xs text-[#64748B]">
                                    <summary class="cursor-pointer">{{ __('View context') }}</summary>
                                    <pre class="mt-2 whitespace-pre-wrap">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </div>
                    @empty
                        <div class="text-sm text-[#64748B]">
                            {{ __('No activity yet for this job.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
