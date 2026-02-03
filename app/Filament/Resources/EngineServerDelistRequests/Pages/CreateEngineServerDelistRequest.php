<?php

namespace App\Filament\Resources\EngineServerDelistRequests\Pages;

use App\Filament\Resources\EngineServerDelistRequests\EngineServerDelistRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEngineServerDelistRequest extends CreateRecord
{
    protected static string $resource = EngineServerDelistRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();

        return $data;
    }
}
