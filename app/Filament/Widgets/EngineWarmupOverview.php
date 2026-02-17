<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Services\EngineServerWarmupSummaryService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EngineWarmupOverview extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        /** @var EngineServerWarmupSummaryService $service */
        $service = app(EngineServerWarmupSummaryService::class);
        $summary = $service->summary();

        $healthy = $summary['healthy'];
        $warming = $summary['warming'];
        $critical = $summary['critical'];
        $overallRate = $summary['overall_rate'];
        $windowHours = $summary['window_hours'];

        $overallColor = 'success';
        if ($overallRate >= $summary['critical_rate']) {
            $overallColor = 'danger';
        } elseif ($overallRate >= $summary['warn_rate']) {
            $overallColor = 'warning';
        }

        $serversUrl = $this->resolveEngineWorkersUrl();

        return [
            Stat::make('Healthy servers', $healthy)
                ->description('Tempfail rate within limits.')
                ->color($healthy > 0 ? 'success' : 'gray')
                ->url($serversUrl),
            Stat::make('Warming / low data', $warming)
                ->description('Insufficient samples or elevated tempfails.')
                ->color($warming > 0 ? 'warning' : 'gray')
                ->url($serversUrl),
            Stat::make('Critical tempfail', $critical)
                ->description('Requires immediate review.')
                ->color($critical > 0 ? 'danger' : 'gray')
                ->url($serversUrl),
            Stat::make('Overall tempfail rate', number_format($overallRate * 100, 1).'%')
                ->description(sprintf('Last %dh window', $windowHours))
                ->color($overallColor)
                ->url($serversUrl),
        ];
    }

    private function resolveEngineWorkersUrl(): string
    {
        $configuredUrl = rtrim((string) config('services.go_control_plane.base_url', ''), '/');
        if ($configuredUrl !== '') {
            if (! str_contains($configuredUrl, '/verifier-engine-room')) {
                return $configuredUrl.'/verifier-engine-room/workers';
            }

            $normalized = preg_replace('#/verifier-engine-room(?:/(?:overview|alerts|pools|settings|workers))?$#', '/verifier-engine-room', $configuredUrl);
            $normalized = is_string($normalized) ? rtrim($normalized, '/') : $configuredUrl;

            return $normalized.'/workers';
        }

        return EngineServerResource::getUrl('index');
    }
}
