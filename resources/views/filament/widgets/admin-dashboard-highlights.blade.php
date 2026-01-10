<x-filament-widgets::widget>
    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($cards as $card)
            <div class="rounded-2xl border border-slate-800/40 bg-slate-900 text-white shadow-sm">
                <div class="p-5">
                    <p class="text-sm text-slate-400">{{ $card['title'] }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $card['value'] }}</p>
                    <div class="mt-3 inline-flex items-center gap-2 text-xs font-medium {{ $card['delta_class'] }}">
                        <span>{{ $card['delta'] }}</span>
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 001.06 0l7.25-7.25v4.47a.75.75 0 001.5 0V5.25a.75.75 0 00-.75-.75H7.5a.75.75 0 000 1.5h4.47l-7.25 7.25a.75.75 0 000 1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <div class="h-20 px-4 pb-4">
                    <svg class="h-full w-full" viewBox="0 0 270 60" preserveAspectRatio="none">
                        <polyline
                            fill="none"
                            stroke-width="2"
                            class="{{ $card['line_class'] }}"
                            points="{{ $card['points'] }}"
                        />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-500">Sample data for dashboard layout preview.</p>
</x-filament-widgets::widget>
