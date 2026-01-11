<?php

namespace App\Filament\Resources\AdminAuditLogs\Pages;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAdminAuditLogs extends ListRecords
{
    protected static string $resource = AdminAuditLogResource::class;

    /**
     * @return array<class-string<Action>>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
