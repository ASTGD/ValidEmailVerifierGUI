<?php

namespace App\Filament\Widgets;

use App\Support\RetentionSettings;
use Filament\Widgets\Widget;

class AdminQuickLinks extends Widget
{
    protected string $view = 'filament.widgets.admin-quick-links';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 200;

    protected function getViewData(): array
    {
        return [
            'storageDisk' => config('verifier.storage_disk') ?: config('filesystems.default'),
            'retentionDays' => RetentionSettings::days(),
            'heartbeatMinutes' => (int) config('verifier.engine_heartbeat_minutes', 5),
        ];
    }
}
