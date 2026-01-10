<?php

namespace App\Filament\Resources\EngineServers\Pages;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateEngineServer extends CreateRecord
{
    protected static string $resource = EngineServerResource::class;

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('engine_server_created', $this->record);
    }
}
