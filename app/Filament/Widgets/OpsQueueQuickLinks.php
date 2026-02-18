<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EngineSettings\EngineSettingResource;
use App\Support\EngineSettings;
use Filament\Widgets\Widget;

class OpsQueueQuickLinks extends Widget
{
    protected string $view = 'filament.widgets.ops-queue-quick-links';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    protected function getViewData(): array
    {
        $settingsUrl = EngineSettingResource::getUrl('index');
        $settingsUrl .= str_contains($settingsUrl, '?') ? '&' : '?';
        $settingsUrl .= 'tab=queue-engine::data::tab';

        return [
            'settingsUrl' => $settingsUrl,
            'horizonUrl' => url('/' . trim((string) config('horizon.path', 'horizon'), '/')),
            'horizonEnabled' => EngineSettings::horizonEnabled(),
        ];
    }
}
