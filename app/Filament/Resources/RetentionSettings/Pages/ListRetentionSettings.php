<?php

namespace App\Filament\Resources\RetentionSettings\Pages;

use App\Filament\Resources\RetentionSettings\RetentionSettingResource;
use App\Models\RetentionSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRetentionSettings extends ListRecords
{
    protected static string $resource = RetentionSettingResource::class;

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        if (RetentionSetting::query()->exists()) {
            return [];
        }

        return [
            CreateAction::make(),
        ];
    }
}
