<div x-data="{ open: @entangle('showModal') }" @keydown.escape.window="open = false" x-cloak wire:ignore.self>

    <!-- Heavy Backdrop -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9998] bg-[#0F172A]/80 backdrop-blur-md">
    </div>

    <!-- Modal Container -->
    <div x-show="open" class="fixed inset-0 z-[9999] overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">

            <div x-show="open" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-[2.5rem] bg-white text-left shadow-2xl transition-all sm:my-8 w-full sm:max-w-lg border border-[#E2E8F0]"
                @click.away="open = false">

                <!-- Header Section -->
                <div class="bg-gradient-to-br from-[#1E7CCF] to-[#1669B2] px-8 pt-10 pb-12 text-white relative">
                    <div class="relative z-10 flex items-start justify-between">
                        <div class="flex flex-col gap-4">
                            <div
                                class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-md shadow-inner">
                                <i data-lucide="plus-circle" class="w-8 h-8 text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-3xl font-black tracking-tight leading-none mb-2">{{ __('Add Balance') }}
                                </h2>
                                <p class="text-white/70 text-sm font-medium">
                                    {{ __('Top up your account for automatic verifications.') }}</p>
                            </div>
                        </div>
                        <button @click="open = false"
                            class="p-2 -mt-2 -mr-2 rounded-xl hover:bg-white/10 transition-colors">
                            <i data-lucide="x" class="w-6 h-6 text-white"></i>
                        </button>
                    </div>

                    <!-- Decorative background element -->
                    <div class="absolute right-0 bottom-0 opacity-10">
                        <i data-lucide="wallet" class="w-32 h-32 -mb-8 -mr-8"></i>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="p-8 md:p-10 space-y-8 bg-white">
                    <form wire:submit.prevent="submit" class="space-y-6">
                        <div class="space-y-3">
                            <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-[0.2em] px-1">
                                {{ __('Amount to deposit (USD)') }}
                            </label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-7 flex items-center pointer-events-none">
                                    <span class="text-2xl font-black text-[#0F172A]">$</span>
                                </div>
                                <input type="number" step="0.01" wire:model.live="amount" required
                                    class="w-full pl-14 pr-8 py-6 bg-[#F8FAFC] border-2 border-[#F1F5F9] rounded-[1.5rem] focus:border-[#1E7CCF] focus:ring-0 transition-all font-black text-4xl text-[#0F172A] placeholder-[#CBD5E1]"
                                    placeholder="0.00">
                            </div>
                            @error('amount')
                                <p class="text-xs text-red-500 font-bold mt-2 italic">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Presets -->
                        <div class="grid grid-cols-3 gap-3">
                            @foreach([10, 50, 100] as $preset)
                                <button type="button" @click="$wire.set('amount', {{ $preset }})"
                                    class="py-4 rounded-2xl border-2 font-black transition-all text-sm
                                        {{ (float) $amount === (float) $preset ? 'bg-[#E9F2FB] border-[#1E7CCF] text-[#1E7CCF] shadow-sm' : 'bg-white border-[#F1F5F9] text-[#64748B] hover:border-[#CBD5E1]' }}">
                                    ${{ $preset }}
                                </button>
                            @endforeach
                        </div>

                        <div class="pt-2">
                            <button type="submit" wire:loading.attr="disabled"
                                class="w-full bg-[#1E7CCF] hover:bg-[#1866AD] text-white py-5 rounded-2xl font-black uppercase tracking-[0.15em] text-xs shadow-xl shadow-blue-100 transition-all flex items-center justify-center gap-3 active:scale-[0.98] group">
                                <span wire:loading.remove>{{ __('Proceed to Secure Payment') }}</span>
                                <span wire:loading class="flex items-center gap-2">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 animate-spin"></i>
                                    {{ __('Preparing...') }}
                                </span>
                                <i wire:loading.remove data-lucide="arrow-right"
                                    class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                            </button>
                        </div>
                    </form>

                    <div class="flex items-center justify-center gap-3 py-2">
                        <div class="flex -space-x-1 opacity-40">
                            <div class="w-8 h-5 bg-slate-200 rounded"></div>
                            <div class="w-8 h-5 bg-slate-100 rounded"></div>
                        </div>
                        <span
                            class="text-[9px] font-black text-[#94A3B8] uppercase tracking-widest">{{ __('Verified Secure Stripe Checkout') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>