<x-filament-widgets::widget>
    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section
            heading="Quick actions"
            description="Jump to common admin workflows."
            class="lg:col-span-2"
        >
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($links as $link)
                    <x-filament::button
                        tag="a"
                        :href="$link['url']"
                        color="primary"
                        outlined
                        class="w-full justify-between"
                    >
                        {{ $link['label'] }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section heading="System settings">
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Storage disk</span>
                    <span class="font-medium text-gray-900">{{ $storageDisk }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Retention days</span>
                    <span class="font-medium text-gray-900">{{ $retentionDays }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Heartbeat threshold</span>
                    <span class="font-medium text-gray-900">{{ $heartbeatMinutes }} min</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
