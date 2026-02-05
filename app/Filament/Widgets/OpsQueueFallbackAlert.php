<?php

namespace App\Filament\Widgets;

use App\Support\EngineSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Redis;

class OpsQueueFallbackAlert extends Widget
{
    protected string $view = 'filament.widgets.ops-queue-fallback-alert';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        $desiredQueue = EngineSettings::queueConnection();
        $desiredCache = EngineSettings::cacheStore();
        $runtimeQueue = (string) config('queue.default', 'database');
        $runtimeCache = (string) config('cache.default', 'database');

        $redisWanted = $desiredQueue === 'redis' || $desiredCache === 'redis';
        $redisOnline = $this->redisOnline();

        $fallbackActive = $redisWanted && ! $redisOnline && (
            ($desiredQueue === 'redis' && $runtimeQueue !== 'redis') ||
            ($desiredCache === 'redis' && $runtimeCache !== 'redis')
        );

        return [
            'showAlert' => $fallbackActive,
            'runtimeQueue' => $runtimeQueue,
            'runtimeCache' => $runtimeCache,
        ];
    }

    private function redisOnline(): bool
    {
        try {
            Redis::connection('default')->ping();

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
