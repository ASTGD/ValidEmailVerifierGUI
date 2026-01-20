<x-filament-panels::page>
    <form wire:submit="saveSettings" class="space-y-6">
        <!-- Render the form logic defined in the PHP file -->
        {{ $this->form }}

        <!-- Manual Save Button since the component was missing -->
        <div class="flex justify-start">
            <button type="submit" class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-primary fi-btn-color-primary bg-custom-600 text-white shadow-sm hover:bg-custom-500 focus-visible:ring-custom-500/50 py-2 px-6" style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600); background-color: #1E7CCF;">
                <span wire:loading.remove wire:target="saveSettings">
                    Save All Settings
                </span>
                <span wire:loading wire:target="saveSettings">
                    Saving...
                </span>
            </button>
        </div>
    </form>
</x-filament-panels::page>
