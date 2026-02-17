<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Support\AdminAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEngineServer extends CreateRecord
{
    protected static string $resource = EngineServerResource::class;

    public function getSubheading(): ?string
    {
        return 'Emergency fallback UI only. Use Go Verifier Engine Room for daily operations.';
    }

    public function mount(): void
    {
        parent::mount();

        Notification::make()
            ->title('Emergency fallback UI')
            ->body('Use this page only when Go Verifier Engine Room is unavailable.')
            ->warning()
            ->send();
    }

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('engine_server_created', $this->record, [
            'source' => 'filament_fallback_ui',
            'triggered_by' => 'filament-admin',
        ]);
    }
}
