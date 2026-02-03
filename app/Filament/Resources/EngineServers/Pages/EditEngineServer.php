<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Filament\Resources\EngineServers\Pages\Concerns\HandlesProvisioningBundle;
use App\Support\AdminAuditLogger;
use App\Services\EngineServerReputationService;
use Filament\Resources\Pages\EditRecord;

class EditEngineServer extends EditRecord
{
    use HandlesProvisioningBundle;

    protected static string $resource = EngineServerResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->loadProvisioningBundle();
    }

    public function reputationViewData(): array
    {
        /** @var EngineServerReputationService $service */
        $service = app(EngineServerReputationService::class);

        return [
            'summary' => $service->summaryFor($this->record),
        ];
    }

    protected function afterSave(): void
    {
        AdminAuditLogger::log('engine_server_updated', $this->record);
    }
}
