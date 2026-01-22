<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Filament\Resources\EngineServers\Pages\Concerns\HandlesProvisioningBundle;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ProvisionEngineServer extends Page
{
    use HandlesProvisioningBundle;
    use InteractsWithRecord;

    protected static string $resource = EngineServerResource::class;

    protected string $view = 'filament.resources.engine-servers.pages.provision-engine-server';

    protected static ?string $title = 'Worker Provisioning';

    protected static ?string $breadcrumb = 'Provisioning';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->loadProvisioningBundle();
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

        return array_merge($this->provisioningViewData(), [
            'record' => $record,
            'identityDomain' => $identityDomain,
            'apiBaseUrl' => $apiBaseUrl,
            'commands' => [
                'login' => 'docker login ' . $registryRef,
                'pull' => 'docker pull ' . $imageRef,
                'run' => $this->buildRunCommand($imageRef, $envPathRef),
                'restart' => 'docker restart valid-email-worker',
                'logs' => 'docker logs -f valid-email-worker',
            ],
        ]);
    }

    private function buildRunCommand(string $imageRef, string $envPath): string
    {
        return implode(" \\\n", [
            'docker run -d --name valid-email-worker --restart unless-stopped',
            '--env-file ' . $envPath,
            $imageRef,
        ]);
    }
}
