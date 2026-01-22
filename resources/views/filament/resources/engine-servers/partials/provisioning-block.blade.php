<div class="space-y-4">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1 text-sm text-gray-600">
            <div class="font-semibold text-gray-900">Generate install bundle</div>
            <div>Creates a short-lived bundle with a fresh worker token and install script.</div>
        </div>
        <x-filament::button wire:click="generateBundle" wire:loading.attr="disabled">
            Generate bundle
        </x-filament::button>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
        @if ($bundle)
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-gray-900">Bundle status:</span>
                @if ($bundle->isExpired())
                    <x-filament::badge color="danger">Expired</x-filament::badge>
                @else
                    <x-filament::badge color="success">Active</x-filament::badge>
                @endif
                <span class="text-gray-500">Expires {{ $bundle->expires_at?->diffForHumans() ?? 'soon' }}</span>
            </div>
        @else
            <div class="text-gray-600">No provisioning bundle generated yet.</div>
        @endif
    </div>

    <div class="space-y-3 text-sm text-gray-600">
        <div class="grid gap-3 md:grid-cols-2">
            <label class="space-y-1">
                <span class="text-xs uppercase tracking-wide text-gray-400">GHCR Username</span>
                <input
                    type="text"
                    wire:model="ghcrUsername"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900"
                    placeholder="your-gh-username"
                    autocomplete="off"
                />
            </label>
            <label class="space-y-1">
                <span class="text-xs uppercase tracking-wide text-gray-400">GHCR Token</span>
                <input
                    type="password"
                    wire:model="ghcrToken"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900"
                    placeholder="ghcr token"
                    autocomplete="off"
                />
            </label>
        </div>

        @if ($installCommand)
            <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $installCommand }}</pre>
        @else
            <div class="rounded-md border border-dashed border-gray-300 p-3 text-sm text-gray-500">
                Generate a bundle and fill GHCR credentials to see the install command.
            </div>
        @endif
    </div>

    <div class="grid gap-3 text-sm text-gray-600 md:grid-cols-2">
        <div>
            <div class="font-semibold text-gray-900">Install script</div>
            @if (!empty($downloadUrls['install']))
                <a class="text-primary-600 underline" href="{{ $downloadUrls['install'] }}">Download install.sh</a>
            @else
                <div class="text-gray-500">Generate a bundle to enable download.</div>
            @endif
        </div>
        <div>
            <div class="font-semibold text-gray-900">worker.env</div>
            @if (!empty($downloadUrls['env']))
                <a class="text-primary-600 underline" href="{{ $downloadUrls['env'] }}">Download worker.env</a>
            @else
                <div class="text-gray-500">Generate a bundle to enable download.</div>
            @endif
        </div>
    </div>

    <details class="rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-600">
        <summary class="cursor-pointer font-semibold text-gray-900">Preview worker.env</summary>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $workerEnv }}</pre>
    </details>
</div>
