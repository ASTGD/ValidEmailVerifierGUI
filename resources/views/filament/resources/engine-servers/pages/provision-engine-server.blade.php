<x-filament-panels::page>
    <div class="space-y-6">
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
                <li>Install Docker on the VPS and ensure registry access.</li>
                <li>Pull the worker image configured in <code class="font-mono">ENGINE_WORKER_IMAGE</code>.</li>
                <li>Copy the <code class="font-mono">worker.env</code> contents to the path configured in <code class="font-mono">ENGINE_WORKER_ENV_PATH</code>.</li>
                <li>Run the container and confirm heartbeat appears in Engine Servers.</li>
            </ol>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-filament::section heading="worker.env">
                <div class="space-y-2 text-sm text-gray-600">
                    <p>Paste this into the configured env file on the worker VPS.</p>
                    @if ($missingConfig['worker_env_path'])
                        <x-filament::badge color="warning">Set ENGINE_WORKER_ENV_PATH in .env</x-filament::badge>
                    @endif
                </div>
                <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-950/90 p-4 text-xs text-gray-100">{{ $workerEnv }}</pre>
            </x-filament::section>

            <x-filament::section heading="Docker commands">
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
