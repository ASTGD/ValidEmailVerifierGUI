<section class="bg-white p-8 md:p-10 rounded-[2.5rem] border-2 border-dashed border-[#FEE2E2]">
    <header class="mb-8">
        <h2 class="text-2xl font-black text-[#DC2626] tracking-tight">
            {{ __('Delete Account') }}
        </h2>
        <p class="mt-2 text-[#64748B] font-medium leading-relaxed">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. This action cannot be undone.') }}
        </p>
    </header>

    <button
        class="bg-[#DC2626] hover:bg-[#B91C1C] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-red-100 transition-all"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Permanently Delete Account') }}</button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-10">
            <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">
                {{ __('Are you sure?') }}
            </h2>

            <p class="mt-4 text-[#64748B] font-medium leading-relaxed">
                {{ __('Please enter your password to confirm you would like to permanently delete your account and all associated data.') }}
            </p>

            <div class="mt-8">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />
                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#DC2626] focus:ring-[#DC2626]/20"
                    placeholder="{{ __('Enter your password to confirm') }}"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2 font-bold" />
            </div>

            <div class="mt-10 flex justify-end gap-4">
                <button type="button" x-on:click="$dispatch('close')" class="px-6 py-3 rounded-xl font-bold text-[#334155] border border-[#E2E8F0] hover:bg-[#F8FAFC]">
                    {{ __('Cancel') }}
                </button>

                <button type="submit" class="bg-[#DC2626] hover:bg-[#B91C1C] text-white px-8 py-3 rounded-xl font-bold">
                    {{ __('Confirm Deletion') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
