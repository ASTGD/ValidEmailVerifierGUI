<?php

namespace App\Filament\Resources\RetentionSettings\Pages;

use App\Filament\Resources\RetentionSettings\RetentionSettingResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditRetentionSetting extends EditRecord
{
    protected static string $resource = RetentionSettingResource::class;

    protected function afterSave(): void
    {
        AdminAuditLogger::log('retention_updated', $this->record, [
            'retention_days' => $this->record->retention_days,
        ]);
    }
}
