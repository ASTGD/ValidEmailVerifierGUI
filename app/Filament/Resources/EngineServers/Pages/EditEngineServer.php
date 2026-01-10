<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditEngineServer extends EditRecord
{
    protected static string $resource = EngineServerResource::class;

    protected function afterSave(): void
    {
        AdminAuditLogger::log('engine_server_updated', $this->record);
    }
}
