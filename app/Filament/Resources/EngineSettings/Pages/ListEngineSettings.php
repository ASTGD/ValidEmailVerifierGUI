<?php

namespace App\Filament\Resources\EngineSettings\Pages;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Models\EngineSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEngineSettings extends ListRecords
{
    protected static string $resource = EngineSettingResource::class;

    public function mount(): void
    {
        parent::mount();

        $record = EngineSetting::query()->first();
        if ($record !== null) {
            $this->redirect(EngineSettingResource::getUrl('edit', ['record' => $record]));
        }
    }

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        if (EngineSetting::query()->exists()) {
            return [];
        }

        return [
            CreateAction::make(),
        ];
    }
}
