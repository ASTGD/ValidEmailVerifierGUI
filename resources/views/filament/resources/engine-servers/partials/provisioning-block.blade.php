@php
    $hasCredentials = trim($ghcrUsername) !== '' && trim($ghcrToken) !== '';
@endphp

<div class="space-y-5">
    <div class="space-y-1 text-sm text-gray-600">
        <div class="font-semibold text-gray-900">Provisioning bundle</div>
        <div>Generate a short-lived bundle with a fresh worker token and install script.</div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-gray-400">GHCR Username</span>
            <input
                type="text"
                wire:model.live="ghcrUsername"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900"
                placeholder="your-gh-username"
                autocomplete="off"
            />
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-gray-400">GHCR Token</span>
            <input
                type="password"
                wire:model.live="ghcrToken"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900"
                placeholder="ghcr token"
                autocomplete="off"
            />
        </label>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="text-xs uppercase tracking-wide text-gray-400">Bundle status</div>
            <x-filament::button wire:click="generateBundle" wire:loading.attr="disabled" :disabled="! $hasCredentials">
                Generate bundle
            </x-filament::button>
        </div>

        @if ($bundle)
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @if ($bundle->isExpired())
                    <x-filament::badge color="danger">Expired</x-filament::badge>
                @else
                    <x-filament::badge color="success">Active</x-filament::badge>
                @endif
                <span class="text-gray-500">Expires {{ $bundle->expires_at?->diffForHumans() ?? 'soon' }}</span>
            </div>
        @else
            <div class="text-gray-500">No bundle generated yet.</div>
        @endif

        @if (! $hasCredentials)
            <div class="text-xs text-gray-500">Enter GHCR credentials to enable bundle generation.</div>
        @endif
    </div>

    <div class="space-y-2 text-sm text-gray-600">
        <div class="font-semibold text-gray-900">Install command (run as root)</div>
        @if ($installCommand)
            <pre class="overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $installCommand }}</pre>
        @elseif ($bundle)
            <div class="rounded-md border border-dashed border-gray-300 p-3 text-sm text-gray-500">
                Enter GHCR credentials to build the one-line installer.
            </div>
        @else
            <div class="rounded-md border border-dashed border-gray-300 p-3 text-sm text-gray-500">
                Generate a bundle to get the one-line installer.
            </div>
        @endif
    </div>

    <details class="rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-600">
        <summary class="cursor-pointer font-semibold text-gray-900">Preview worker.env</summary>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $workerEnv }}</pre>
    </details>
</div>
