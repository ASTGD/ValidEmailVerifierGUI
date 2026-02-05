<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EngineServers\EngineServerResource;
use Filament\Widgets\Widget;

class OpsVerifierEngineLinks extends Widget
{
    protected string $view = 'filament.widgets.ops-verifier-engine-links';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected function getViewData(): array
    {
        return [
            'serversUrl' => EngineServerResource::getUrl('index'),
            'createUrl' => EngineServerResource::getUrl('create'),
        ];
    }
}
