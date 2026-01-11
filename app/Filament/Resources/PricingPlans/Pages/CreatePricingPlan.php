<?php

namespace App\Filament\Resources\PricingPlans\Pages;

use App\Filament\Resources\PricingPlans\PricingPlanResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreatePricingPlan extends CreateRecord
{
    protected static string $resource = PricingPlanResource::class;

    protected function afterCreate(): void
    {
        AdminAuditLogger::log('pricing_plan_created', $this->record);
    }
}
