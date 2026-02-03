@php
    $config = (array) config('engine.cache_dynamodb', []);
@endphp

<div class="fi-fo-field-content-col" style="display: grid; row-gap: 0.5rem;">
    <div class="fi-fo-field-content-ctn" style="display: grid; row-gap: 0.35rem;">
        <span class="fi-fo-field-label-content"><strong>Table</strong>: {{ $config['table'] ?? '—' }}</span>
        <span class="fi-fo-field-label-content"><strong>Region</strong>: {{ $config['region'] ?? '—' }}</span>
        <span class="fi-fo-field-label-content"><strong>Partition key</strong>: {{ $config['key_attribute'] ?? '—' }}</span>
        <span class="fi-fo-field-label-content"><strong>Result attribute</strong>: {{ $config['result_attribute'] ?? '—' }}</span>
        <span class="fi-fo-field-label-content"><strong>DateTime attribute</strong>: {{ $config['datetime_attribute'] ?? '—' }}</span>
    </div>
</div>
