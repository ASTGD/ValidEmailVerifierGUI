<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Upload Email List') }}
            </h2>
            <a href="{{ route('portal.jobs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500" wire:navigate>
                {{ __('View Jobs') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form wire:submit.prevent="save" class="space-y-6" enctype="multipart/form-data">
                        <div>
                            <x-input-label for="file" :value="__('CSV or TXT file')" />
                            <input
                                id="file"
                                type="file"
                                wire:model="file"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
                            />
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('Accepted formats: CSV or TXT. Max 10MB.') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button wire:loading.attr="disabled">
                                {{ __('Upload') }}
                            </x-primary-button>
                            <a href="{{ route('portal.jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-800" wire:navigate>
                                {{ __('Browse existing jobs') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
