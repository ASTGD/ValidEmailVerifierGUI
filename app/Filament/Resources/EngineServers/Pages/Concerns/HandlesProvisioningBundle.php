<?php

namespace App\Filament\Resources\EngineServers\Pages\Concerns;

use App\Models\EngineServer;
use App\Models\EngineServerProvisioningBundle;
use App\Services\EngineWorkerProvisioningService;
use App\Support\AdminAuditLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

trait HandlesProvisioningBundle
{
    public ?int $bundleId = null;

    public string $ghcrUsername = '';

    public string $ghcrToken = '';

    protected function loadProvisioningBundle(): void
    {
        $latestBundle = EngineServerProvisioningBundle::query()
            ->where('engine_server_id', $this->getRecord()->id)
            ->latest()
            ->first();

        $this->bundleId = $latestBundle?->id;
    }

    public function generateBundle(): void
    {
        try {
            $bundle = app(EngineWorkerProvisioningService::class)->createBundle($this->getRecord(), auth()->user());
            $this->bundleId = $bundle->id;

            AdminAuditLogger::log('engine_worker_bundle_generated', $this->getRecord(), [
                'bundle_id' => $bundle->id,
                'expires_at' => $bundle->expires_at?->toDateTimeString(),
            ]);

            Notification::make()
                ->title('Provisioning bundle generated.')
                ->success()
                ->send();
        } catch (\RuntimeException $exception) {
            Notification::make()
                ->title('Provisioning bundle failed.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function provisioningViewData(): array
    {
        $record = $this->getRecord();
        $identityDomain = $record->verifierDomain?->domain ?? $record->identity_domain;
        $apiBaseUrl = $this->resolveApiBaseUrl();

        $bundle = $this->bundleId
            ? EngineServerProvisioningBundle::query()->find($this->bundleId)
            : null;

        return [
            'bundle' => $bundle,
            'downloadUrls' => $this->buildDownloadUrls($bundle),
            'installCommand' => $this->buildInstallCommand($bundle),
            'workerEnv' => $this->resolveWorkerEnv($record, $apiBaseUrl, $identityDomain, $bundle),
            'missingConfig' => $this->missingProvisioningConfig(),
        ];
    }

    protected function resolveApiBaseUrl(): string
    {
        $apiBaseUrl = trim((string) config('app.url'), '/');

        return $apiBaseUrl !== '' ? $apiBaseUrl : '<app-url>';
    }

    protected function buildWorkerEnv(
        EngineServer $record,
        string $apiBaseUrl,
        ?string $identityDomain,
        string $token = ''
    ): string {
        $lines = [
            $this->envLine('ENGINE_API_BASE_URL', $apiBaseUrl),
            $this->envLine('ENGINE_API_TOKEN', $token),
            $this->envLine('ENGINE_SERVER_IP', $record->ip_address),
            $this->envLine('ENGINE_SERVER_NAME', $record->name),
            $this->envLine('WORKER_ID', (string) $record->id),
        ];

        if ($record->environment) {
            $lines[] = $this->envLine('ENGINE_SERVER_ENV', $record->environment);
        }

        if ($record->region) {
            $lines[] = $this->envLine('ENGINE_SERVER_REGION', $record->region);
        }

        if ($record->helo_name) {
            $lines[] = $this->envLine('HELO_NAME', $record->helo_name);
        }

        if ($record->mail_from_address) {
            $lines[] = $this->envLine('MAIL_FROM_ADDRESS', $record->mail_from_address);
        }

        if ($identityDomain) {
            $lines[] = $this->envLine('IDENTITY_DOMAIN', $identityDomain);
        }

        return implode("\n", $lines) . "\n";
    }

    protected function resolveWorkerEnv(
        EngineServer $record,
        string $apiBaseUrl,
        ?string $identityDomain,
        ?EngineServerProvisioningBundle $bundle
    ): string {
        if (! $bundle || $bundle->isExpired()) {
            return $this->buildWorkerEnv($record, $apiBaseUrl, $identityDomain);
        }

        $disk = (string) config('engine.worker_provisioning_disk', 'local');

        if (! Storage::disk($disk)->exists($bundle->env_key)) {
            return $this->buildWorkerEnv($record, $apiBaseUrl, $identityDomain);
        }

        return (string) Storage::disk($disk)->get($bundle->env_key);
    }

    /**
     * @return array<string, string>
     */
    protected function buildDownloadUrls(?EngineServerProvisioningBundle $bundle): array
    {
        if (! $bundle || $bundle->isExpired()) {
            return [];
        }

        $expiresAt = $bundle->expires_at ?? now();

        return [
            'install' => URL::temporarySignedRoute('provisioning-bundles.download', $expiresAt, [
                'bundle' => $bundle->bundle_uuid,
                'file' => 'install.sh',
            ]),
            'env' => URL::temporarySignedRoute('provisioning-bundles.download', $expiresAt, [
                'bundle' => $bundle->bundle_uuid,
                'file' => 'worker.env',
            ]),
        ];
    }

    protected function buildInstallCommand(?EngineServerProvisioningBundle $bundle): ?string
    {
        if (! $bundle || $bundle->isExpired()) {
            return null;
        }

        if ($this->ghcrUsername === '' || $this->ghcrToken === '') {
            return null;
        }

        $urls = $this->buildDownloadUrls($bundle);
        $installUrl = $urls['install'] ?? null;

        if (! $installUrl) {
            return null;
        }

        return sprintf(
            'curl -fsSL "%s" | bash -s -- --ghcr-user "%s" --ghcr-token "%s"',
            $installUrl,
            $this->ghcrUsername,
            $this->ghcrToken
        );
    }

    /**
     * @return array<string, bool>
     */
    protected function missingProvisioningConfig(): array
    {
        return [
            'worker_registry' => (string) config('engine.worker_registry', '') === '',
            'worker_image' => (string) config('engine.worker_image', '') === '',
            'worker_env_path' => (string) config('engine.worker_env_path', '') === '',
        ];
    }

    protected function envLine(string $key, string $value): string
    {
        return $key . '=' . $value;
    }
}
