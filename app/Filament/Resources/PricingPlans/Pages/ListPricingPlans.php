<?php

namespace App\Filament\Resources\PricingPlans\Pages;

use App\Filament\Resources\PricingPlans\PricingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricingPlans extends ListRecords
{
    protected static string $resource = PricingPlanResource::class;

    /**
     * @return array<class-string<CreateAction>>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
