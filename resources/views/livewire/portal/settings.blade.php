<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Settings') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Manage your profile and notification preferences.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 p-4">
            <p class="text-sm text-gray-500">{{ __('Profile') }}</p>
            <p class="mt-2 text-sm text-gray-700">{{ __('Profile settings placeholder. Link to profile page when ready.') }}</p>
            <a href="{{ route('profile') }}" class="mt-2 inline-flex text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
                {{ __('Go to Profile') }}
            </a>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <p class="text-sm text-gray-500">{{ __('Notifications') }}</p>
            <p class="mt-2 text-sm text-gray-700">{{ __('Notification preferences placeholder.') }}</p>
        </div>
    </div>
</x-portal-layout>
