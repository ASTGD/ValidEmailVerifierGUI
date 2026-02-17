<x-filament::section heading="Verifier Engine Links">
    <div class="flex flex-wrap items-center gap-3">
        @if ($goWorkersUrl)
            <x-filament::button tag="a" href="{{ $goWorkersUrl }}" target="_blank" rel="noopener">
                Open Go Workers
            </x-filament::button>
        @endif

        @if ($fallbackUrl)
            <x-filament::button tag="a" href="{{ $fallbackUrl }}" color="gray">
                Emergency Fallback UI
            </x-filament::button>
        @endif

        @if (! $goWorkersUrl)
            <span class="text-sm text-gray-500">
                Set `GO_CONTROL_PLANE_BASE_URL` to open Go workers from admin.
            </span>
        @endif
    </div>

    @if ($fallbackUrl)
        <p class="mt-3 text-xs text-amber-600">
            Fallback UI is for break-glass use only when Go control plane is unavailable.
        </p>
    @endif
    @if (! $fallbackUrl)
        <p class="mt-3 text-xs text-gray-500">
            Laravel fallback UI is disabled by policy.
        </p>
    @endif
</x-filament::section>
