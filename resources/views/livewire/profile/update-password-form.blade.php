<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state([
    'current_password' => '',
    'password' => '',
    'password_confirmation' => '',
]);

rules([
    'current_password' => ['required', 'string', 'current_password'],
    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
]);

$updatePassword = function () {
    try {
        $validated = $this->validate();
    } catch (ValidationException $e) {
        $this->reset('current_password', 'password', 'password_confirmation');

        throw $e;
    }

    Auth::user()->update([
        'password' => Hash::make($validated['password']),
    ]);

    $this->reset('current_password', 'password', 'password_confirmation');

    $this->dispatch('password-updated');
};

?>

<section class="bg-white p-8 md:p-10 rounded-[2.5rem] border border-[#E2E8F0] shadow-sm">
    <header class="mb-8">
        <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">
            {{ __('Update Password') }}
        </h2>
        <p class="mt-2 text-[#64748B] font-medium">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="space-y-6">
        <div class="max-w-xl">
            <x-input-label for="update_password_current_password" :value="__('Current Password')" class="font-bold text-[#334155] mb-2" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]/20" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="max-w-xl">
            <x-input-label for="update_password_password" :value="__('New Password')" class="font-bold text-[#334155] mb-2" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]/20" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="max-w-xl">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm New Password')" class="font-bold text-[#334155] mb-2" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full px-4 py-3 rounded-xl border-[#E2E8F0] focus:border-[#1E7CCF] focus:ring-[#1E7CCF]/20" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-4 border-t border-[#F8FAFC]">
            <button type="submit" class="bg-[#1E7CCF] hover:bg-[#1866AD] text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all">
                {{ __('Update Password') }}
            </button>

            <x-action-message class="text-[#16A34A] font-bold text-sm flex items-center gap-1" on="password-updated">
                <i data-lucide="check" class="w-4 h-4"></i> {{ __('Password updated.') }}
            </x-action-message>
        </div>
    </form>
</section>
