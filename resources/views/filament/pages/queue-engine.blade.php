<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Queue Engine">
            <p class="text-sm text-gray-500">
                Live queue monitoring powered by Horizon. Ensure Horizon is running on the server.
            </p>

            <div class="mt-4 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <iframe
                    src="{{ $this->horizonUrl() }}"
                    class="h-[calc(100vh-16rem)] w-full rounded-xl"
                    title="Queue Engine (Horizon)"
                ></iframe>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
