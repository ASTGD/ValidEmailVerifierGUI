<?php

namespace App\Filament\Resources\EngineSettings\Pages;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Filament\Resources\EngineSettings\Pages\Concerns\HandlesPolicySettings;
use Filament\Resources\Pages\CreateRecord;

class CreateEngineSetting extends CreateRecord
{
    use HandlesPolicySettings;

    protected static string $resource = EngineSettingResource::class;

    public function getTitle(): string
    {
        return 'Settings';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->capturePolicyData($data);
    }

    protected function afterCreate(): void
    {
        $this->persistPolicyData();
    }
}
