<?php

namespace App\Filament\Widgets;

use App\Models\EngineServer;
use App\Models\SmtpPolicyVersion;
use App\Models\VerificationJobMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpsGoControlPlaneOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $status = $this->controlPlaneStatus();
        $fallbackCount = $this->fallbackHeartbeatWorkers();
        $policyVersion = $this->activePolicyVersion();
        $probeUnknownRate = $this->probeUnknownRate();

        return [
            Stat::make('Go Control Plane', $status['label'])
                ->description($status['description'])
                ->color($status['color']),
            Stat::make('Laravel Fallback HB', (string) $fallbackCount)
                ->description('Online engine servers with Laravel heartbeat')
                ->color($fallbackCount > 0 ? 'success' : 'warning'),
            Stat::make('Active Probe Policy', $policyVersion)
                ->description('Control-plane rollout version')
                ->color($policyVersion === 'baseline' ? 'gray' : 'success'),
            Stat::make('Probe Unknown Rate', sprintf('%.2f%%', $probeUnknownRate * 100))
                ->description('Aggregated from verification metrics')
                ->color($probeUnknownRate >= 0.25 ? 'danger' : ($probeUnknownRate >= 0.12 ? 'warning' : 'success')),
        ];
    }

    /**
     * @return array{label:string,description:string,color:string}
     */
    private function controlPlaneStatus(): array
    {
        $baseUrl = trim((string) config('services.go_control_plane.base_url'));
        $token = trim((string) config('services.go_control_plane.token'));
        $timeout = max(1, (int) config('services.go_control_plane.timeout_seconds', 3));

        if ($baseUrl === '' || $token === '') {
            return [
                'label' => 'Disabled',
                'description' => 'Set GO_CONTROL_PLANE_BASE_URL/TOKEN',
                'color' => 'gray',
            ];
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout($timeout)
                ->get(rtrim($baseUrl, '/').'/api/health/ready');

            if ($response->successful()) {
                return [
                    'label' => 'Ready',
                    'description' => 'Primary worker control path',
                    'color' => 'success',
                ];
            }

            return [
                'label' => 'Unavailable',
                'description' => 'Health endpoint not ready',
                'color' => 'danger',
            ];
        } catch (Throwable) {
            return [
                'label' => 'Unavailable',
                'description' => 'Health endpoint unreachable',
                'color' => 'danger',
            ];
        }
    }

    private function fallbackHeartbeatWorkers(): int
    {
        $thresholdMinutes = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));

        return EngineServer::query()
            ->where('is_active', true)
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>=', now()->subMinutes($thresholdMinutes))
            ->count();
    }

    private function activePolicyVersion(): string
    {
        $baseUrl = trim((string) config('services.go_control_plane.base_url'));
        $token = trim((string) config('services.go_control_plane.token'));
        $timeout = max(1, (int) config('services.go_control_plane.timeout_seconds', 3));

        if ($baseUrl !== '' && $token !== '') {
            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout($timeout)
                    ->get(rtrim($baseUrl, '/').'/api/providers/policies');

                if ($response->successful()) {
                    $version = trim((string) data_get($response->json(), 'data.active_version', ''));
                    if ($version !== '') {
                        return $version;
                    }
                }
            } catch (Throwable) {
                // Fall through to DB-backed fallback.
            }
        }

        return (string) (SmtpPolicyVersion::query()
            ->where('is_active', true)
            ->value('version') ?? 'baseline');
    }

    private function probeUnknownRate(): float
    {
        $aggregates = VerificationJobMetric::query()
            ->where('phase_updated_at', '>=', now()->subDays(7))
            ->selectRaw('COALESCE(SUM(probe_completed_count), 0) as completed_total')
            ->selectRaw('COALESCE(SUM(probe_unknown_count), 0) as unknown_total')
            ->first();

        $completed = (int) ($aggregates?->completed_total ?? 0);
        $unknown = (int) ($aggregates?->unknown_total ?? 0);

        if ($completed <= 0) {
            return 0.0;
        }

        return min(1.0, max(0.0, $unknown / $completed));
    }
}
