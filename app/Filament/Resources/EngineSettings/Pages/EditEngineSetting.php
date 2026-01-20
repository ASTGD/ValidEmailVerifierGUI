<?php

namespace App\Filament\Resources\EngineSettings\Pages;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Filament\Resources\EngineSettings\Pages\Concerns\HandlesPolicySettings;
use Filament\Resources\Pages\EditRecord;

class EditEngineSetting extends EditRecord
{
    use HandlesPolicySettings;

    protected static string $resource = EngineSettingResource::class;

    public function getTitle(): string
    {
        return 'Settings';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillPolicyFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturePolicyData($data);
    }

    protected function afterSave(): void
    {
        $this->persistPolicyData();
    }
}
