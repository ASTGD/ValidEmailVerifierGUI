<x-filament-widgets::widget>
    <div style="display:grid;gap:1rem;">
        <x-filament::section heading="Cache Hit Rate">
            <div style="display:grid;gap:0.75rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#64748b;">Last 7 days</span>
                    <span style="font-weight:600;color:#0f172a;">
                        {{ $cache['seven']['rate'] }}% ({{ $cache['seven']['cached'] }} / {{ $cache['seven']['total'] }})
                    </span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#64748b;">Last 30 days</span>
                    <span style="font-weight:600;color:#0f172a;">
                        {{ $cache['thirty']['rate'] }}% ({{ $cache['thirty']['cached'] }} / {{ $cache['thirty']['total'] }})
                    </span>
                </div>
                <a href="{{ $cache['jobs_url'] }}" style="font-size:0.75rem;color:#2563eb;">View jobs</a>
            </div>
        </x-filament::section>

        <x-filament::section heading="Top Reason Codes (30d)">
            <div style="display:grid;gap:0.5rem;">
                @forelse ($topReasons as $reason)
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="color:#0f172a;font-weight:500;">{{ $reason->reason_code }}</span>
                        <span style="color:#64748b;">{{ $reason->total }}</span>
                    </div>
                @empty
                    <div style="color:#64748b;">No recent outcomes.</div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section heading="Feedback Ingestion (30d)">
            <div style="display:grid;gap:0.75rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#64748b;">Total ingestions</span>
                    <span style="font-weight:600;color:#0f172a;">{{ $ingestion['summary']->ingestions ?? 0 }}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#64748b;">Items received</span>
                    <span style="font-weight:600;color:#0f172a;">{{ $ingestion['summary']->items ?? 0 }}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#64748b;">Items imported</span>
                    <span style="font-weight:600;color:#0f172a;">{{ $ingestion['summary']->imported ?? 0 }}</span>
                </div>
                <div style="display:grid;gap:0.5rem;margin-top:0.25rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="color:#64748b;">API ingestions</span>
                        <span style="color:#0f172a;">
                            {{ $ingestion['byType']['api']->count ?? 0 }} ({{ $ingestion['byType']['api']->items ?? 0 }})
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="color:#64748b;">Import ingestions</span>
                        <span style="color:#0f172a;">
                            {{ $ingestion['byType']['import']->count ?? 0 }} ({{ $ingestion['byType']['import']->items ?? 0 }})
                        </span>
                    </div>
                </div>
                <a href="{{ $ingestion['imports_url'] }}" style="font-size:0.75rem;color:#2563eb;">View imports</a>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
