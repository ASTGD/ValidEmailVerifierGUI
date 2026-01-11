<x-filament-widgets::widget>
    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
        @foreach ($cards as $card)
            <div class="admin-highlight-card" style="border-radius:16px;border:1px solid var(--admin-highlight-border);background:var(--admin-highlight-bg);color:var(--admin-highlight-text);box-shadow:var(--admin-highlight-shadow);">
                <div style="padding:1.25rem;">
                    <div style="font-size:0.875rem;color:var(--admin-highlight-muted);">{{ $card['title'] }}</div>
                    <div style="margin-top:0.5rem;font-size:1.5rem;font-weight:600;">{{ $card['value'] }}</div>
                    <div style="margin-top:0.75rem;display:flex;align-items:center;gap:0.5rem;font-size:0.75rem;font-weight:600;color:{{ str_contains($card['delta_class'], 'emerald') ? '#34d399' : '#fb7185' }};">
                        <span>{{ $card['delta'] }}</span>
                        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.25-7.25v4.47a.75.75 0 001.5 0V5.25a.75.75 0 00-.75-.75H7.5a.75.75 0 000 1.5h4.47l-7.25 7.25a.75.75 0 000 1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <div style="height:64px;padding:0 1rem 1rem;">
                    <svg width="100%" height="60" viewBox="0 0 270 60" preserveAspectRatio="none" style="display:block;">
                        <polyline
                            fill="none"
                            stroke-width="2"
                            stroke="{{ str_contains($card['line_class'], 'emerald') ? '#34d399' : '#fb7185' }}"
                            points="{{ $card['points'] }}"
                        />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>
    <p style="margin-top:0.75rem;font-size:0.75rem;color:var(--admin-highlight-muted);">Sample data for dashboard layout preview.</p>
</x-filament-widgets::widget>
