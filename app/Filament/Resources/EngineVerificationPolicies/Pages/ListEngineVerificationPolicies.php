<?php

namespace App\Filament\Resources\EngineVerificationPolicies\Pages;

use App\Filament\Resources\EngineVerificationPolicies\EngineVerificationPolicyResource;
use App\Models\EngineVerificationPolicy;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEngineVerificationPolicies extends ListRecords
{
    protected static string $resource = EngineVerificationPolicyResource::class;

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        if (EngineVerificationPolicy::query()->count() >= 2) {
            return [];
        }

        return [
            CreateAction::make(),
        ];
    }
}
