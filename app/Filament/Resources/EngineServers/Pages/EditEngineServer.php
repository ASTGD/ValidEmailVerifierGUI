<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Filament\Resources\EngineServers\Pages\Concerns\HandlesProvisioningBundle;
use App\Services\EngineServerReputationService;
use App\Support\AdminAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEngineServer extends EditRecord
{
    use HandlesProvisioningBundle;

    protected static string $resource = EngineServerResource::class;

    public function getSubheading(): ?string
    {
        return 'Emergency fallback UI only. Use Go Verifier Engine Room for daily operations.';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        Notification::make()
            ->title('Emergency fallback UI')
            ->body('Use this page only when Go Verifier Engine Room is unavailable.')
            ->warning()
            ->send();

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
        AdminAuditLogger::log('engine_server_updated', $this->record, [
            'source' => 'filament_fallback_ui',
            'triggered_by' => 'filament-admin',
        ]);
    }
}
