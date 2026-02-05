<?php

namespace App\Filament\Widgets;

use App\Support\EngineSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class OpsQueueStatusOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $queueDriver = (string) config('queue.default', 'sync');
        $cacheStore = (string) config('cache.default', 'database');

        $redis = $this->redisStatus();
        $horizon = $this->horizonStatus($redis['ok']);

        return [
            Stat::make('Queue driver', strtoupper($queueDriver))
                ->description('Cache: ' . $cacheStore)
                ->color($queueDriver === 'redis' ? 'success' : 'warning'),
            Stat::make('Redis', $redis['label'])
                ->description($redis['detail'])
                ->color($redis['color']),
            Stat::make('Horizon', $horizon['label'])
                ->description($horizon['detail'])
                ->color($horizon['color']),
        ];
    }

    /**
     * @return array{ok: bool, label: string, detail: string, color: string}
     */
    private function redisStatus(): array
    {
        $host = (string) config('database.redis.default.host', 'redis');
        $port = (string) config('database.redis.default.port', 6379);

        try {
            $response = Redis::connection('default')->ping();

            return [
                'ok' => true,
                'label' => is_string($response) ? strtoupper($response) : 'Connected',
                'detail' => sprintf('%s:%s', $host, $port),
                'color' => 'success',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'label' => 'Unavailable',
                'detail' => 'Connection failed',
                'color' => 'danger',
            ];
        }
    }

    /**
     * @return array{label: string, detail: string, color: string}
     */
    private function horizonStatus(bool $redisOk): array
    {
        if (! EngineSettings::horizonEnabled()) {
            return [
                'label' => 'Disabled',
                'detail' => 'Toggle off',
                'color' => 'gray',
            ];
        }

        if (! $redisOk) {
            return [
                'label' => 'Unavailable',
                'detail' => 'Redis offline',
                'color' => 'danger',
            ];
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            if ($masters === []) {
                return [
                    'label' => 'Stopped',
                    'detail' => 'No supervisors',
                    'color' => 'warning',
                ];
            }

            $statuses = collect($masters)->pluck('status');
            $paused = $statuses->every(fn ($status): bool => $status === 'paused');

            return [
                'label' => $paused ? 'Paused' : 'Running',
                'detail' => sprintf('%d supervisor(s)', count($masters)),
                'color' => $paused ? 'warning' : 'success',
            ];
        } catch (\Throwable $exception) {
            return [
                'label' => 'Unknown',
                'detail' => 'Status unavailable',
                'color' => 'gray',
            ];
        }
    }
}
