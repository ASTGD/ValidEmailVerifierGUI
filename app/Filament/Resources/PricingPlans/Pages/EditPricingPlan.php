<?php

namespace App\Filament\Resources\PricingPlans\Pages;

use App\Filament\Resources\PricingPlans\PricingPlanResource;
use App\Support\AdminAuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditPricingPlan extends EditRecord
{
    protected static string $resource = PricingPlanResource::class;

    protected function afterSave(): void
    {
        AdminAuditLogger::log('pricing_plan_updated', $this->record);
    }
}
