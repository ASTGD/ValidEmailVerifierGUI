<x-filament::section heading="Queue Engine Links">
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button tag="a" href="{{ $settingsUrl }}">
            Queue Engine Settings
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ $horizonUrl }}"
            target="_blank"
            rel="noopener"
            color="gray"
            :disabled="! $horizonEnabled"
        >
            Horizon Dashboard
        </x-filament::button>

        @if (! $horizonEnabled)
            <span class="text-xs text-gray-500">Enable Horizon in Settings to access the dashboard.</span>
        @endif
    </div>
</x-filament::section>
