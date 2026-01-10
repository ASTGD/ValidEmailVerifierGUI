@props(['type' => 'info', 'message' => null])

@php
    $classes = match ($type) {
        'success' => 'border-green-200 bg-green-50 text-green-700',
        'error' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-700',
        default => 'border-gray-200 bg-gray-50 text-gray-700',
    };
@endphp

@if($message)
    <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}">
        {{ $message }}
    </div>
@endif
