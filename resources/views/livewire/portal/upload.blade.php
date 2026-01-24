<div class="space-y-10">
    <!-- 1. HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-black text-[#0F172A] tracking-tight">{{ __('Verify New List') }}</h1>
            <p class="text-[#64748B] font-medium mt-1">
                {{ __('Upload your CSV or TXT file to start a new verification job.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('portal.jobs.index') }}"
                class="text-sm font-bold text-[#1E7CCF] hover:underline flex items-center gap-1" wire:navigate>
                <i data-lucide="list-checks" class="w-4 h-4"></i> {{ __('View My Jobs') }}
            </a>
        </div>
    </div>

    @if ($errors->any())
        <x-flash type="error" :message="__('Please fix the errors below and try again.')" />
    @endif

    <!-- 2. MAIN UPLOAD CARD -->
    <div class="max-w-4xl mx-auto">
        <div class="bg-white p-4 rounded-[2.5rem] border border-[#E2E8F0] shadow-2xl shadow-blue-900/5">
            <form wire:submit.prevent="save" method="POST" action="{{ route('portal.upload.store') }}"
                enctype="multipart/form-data">
                @csrf

                <!-- THE INTERACTIVE DROP ZONE -->
                <div
                    class="border-2 border-dashed border-[#CBD5E1] rounded-[2rem] p-10 md:p-16 text-center bg-[#F8FAFC] transition-all hover:border-[#1E7CCF] group">

                    <div wire:loading.remove wire:target="file">
                        <div
                            class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-sm group-hover:scale-110 transition-transform">
                            <i data-lucide="upload-cloud" class="text-[#1E7CCF] w-10 h-10"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-[#0F172A] mb-2">{{ __('Select Your Data Source') }}</h3>
                        <p class="text-[#64748B] mb-8 font-medium">{{ __('Supported formats: CSV, TXT (Max 10MB)') }}
                        </p>
                    </div>

                    <!-- LIVEWIRE LOADING STATE (While file is uploading to server) -->
                    <div wire:loading wire:target="file" class="py-10">
                        <div
                            class="animate-spin rounded-full h-12 w-12 border-4 border-[#E9F2FB] border-t-[#1E7CCF] mx-auto mb-4">
                        </div>
                        <p class="text-sm font-bold text-[#1E7CCF] uppercase tracking-widest">
                            {{ __('Uploading to secure server...') }}</p>
                    </div>

                    <!-- THE ACTUAL INPUT (Styled to look like a pro button) -->
                    <div class="relative max-w-sm mx-auto">
                        <input id="file" name="file" type="file" wire:model="file"
                            class="block w-full text-sm text-[#64748B]
                            file:mr-4 file:py-3 file:px-6
                            file:rounded-xl file:border-0
                            file:text-sm file:font-bold
                            file:bg-[#1E7CCF] file:text-white
                            hover:file:bg-[#1866AD] cursor-pointer" />
                    </div>

                <!-- ERROR HANDLING (Keeping your backend logic) -->
                <x-input-error :messages="$errors->get('file')" class="mt-4 font-bold" />
                </div>

                @php
                    $enhancedGate = $this->enhancedGate;
                    $enhancedAllowed = $enhancedGate['allowed'] ?? false;
                @endphp

                <div class="mt-8 rounded-[2rem] border border-[#E2E8F0] bg-white p-6">
                    <div class="flex flex-col gap-2">
                        <h4 class="text-lg font-bold text-[#0F172A]">{{ __('Verification Mode') }}</h4>
                        <p class="text-sm text-[#64748B]">
                            {{ __('Standard runs the default verification pipeline. Enhanced performs mailbox-level checks (SG5) when available.') }}
                        </p>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-[#E2E8F0] p-4 hover:border-[#1E7CCF]">
                            <input type="radio" name="verification_mode" value="standard" wire:model="verification_mode"
                                class="mt-1 h-4 w-4 text-[#1E7CCF] focus:ring-[#1E7CCF]"
                                @checked(old('verification_mode', 'standard') === 'standard') />
                            <div>
                                <p class="text-sm font-bold text-[#0F172A]">{{ __('Standard') }}</p>
                                <p class="text-xs text-[#64748B]">{{ __('Recommended for most lists.') }}</p>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-2xl border border-[#E2E8F0] p-4 hover:border-[#1E7CCF] {{ $enhancedAllowed ? '' : 'opacity-60' }}"
                            title="{{ $enhancedAllowed ? '' : \App\Support\EnhancedModeGate::helperText(auth()->user()) }}">
                            <input type="radio" name="verification_mode" value="enhanced" wire:model="verification_mode"
                                class="mt-1 h-4 w-4 text-[#1E7CCF] focus:ring-[#1E7CCF]" @disabled(! $enhancedAllowed)
                                @checked(old('verification_mode') === 'enhanced') />
                            <div>
                                <p class="text-sm font-bold text-[#0F172A]">{{ __('Enhanced') }}</p>
                                <p class="text-xs text-[#64748B]">
                                    {{ $enhancedAllowed ? __('Enable for deeper checks when available.') : \App\Support\EnhancedModeGate::helperText(auth()->user()) }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <x-input-error :messages="$errors->get('verification_mode')" class="mt-3 font-bold" />
                </div>

                <!-- SUBMIT ACTIONS -->
                <div class="mt-8 px-4 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-2 text-[#64748B]">
                        <i data-lucide="shield-check" class="w-5 h-5 text-[#16A34A]"></i>
                        <span
                            class="text-xs font-bold uppercase tracking-wider">{{ __('Secured with AES-256 Encryption') }}</span>
                    </div>

                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,file"
                            class="w-full md:w-auto bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-10 py-4 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                            <span wire:loading.remove wire:target="save">{{ __('Start Verification') }}</span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4 animate-spin"></i> {{ __('Processing...') }}
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. HELP & TIPS SECTION -->
    <div class="max-w-4xl mx-auto grid md:grid-cols-2 gap-6">
        <div class="bg-[#F8FAFC] p-8 rounded-[2rem] border border-[#E2E8F0]">
            <h4 class="font-bold text-[#0F172A] mb-3 flex items-center gap-2">
                <i data-lucide="help-circle" class="text-[#1E7CCF] w-5 h-5"></i> {{ __('Formatting Help') }}
            </h4>
            <p class="text-sm text-[#64748B] leading-relaxed">
                {{ __('Ensure your file contains one email per line. If using CSV, you can include a header row—our system will automatically detect the email column.') }}
            </p>
        </div>

        <div class="bg-[#F8FAFC] p-8 rounded-[2rem] border border-[#E2E8F0]">
            <h4 class="font-bold text-[#0F172A] mb-3 flex items-center gap-2">
                <i data-lucide="info" class="text-[#1E7CCF] w-5 h-5"></i> {{ __('Need Support?') }}
            </h4>
            <p class="text-sm text-[#64748B] leading-relaxed">
                {{ __('If you have a custom file format or very large lists (50k+), please contact our') }}
                <a href="{{ route('portal.support') }}" class="text-[#1E7CCF] font-bold hover:underline"
                    wire:navigate>{{ __('technical support team') }}</a>.
            </p>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();
    // Re-render icons after Livewire updates
    document.addEventListener('livewire:load', () => lucide.createIcons());
    document.addEventListener('livewire:update', () => lucide.createIcons());
</script>
