@php
    $value = (int) ($getState() ?? 0);
    $value = max(0, min(100, $value));
@endphp

<div class="w-full">
    <div class="text-xs text-gray-500 mb-1">{{ $value }}%</div>
    <div class="h-2 w-full rounded bg-gray-200">
        <div class="h-2 rounded bg-primary-500" style="width: {{ $value }}%"></div>
    </div>
</div>
