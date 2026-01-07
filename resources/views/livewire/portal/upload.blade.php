<x-portal-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Verify List') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Upload a CSV or TXT file to start a new verification job.') }}</p>
        </div>
    </x-slot>
    <x-slot name="headerAction">
        <a href="{{ route('portal.jobs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
            {{ __('View Jobs') }}
        </a>
    </x-slot>

    <div class="space-y-6">
        @if($errors->any())
            <x-flash type="error" :message="__('Please fix the errors below and try again.')" />
        @endif
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6">
            <form wire:submit.prevent="save" class="space-y-6" enctype="multipart/form-data">
                <div>
                    <x-input-label for="file" :value="__('CSV or TXT file')" />
                    <input
                        id="file"
                        type="file"
                        wire:model="file"
                        class="mt-2 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-white file:text-gray-700 hover:file:bg-gray-100"
                    />
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    <p class="mt-2 text-sm text-gray-500">
                        {{ __('Accepted formats: CSV or TXT. Max 10MB. One email per line.') }}
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ __('Upload list') }}</span>
                        <span wire:loading wire:target="save">{{ __('Uploading...') }}</span>
                    </x-primary-button>
                    <a href="{{ route('portal.jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-800" wire:navigate>
                        {{ __('Browse existing jobs') }}
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
            {{ __('Need a template? Include a header row or paste a single column of emails.') }}
            <a href="{{ route('portal.support') }}" class="text-indigo-600 hover:text-indigo-500" wire:navigate>
                {{ __('Contact support') }}
            </a>
            {{ __('for help formatting your list.') }}
        </div>
    </div>
</x-portal-layout>
