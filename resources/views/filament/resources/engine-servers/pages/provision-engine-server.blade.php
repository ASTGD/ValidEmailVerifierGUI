<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Provisioning bundle">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1 text-sm text-gray-600">
                    <div class="font-semibold text-gray-900">Generate install bundle</div>
                    <div>Creates a short-lived bundle with a fresh worker token and install script.</div>
                </div>
                <x-filament::button wire:click="generateBundle" wire:loading.attr="disabled">
                    Generate bundle
                </x-filament::button>
            </div>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
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
        </x-filament::section>

        <x-filament::section heading="Server overview">
            <div class="grid grid-cols-1 gap-4 text-sm text-gray-600 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Name</div>
                    <div class="font-semibold text-gray-900">{{ $record->name }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">IP Address</div>
                    <div class="font-semibold text-gray-900">{{ $record->ip_address }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Status</div>
                    <div class="font-semibold text-gray-900">{{ $record->isOnline() ? 'Online' : 'Offline' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Host Name</div>
                    <div class="font-semibold text-gray-900">{{ $record->helo_name ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">MAIL FROM</div>
                    <div class="font-semibold text-gray-900">{{ $record->mail_from_address ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Verifier Domain</div>
                    <div class="font-semibold text-gray-900">{{ $identityDomain ?: '-' }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Provisioning steps">
            <ol class="list-decimal space-y-1 pl-4 text-sm text-gray-600">
                <li>Generate the provisioning bundle.</li>
                <li>Enter GHCR credentials to build the one-line installer.</li>
                <li>Run the installer on the VPS as root and confirm heartbeat appears.</li>
            </ol>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-filament::section heading="One-line installer">
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
                        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $installCommand }}</pre>
                    @else
                        <div class="rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                            Generate a bundle and fill GHCR credentials to see the install command.
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section heading="Bundle downloads">
                <div class="space-y-4 text-sm text-gray-600">
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
            </x-filament::section>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-filament::section heading="worker.env">
                <div class="space-y-2 text-sm text-gray-600">
                    <p>Contains the API token. Treat this as sensitive.</p>
                    @if ($missingConfig['worker_env_path'])
                        <x-filament::badge color="warning">Set ENGINE_WORKER_ENV_PATH in .env</x-filament::badge>
                    @endif
                </div>
                <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $workerEnv }}</pre>
            </x-filament::section>

            <x-filament::section heading="Manual Docker commands">
                <div class="space-y-4 text-sm text-gray-600">
                    <div>
                        <div class="font-semibold text-gray-900">Registry login</div>
                        @if ($missingConfig['worker_registry'])
                            <x-filament::badge color="warning" class="mt-2">Set ENGINE_WORKER_REGISTRY in .env</x-filament::badge>
                        @endif
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $commands['login'] }}</pre>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Pull image</div>
                        @if ($missingConfig['worker_image'])
                            <x-filament::badge color="warning" class="mt-2">Set ENGINE_WORKER_IMAGE in .env</x-filament::badge>
                        @endif
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $commands['pull'] }}</pre>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Run container</div>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $commands['run'] }}</pre>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">Operations</div>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $commands['restart'] }}
{{ $commands['logs'] }}</pre>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
