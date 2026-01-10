<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Filament\Resources\PricingPlans\PricingPlanResource;
use App\Filament\Resources\RetentionSettings\RetentionSettingResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Support\RetentionSettings;
use Filament\Widgets\Widget;

class AdminQuickLinks extends Widget
{
    protected string $view = 'filament.widgets.admin-quick-links';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'links' => [
                ['label' => 'Customers', 'url' => CustomerResource::getUrl()],
                ['label' => 'Verification Jobs', 'url' => VerificationJobResource::getUrl()],
                ['label' => 'Support Tickets', 'url' => SupportTicketResource::getUrl()],
                ['label' => 'Engine Servers', 'url' => EngineServerResource::getUrl()],
                ['label' => 'Subscriptions', 'url' => SubscriptionResource::getUrl()],
                ['label' => 'Pricing Plans', 'url' => PricingPlanResource::getUrl()],
                ['label' => 'Retention Settings', 'url' => RetentionSettingResource::getUrl()],
            ],
            'storageDisk' => config('verifier.storage_disk') ?: config('filesystems.default'),
            'retentionDays' => RetentionSettings::days(),
            'heartbeatMinutes' => (int) config('verifier.engine_heartbeat_minutes', 5),
        ];
    }
}
