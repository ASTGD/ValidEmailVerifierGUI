@php
    $healthCheck = $healthCheck ?? null;
    $healthCheckEmails = $healthCheckEmails ?? '';
@endphp

<div class="fi-fo-field-content-col" style="display: grid; row-gap: 1.25rem;">
    <div class="fi-prose">
        <p>
            Run a quick connectivity test against the configured cache server without
            reading or writing any email data.
        </p>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-content-col">
            <x-filament::button
                type="button"
                wire:click="runCacheHealthCheck"
                wire:loading.attr="disabled"
            >
                Run health check
            </x-filament::button>
        </div>
    </div>

    <div class="fi-fo-field" style="display: grid; row-gap: 0.75rem;">
        <div class="fi-fo-field-label-col">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">Read test emails (optional)</span>
            </label>
        </div>
        <div class="fi-fo-field-content-col">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live="cacheHealthCheckEmails"
                    autocomplete="off"
                    placeholder="email1@example.com, email2@example.com"
                />
            </x-filament::input.wrapper>
            <span class="fi-fo-field-label-content" style="font-size: 0.75rem; opacity: 0.7;">
                Enter up to 25 emails (comma or new line). Used to confirm read access.
            </span>
        </div>
    </div>

    @if ($healthCheck)
        <div class="fi-fo-field-content-ctn" style="display: grid; row-gap: 0.35rem;">
            @if ($healthCheck['ok'] ?? false)
                <x-filament::badge color="success">Healthy</x-filament::badge>
            @else
                <x-filament::badge color="danger">Unhealthy</x-filament::badge>
            @endif
            <span class="fi-fo-field-label-content">{{ $healthCheck['message'] ?? '' }}</span>
            @if (! empty($healthCheck['read_test']))
                <span class="fi-fo-field-label-content" style="font-size: 0.75rem; opacity: 0.7;">
                    Read test: {{ $healthCheck['read_test']['found'] ?? 0 }} of {{ $healthCheck['read_test']['attempted'] ?? 0 }} found.
                </span>
            @endif
            @if (! empty($healthCheck['checked_at']))
                <span class="fi-fo-field-label-content" style="font-size: 0.75rem; opacity: 0.7;">
                    Checked at {{ $healthCheck['checked_at'] }}
                </span>
            @endif
        </div>
    @else
        <span class="fi-fo-field-label-content" style="opacity: 0.7;">
            No health check run yet.
        </span>
    @endif
</div>
