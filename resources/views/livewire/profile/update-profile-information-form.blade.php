<section class="bg-white p-8 md:p-10 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
    <header class="mb-8">
        <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-2 text-[#64748B] font-medium">
            {{ __("Update your account's public profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-6">
        <!-- Name -->
        <div class="max-w-xl">
            <x-input-label for="name" :value="__('Full Name')" class="font-bold text-[#334155] mb-2" />
            <x-text-input wire:model="name" id="name" name="name" type="text"
                class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]/20"
                required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <!-- Email -->
        <div class="max-w-xl">
            <x-input-label for="email" :value="__('Email Address')" class="font-bold text-[#334155] mb-2" />
            <x-text-input wire:model="email" id="email" name="email" type="email"
                class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]/20"
                required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                <div class="mt-4 p-4 bg-[#FEF3C7] border border-[#F59E0B]/20 rounded-2xl">
                    <p class="text-sm font-bold text-[#92400E] flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                        {{ __('Your email address is unverified.') }}
                    </p>
                    <button wire:click.prevent="sendVerification"
                        class="mt-2 text-sm text-[#F59E0B] font-black underline hover:text-[#B45309]">
                        {{ __('Re-send verification email') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-3 text-xs font-bold text-[#16A34A] bg-[#DCFCE7] px-3 py-1 rounded-lg inline-block">
                            {{ __('A new verification link has been sent.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center gap-4 pt-4 border-t border-[#F8FAFC]">
            <button type="submit"
                class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all">
                {{ __('Save Changes') }}
            </button>

            <x-action-message class="text-[#16A34A] font-bold text-sm flex items-center gap-1" on="profile-updated">
                <i data-lucide="check" class="w-4 h-4"></i> {{ __('Saved successfully.') }}
            </x-action-message>
        </div>
    </form>
</section>
