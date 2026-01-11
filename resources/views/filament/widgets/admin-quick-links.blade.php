<x-filament-widgets::widget>
    <x-filament::section heading="System settings">
        <div style="display:grid;gap:0.75rem;font-size:0.875rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="color:#64748b;">Storage disk</span>
                <span style="font-weight:600;color:#0f172a;">{{ $storageDisk }}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="color:#64748b;">Retention days</span>
                <span style="font-weight:600;color:#0f172a;">{{ $retentionDays }}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="color:#64748b;">Heartbeat threshold</span>
                <span style="font-weight:600;color:#0f172a;">{{ $heartbeatMinutes }} min</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
