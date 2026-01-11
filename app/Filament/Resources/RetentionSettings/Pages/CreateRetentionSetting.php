<?php

namespace App\Filament\Resources\RetentionSettings\Pages;

use App\Filament\Resources\RetentionSettings\RetentionSettingResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateRetentionSetting extends CreateRecord
{
    protected static string $resource = RetentionSettingResource::class;

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('retention_created', $this->record, [
            'retention_days' => $this->record->retention_days,
        ]);
    }
}
