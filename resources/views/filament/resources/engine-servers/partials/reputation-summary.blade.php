@php
    $summary = $summary ?? [
        'status' => 'warming',
        'status_color' => 'warning',
        'tempfail_rate' => 0.0,
        'tempfail_count' => 0,
        'total_count' => 0,
        'window_hours' => 24,
        'min_samples' => 100,
    ];
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$summary['status_color']">
            {{ ucfirst($summary['status']) }}
        </x-filament::badge>
        <span class="text-sm text-gray-500">
            Window: last {{ $summary['window_hours'] }}h
        </span>
    </div>
    <div class="text-sm text-gray-600">
        Tempfail rate: {{ number_format($summary['tempfail_rate'] * 100, 1) }}%
        ({{ $summary['tempfail_count'] }} / {{ $summary['total_count'] }})
    </div>
    <div class="text-xs text-gray-500">
        Status requires at least {{ $summary['min_samples'] }} samples.
    </div>
</div>
