<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Models\EngineServer;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ProvisionEngineServer extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EngineServerResource::class;

    protected string $view = 'filament.resources.engine-servers.pages.provision-engine-server';

    protected static ?string $title = 'Worker Provisioning';

    protected static ?string $breadcrumb = 'Provisioning';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
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

        return [
            'record' => $record,
            'identityDomain' => $identityDomain,
            'apiBaseUrl' => $apiBaseUrl,
            'workerEnv' => $this->buildWorkerEnv($record, $apiBaseUrl, $identityDomain),
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

    private function resolveApiBaseUrl(): string
    {
        $apiBaseUrl = trim((string) config('app.url'), '/');

        return $apiBaseUrl !== '' ? $apiBaseUrl : '<app-url>';
    }

    private function buildWorkerEnv(EngineServer $record, string $apiBaseUrl, ?string $identityDomain): string
    {
        $lines = [
            $this->envLine('ENGINE_API_BASE_URL', $apiBaseUrl),
            $this->envLine('ENGINE_API_TOKEN', ''),
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
