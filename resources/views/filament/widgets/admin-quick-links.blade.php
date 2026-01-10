<x-filament-widgets::widget>
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
</x-filament-widgets::widget>
