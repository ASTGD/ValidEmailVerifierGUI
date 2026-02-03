@php
    $bundle = $bundle ?? null;
    $ghcrUsername = $ghcrUsername ?? '';
    $ghcrToken = $ghcrToken ?? '';
    $installCommand = $installCommand ?? null;
    $bundleGenerated = $bundleGenerated ?? false;
    $hasCredentials = trim($ghcrUsername) !== '' && trim($ghcrToken) !== '';
@endphp

<div class="fi-fo-field-content-col" style="display: grid; row-gap: 1.75rem;">
    <div class="fi-prose">
        <p>
            Provision a worker for this server with a short-lived bundle. Enter your GHCR
            credentials, click Generate bundle, then run the one-line install command on the VPS.
            Bundles expire automatically.
        </p>
    </div>

    <div class="fi-fo-field" style="display: grid; row-gap: 0.75rem;">
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

    <div class="fi-fo-field" style="display: grid; row-gap: 0.75rem;">
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

    <div class="fi-fo-field" style="display: grid; row-gap: 0.75rem;">
        <div class="fi-fo-field-content-col">
            @if ($bundleGenerated && $bundle)
                <div class="fi-fo-field-content-ctn" style="margin-bottom: 0.75rem;">
                    @if ($bundle->isExpired())
                        <x-filament::badge color="danger">Expired</x-filament::badge>
                    @else
                        <x-filament::badge color="success">Active</x-filament::badge>
                    @endif
                    <span class="fi-fo-field-label-content">
                        Expires {{ $bundle->expires_at?->diffForHumans() ?? 'soon' }}
                    </span>
                </div>
            @endif
            <x-filament::button
                class="w-full"
                wire:click="generateBundle"
                wire:loading.attr="disabled"
                :disabled="! $hasCredentials"
            >
                Generate bundle
            </x-filament::button>
        </div>
    </div>

    @if ($installCommand)
        <div class="fi-fo-field" style="display: grid; row-gap: 0.75rem;">
            <div class="fi-fo-field-label-col">
                <label class="fi-fo-field-label">
                    <span class="fi-fo-field-label-content">Install command (run as root)</span>
                </label>
            </div>
            <div class="fi-fo-field-content-col">
                <div x-data="{ copied: false }" class="fi-fo-field-content-ctn" style="align-items: flex-start;">
                    <div class="fi-fo-field-content">
                        <pre
                            class="fi-fo-field-label-content"
                            style="background: #0b0f1a; color: #e2e8f0; padding: 0.75rem 1rem; border-radius: 0.75rem; white-space: pre-wrap; word-break: break-all; font-family: var(--font-mono); margin: 0;"
                        ><code x-ref="installCommand">{{ $installCommand }}</code></pre>
                    </div>
                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        x-on:click="
                            const text = $refs.installCommand.textContent;
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(() => {
                                    copied = true;
                                    setTimeout(() => copied = false, 1500);
                                }).catch(() => {
                                    const selection = window.getSelection();
                                    const range = document.createRange();
                                    range.selectNodeContents($refs.installCommand);
                                    selection.removeAllRanges();
                                    selection.addRange(range);
                                    document.execCommand('copy');
                                    selection.removeAllRanges();
                                    copied = true;
                                    setTimeout(() => copied = false, 1500);
                                });
                            } else {
                                const selection = window.getSelection();
                                const range = document.createRange();
                                range.selectNodeContents($refs.installCommand);
                                selection.removeAllRanges();
                                selection.addRange(range);
                                document.execCommand('copy');
                                selection.removeAllRanges();
                                copied = true;
                                setTimeout(() => copied = false, 1500);
                            }
                        "
                    >
                        <span x-show="! copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

</div>
