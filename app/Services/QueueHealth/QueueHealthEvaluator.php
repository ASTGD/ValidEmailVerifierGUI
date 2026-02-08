<?php

namespace App\Services\QueueHealth;

use App\Models\QueueMetric;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class QueueHealthEvaluator
{
    public function __construct(private MasterSupervisorRepository $masterSupervisors) {}

    /**
     * @return array{
     *     status: string,
     *     checked_at: string,
     *     issues: array<int, array{key: string, severity: string, title: string, detail: string, lane: string|null}>,
     *     summary: array{critical: int, warning: int},
     *     meta: array{redis_ok: bool, horizon_master_count: int, required_supervisor_count: int, active_supervisor_count: int}
     * }
     */
    public function evaluate(): array
    {
        $issues = [];
        $redisOk = $this->redisOnline();

        if (! $redisOk) {
            $issues[] = $this->issue(
                'redis_unavailable',
                'critical',
                'Redis unavailable',
                'Unable to connect to Redis default connection.',
            );
        }

        $masters = [];

        if ($redisOk) {
            try {
                $masters = $this->masterSupervisors->all();
            } catch (Throwable $exception) {
                $issues[] = $this->issue(
                    'horizon_repository_error',
                    'critical',
                    'Horizon status check failed',
                    $exception->getMessage(),
                );
            }
        }

        if ($redisOk && $masters === []) {
            $issues[] = $this->issue(
                'horizon_inactive',
                'critical',
                'Horizon inactive',
                'No Horizon master supervisors are running.',
            );
        }

        $activeSupervisors = $this->activeSupervisorNames($masters);
        $requiredSupervisors = array_values((array) config('queue_health.required_supervisors', []));

        foreach ($requiredSupervisors as $supervisor) {
            $supervisor = trim((string) $supervisor);

            if ($supervisor === '') {
                continue;
            }

            if (! in_array($supervisor, $activeSupervisors, true)) {
                $issues[] = $this->issue(
                    'missing_supervisor:'.$supervisor,
                    'critical',
                    'Missing supervisor',
                    sprintf('Horizon supervisor "%s" is not running.', $supervisor),
                );
            }
        }

        $lanes = (array) config('queue_health.lanes', []);

        foreach ($lanes as $lane => $definition) {
            $driver = trim((string) ($definition['driver'] ?? ''));
            $queue = trim((string) ($definition['queue'] ?? ''));

            if ($driver === '' || $queue === '') {
                continue;
            }

            $metric = QueueMetric::query()
                ->where('driver', $driver)
                ->where('queue', $queue)
                ->latest('captured_at')
                ->first();

            if (! $metric) {
                $issues[] = $this->issue(
                    sprintf('lane_metric_missing:%s:%s', $driver, $queue),
                    'warning',
                    'Queue metric missing',
                    sprintf('No recent metrics found for lane %s (%s:%s).', $lane, $driver, $queue),
                    $lane,
                );

                continue;
            }

            $maxDepth = max(0, (int) ($definition['max_depth'] ?? 0));
            if ($maxDepth > 0 && (int) $metric->depth > $maxDepth) {
                $issues[] = $this->issue(
                    sprintf('lane_depth:%s:%s', $driver, $queue),
                    'warning',
                    'Queue depth high',
                    sprintf('Lane %s depth is %d (threshold %d).', $lane, (int) $metric->depth, $maxDepth),
                    $lane,
                );
            }

            $maxAge = max(0, (int) ($definition['max_oldest_age_seconds'] ?? 0));
            $oldestAge = $metric->oldest_age_seconds !== null ? (int) $metric->oldest_age_seconds : null;

            if ($maxAge > 0 && $oldestAge !== null && $oldestAge > $maxAge) {
                $issues[] = $this->issue(
                    sprintf('lane_oldest:%s:%s', $driver, $queue),
                    'critical',
                    'Oldest job age high',
                    sprintf('Lane %s oldest job age is %ds (threshold %ds).', $lane, $oldestAge, $maxAge),
                    $lane,
                );
            }
        }

        $issues = $this->sortIssues($issues);
        $critical = collect($issues)->where('severity', 'critical')->count();
        $warning = collect($issues)->where('severity', 'warning')->count();

        $status = 'healthy';
        if ($critical > 0) {
            $status = 'critical';
        } elseif ($warning > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'issues' => $issues,
            'summary' => [
                'critical' => $critical,
                'warning' => $warning,
            ],
            'meta' => [
                'redis_ok' => $redisOk,
                'horizon_master_count' => count($masters),
                'required_supervisor_count' => count($requiredSupervisors),
                'active_supervisor_count' => count($activeSupervisors),
            ],
        ];
    }

    private function redisOnline(): bool
    {
        try {
            Redis::connection('default')->ping();

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @param  array<int, mixed>  $masters
     * @return array<int, string>
     */
    private function activeSupervisorNames(array $masters): array
    {
        $names = [];

        foreach ($masters as $master) {
            $supervisors = (array) data_get($master, 'supervisors', []);

            foreach ($supervisors as $supervisor) {
                $raw = trim((string) $supervisor);
                if ($raw === '') {
                    continue;
                }

                $names[] = Str::contains($raw, ':')
                    ? trim((string) Str::afterLast($raw, ':'))
                    : $raw;
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * @return array{key: string, severity: string, title: string, detail: string, lane: string|null}
     */
    private function issue(string $key, string $severity, string $title, string $detail, ?string $lane = null): array
    {
        return [
            'key' => $key,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'lane' => $lane,
        ];
    }

    /**
     * @param  array<int, array{key: string, severity: string, title: string, detail: string, lane: string|null}>  $issues
     * @return array<int, array{key: string, severity: string, title: string, detail: string, lane: string|null}>
     */
    private function sortIssues(array $issues): array
    {
        usort($issues, static function (array $left, array $right): int {
            $severityWeight = [
                'critical' => 0,
                'warning' => 1,
            ];

            $leftWeight = $severityWeight[$left['severity']] ?? 9;
            $rightWeight = $severityWeight[$right['severity']] ?? 9;

            if ($leftWeight !== $rightWeight) {
                return $leftWeight <=> $rightWeight;
            }

            return strcmp($left['key'], $right['key']);
        });

        return $issues;
    }
}
