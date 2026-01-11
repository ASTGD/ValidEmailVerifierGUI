<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AdminDashboardHighlights extends Widget
{
    protected string $view = 'filament.widgets.admin-dashboard-highlights';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'cards' => [
                [
                    'title' => 'Revenue',
                    'value' => '$192.10k',
                    'delta' => '32% increase',
                    'delta_class' => 'text-emerald-400',
                    'line_class' => 'stroke-emerald-400',
                    'points' => '0,38 18,40 36,36 54,34 72,36 90,30 108,32 126,26 144,30 162,24 180,28 198,22 216,20 234,24 252,22 270,18',
                ],
                [
                    'title' => 'New customers',
                    'value' => '1.34k',
                    'delta' => '3% decrease',
                    'delta_class' => 'text-rose-400',
                    'line_class' => 'stroke-rose-400',
                    'points' => '0,18 18,20 36,22 54,24 72,26 90,28 108,30 126,30 144,32 162,34 180,36 198,36 216,38 234,40 252,42 270,44',
                ],
                [
                    'title' => 'New orders',
                    'value' => '3.54k',
                    'delta' => '7% increase',
                    'delta_class' => 'text-emerald-400',
                    'line_class' => 'stroke-emerald-400',
                    'points' => '0,34 18,36 36,32 54,30 72,34 90,28 108,30 126,26 144,28 162,24 180,26 198,22 216,24 234,20 252,22 270,18',
                ],
            ],
        ];
    }
}
