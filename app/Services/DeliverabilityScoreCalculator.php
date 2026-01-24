<?php

namespace App\Services;

class DeliverabilityScoreCalculator
{
    /**
     * @param  array<string, mixed>|null  $cacheOutcome
     */
    public function calculate(string $status, string $subStatus, string $reason, ?array $cacheOutcome = null): int
    {
        $config = (array) config('engine.deliverability_score', []);
        $baseScores = (array) ($config['base'] ?? []);
        $reasonOverrides = (array) ($config['reason_overrides'] ?? []);
        $subStatusCaps = (array) ($config['sub_status_caps'] ?? []);
        $cacheAdjustments = (array) ($config['cache_adjustments'] ?? []);

        $base = (int) ($baseScores[$status] ?? 0);
        $reasonKey = $reason !== '' ? $reason : 'unknown';

        if (array_key_exists($reasonKey, $reasonOverrides)) {
            $base = (int) $reasonOverrides[$reasonKey];
        }

        if ($cacheOutcome) {
            $cacheStatus = strtolower((string) ($cacheOutcome['outcome'] ?? $cacheOutcome['status'] ?? ''));
            if ($cacheStatus !== '' && array_key_exists($cacheStatus, $cacheAdjustments)) {
                $base += (int) $cacheAdjustments[$cacheStatus];
            }
        }

        if ($subStatus !== '' && array_key_exists($subStatus, $subStatusCaps)) {
            $cap = (int) $subStatusCaps[$subStatus];
            if ($cap > 0) {
                $base = min($base, $cap);
            }
        }

        return max(0, min(100, $base));
    }
}
