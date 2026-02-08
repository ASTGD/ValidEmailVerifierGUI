<div class="bg-white p-8 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm"
    @if ($this->shouldPoll) wire:poll.6s @endif>
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h3 class="text-xl font-bold text-[#0F172A]">{{ __('Single Email Test') }}</h3>
            <p class="text-sm text-[#64748B] mt-1">
                {{ __('Run a quick mailbox verification for one address.') }}
            </p>
        </div>
    </div>

    <form wire:submit.prevent="submit" class="mt-6 grid gap-5 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-[#0F172A] mb-2">{{ __('Email address') }}</label>
            <input type="email" wire:model.defer="email"
                class="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 text-sm text-[#0F172A] placeholder-[#94A3B8] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]"
                placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 font-bold" />
        </div>

        <div class="md:col-span-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                class="w-full md:w-auto bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">{{ __('Verify') }}</span>
                <span wire:loading wire:target="submit" class="flex items-center justify-center gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4 animate-spin"></i> {{ __('Submitting...') }}
                </span>
            </button>
        </div>
    </form>

    @if ($this->singleCheckJob)
        <div class="mt-6 border-t border-[#E2E8F0] pt-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-bold uppercase tracking-widest text-[#64748B]">{{ __('Job status') }}</span>
                <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-black uppercase {{ $this->singleCheckJob->status->badgeClasses() }}">
                    {{ $this->singleCheckJob->status->label() }}
                </span>
            </div>

            @if ($this->singleCheckJob->status === \App\Enums\VerificationJobStatus::Processing || $this->singleCheckJob->status === \App\Enums\VerificationJobStatus::Pending)
                <div class="mt-4 flex items-center gap-3 text-sm text-[#64748B]">
                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-[#E2E8F0] border-t-[#1E7CCF]">
                    </div>
                    {{ __('Processing...') }}
                </div>
            @elseif ($this->singleCheckResult)
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-[#E2E8F0] p-4">
                        <p class="text-xs font-bold uppercase text-[#64748B]">{{ __('Result status') }}</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $this->singleCheckResult['status'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E8F0] p-4">
                        <p class="text-xs font-bold uppercase text-[#64748B]">{{ __('Email') }}</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $this->singleCheckResult['email'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E8F0] p-4">
                        <p class="text-xs font-bold uppercase text-[#64748B]">{{ __('Sub-status') }}</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $this->singleCheckResult['sub_status'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E8F0] p-4">
                        <p class="text-xs font-bold uppercase text-[#64748B]">{{ __('Score') }}</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">
                            {{ $this->singleCheckResult['score'] !== null ? $this->singleCheckResult['score'] : '-' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E8F0] p-4">
                        <p class="text-xs font-bold uppercase text-[#64748B]">{{ __('Reason') }}</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-1">{{ $this->singleCheckResult['reason'] ?? '-' }}</p>
                    </div>
                </div>
                <div class="mt-4 text-xs font-bold uppercase text-[#94A3B8]">
                    {{ __('Verified at') }}:
                    <span class="text-[#0F172A]">
                        {{ $this->singleCheckResult['verified_at']?->format('M d, Y H:i') ?? '-' }}
                    </span>
                </div>
            @elseif ($this->singleCheckJob->status === \App\Enums\VerificationJobStatus::Failed)
                <div class="mt-4 text-sm font-medium text-red-600">
                    {{ $this->singleCheckJob->error_message ?: __('Single check failed.') }}
                </div>
            @endif
        </div>
    @endif
</div>

<script>
    lucide.createIcons();
    document.addEventListener('livewire:load', () => lucide.createIcons());
    document.addEventListener('livewire:update', () => lucide.createIcons());
</script>
