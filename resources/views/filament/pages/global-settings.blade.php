<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-filament::section heading="Global Settings" class="xl:col-span-2">
            <p class="text-sm text-gray-500">
                Manage shared UI and system-wide defaults here. Use this page for non-engine, non-billing settings.
            </p>

            <div class="mt-4">
                {{ $this->form }}
            </div>

            <div class="mt-6">
                <x-filament::button wire:click="save">
                    Save settings
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Notes" class="xl:col-span-1">
            <div class="space-y-4 text-sm text-gray-500">
                <p>
                    Developer Tools are intended for internal diagnostics only. Keep them disabled in production unless
                    explicitly required.
                </p>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">Current status</div>
                    <div class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ data_get($this->formData, 'devtools_enabled') ? 'Enabled' : 'Disabled' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">Allowed environments</div>
                    <div class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ data_get($this->formData, 'devtools_environments') ?: 'local,staging' }}
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
