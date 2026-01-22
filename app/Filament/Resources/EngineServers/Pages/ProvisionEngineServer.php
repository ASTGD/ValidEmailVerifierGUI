<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Models\EngineServer;
use App\Models\EngineServerProvisioningBundle;
use App\Services\EngineWorkerProvisioningService;
use App\Support\AdminAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ProvisionEngineServer extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EngineServerResource::class;

    protected string $view = 'filament.resources.engine-servers.pages.provision-engine-server';

    protected static ?string $title = 'Worker Provisioning';

    protected static ?string $breadcrumb = 'Provisioning';

    public ?int $bundleId = null;

    public string $ghcrUsername = '';

    public string $ghcrToken = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $latestBundle = EngineServerProvisioningBundle::query()
            ->where('engine_server_id', $this->record->id)
            ->latest()
            ->first();

        $this->bundleId = $latestBundle?->id;
    }

    protected function getViewData(): array
    {
        $record = $this->getRecord();
        $identityDomain = $record->verifierDomain?->domain ?? $record->identity_domain;
        $apiBaseUrl = $this->resolveApiBaseUrl();
        $workerRegistry = (string) config('engine.worker_registry', '');
        $workerImage = (string) config('engine.worker_image', '');
        $workerEnvPath = (string) config('engine.worker_env_path', '');

        $registryRef = $workerRegistry !== '' ? $workerRegistry : '<registry>';
        $imageRef = $workerImage !== '' ? $workerImage : '<image-ref>';
        $envPathRef = $workerEnvPath !== '' ? $workerEnvPath : '<path-to-worker.env>';

        $bundle = $this->bundleId
            ? EngineServerProvisioningBundle::query()->find($this->bundleId)
            : null;

        $downloadUrls = $this->buildDownloadUrls($bundle);

        return [
            'record' => $record,
            'identityDomain' => $identityDomain,
            'apiBaseUrl' => $apiBaseUrl,
            'bundle' => $bundle,
            'downloadUrls' => $downloadUrls,
            'installCommand' => $this->buildInstallCommand($downloadUrls['install'] ?? null),
            'workerEnv' => $this->resolveWorkerEnv($record, $apiBaseUrl, $identityDomain, $bundle),
            'commands' => [
                'login' => 'docker login ' . $registryRef,
                'pull' => 'docker pull ' . $imageRef,
                'run' => $this->buildRunCommand($imageRef, $envPathRef),
                'restart' => 'docker restart valid-email-worker',
                'logs' => 'docker logs -f valid-email-worker',
            ],
            'missingConfig' => [
                'worker_registry' => $workerRegistry === '',
                'worker_image' => $workerImage === '',
                'worker_env_path' => $workerEnvPath === '',
            ],
        ];
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

    private function resolveApiBaseUrl(): string
    {
        $apiBaseUrl = trim((string) config('app.url'), '/');

        return $apiBaseUrl !== '' ? $apiBaseUrl : '<app-url>';
    }

    private function buildWorkerEnv(
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

    private function resolveWorkerEnv(
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
    private function buildDownloadUrls(?EngineServerProvisioningBundle $bundle): array
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

    private function buildInstallCommand(?string $installUrl): ?string
    {
        if (! $installUrl) {
            return null;
        }

        if ($this->ghcrUsername === '' || $this->ghcrToken === '') {
            return null;
        }

        return sprintf(
            'curl -fsSL "%s" | bash -s -- --ghcr-user "%s" --ghcr-token "%s"',
            $installUrl,
            $this->ghcrUsername,
            $this->ghcrToken
        );
    }

    private function buildRunCommand(string $imageRef, string $envPath): string
    {
        return implode(" \\\n", [
            'docker run -d --name valid-email-worker --restart unless-stopped',
            '--env-file ' . $envPath,
            $imageRef,
        ]);
    }

    private function envLine(string $key, string $value): string
    {
        return $key . '=' . $value;
    }
}
