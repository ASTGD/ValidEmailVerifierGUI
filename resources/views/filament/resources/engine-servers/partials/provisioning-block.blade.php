@php
    $bundle = $bundle ?? null;
    $ghcrUsername = $ghcrUsername ?? '';
    $ghcrToken = $ghcrToken ?? '';
    $installCommand = $installCommand ?? null;
    $workerEnv = $workerEnv ?? '';
    $hasCredentials = trim($ghcrUsername) !== '' && trim($ghcrToken) !== '';
@endphp

<div class="fi-fo-field-content-col" style="row-gap: 1rem;">
    <div class="fi-prose">
        <p>Generate a short-lived bundle with a fresh worker token and install script.</p>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-label-col">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">GHCR Username</span>
            </label>
        </div>
        <div class="fi-fo-field-content-col">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live="ghcrUsername"
                    autocomplete="off"
                    placeholder="your-gh-username"
                />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-label-col">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">GHCR Token</span>
            </label>
        </div>
        <div class="fi-fo-field-content-col">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="password"
                    wire:model.live="ghcrToken"
                    autocomplete="off"
                    placeholder="ghcr token"
                />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-label-col">
            <span class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">Provisioning bundle</span>
            </span>
        </div>
        <div class="fi-fo-field-content-col">
            <div class="fi-fo-field-content-ctn">
                <div class="fi-fo-field-content">
                    @if ($bundle)
                        <div class="fi-fo-field-content-ctn">
                            @if ($bundle->isExpired())
                                <x-filament::badge color="danger">Expired</x-filament::badge>
                            @else
                                <x-filament::badge color="success">Active</x-filament::badge>
                            @endif
                            <span class="fi-fo-field-label-content">
                                Expires {{ $bundle->expires_at?->diffForHumans() ?? 'soon' }}
                            </span>
                        </div>
                    @else
                        <span class="fi-fo-field-label-content">No bundle generated yet.</span>
                    @endif
                </div>
                <x-filament::button
                    wire:click="generateBundle"
                    wire:loading.attr="disabled"
                    :disabled="! $hasCredentials"
                >
                    Generate bundle
                </x-filament::button>
            </div>

            @if (! $hasCredentials)
                <div class="fi-prose">
                    <p>Enter GHCR credentials to enable bundle generation.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-label-col">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">Install command (run as root)</span>
            </label>
        </div>
        <div class="fi-fo-field-content-col">
            @if ($installCommand)
                <div x-data="{ copied: false }" class="fi-fo-field-content-ctn">
                    <div class="fi-fo-field-content">
                        <x-filament::input.wrapper class="fi-fo-textarea">
                            <textarea
                                class="fi-input"
                                rows="2"
                                readonly
                                style="font-family: var(--font-mono);"
                            >{{ $installCommand }}</textarea>
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        x-on:click="navigator.clipboard.writeText(@js($installCommand)); copied = true; setTimeout(() => copied = false, 1500)"
                    >
                        <span x-show="! copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </x-filament::button>
                </div>
            @elseif ($bundle)
                <div class="fi-prose">
                    <p>Enter GHCR credentials to build the one-line installer.</p>
                </div>
            @else
                <div class="fi-prose">
                    <p>Generate a bundle to get the one-line installer.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="fi-fo-field">
        <div class="fi-fo-field-label-col">
            <label class="fi-fo-field-label">
                <span class="fi-fo-field-label-content">Worker env (preview)</span>
            </label>
        </div>
        <div class="fi-fo-field-content-col">
            <details>
                <summary class="fi-fo-field-label-content">Preview worker.env</summary>
                <x-filament::input.wrapper class="fi-fo-textarea" style="margin-top: 0.5rem;">
                    <textarea
                        class="fi-input"
                        rows="8"
                        readonly
                        style="font-family: var(--font-mono);"
                    >{{ $workerEnv }}</textarea>
                </x-filament::input.wrapper>
            </details>
        </div>
    </div>
</div>
