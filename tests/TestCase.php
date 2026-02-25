<?php

namespace Tests;

use App\Models\EngineSetting;
use App\Models\EngineVerificationPolicy;
use App\Models\EngineWorkerPool;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureEngineDefaults();
    }

    protected function ensureEngineDefaults(): void
    {
        if (Schema::hasTable('engine_settings')) {
            EngineSetting::query()->firstOrCreate([], [
                'engine_paused' => false,
                'enhanced_mode_enabled' => (bool) config('engine.enhanced_mode_enabled', false),
                'role_accounts_behavior' => (string) config('engine.role_accounts_behavior', 'risky'),
                'role_accounts_list' => (string) config('engine.role_accounts_list', ''),
                'catch_all_policy' => (string) config('engine.catch_all_policy', 'risky_only'),
            ]);
        }

        if (Schema::hasTable('engine_verification_policies')) {
            foreach (['standard', 'enhanced'] as $mode) {
                $defaults = config("engine.policy_defaults.{$mode}", []);

                EngineVerificationPolicy::query()->firstOrCreate(
                    ['mode' => $mode],
                    [
                        'enabled' => (bool) ($defaults['enabled'] ?? false),
                        'dns_timeout_ms' => (int) ($defaults['dns_timeout_ms'] ?? 2000),
                        'smtp_connect_timeout_ms' => (int) ($defaults['smtp_connect_timeout_ms'] ?? 2000),
                        'smtp_read_timeout_ms' => (int) ($defaults['smtp_read_timeout_ms'] ?? 2000),
                        'max_mx_attempts' => (int) ($defaults['max_mx_attempts'] ?? 2),
                        'max_concurrency_default' => (int) ($defaults['max_concurrency_default'] ?? 1),
                        'per_domain_concurrency' => (int) ($defaults['per_domain_concurrency'] ?? 2),
                        'catch_all_detection_enabled' => (bool) ($defaults['catch_all_detection_enabled'] ?? false),
                        'global_connects_per_minute' => $defaults['global_connects_per_minute'] ?? null,
                        'tempfail_backoff_seconds' => $defaults['tempfail_backoff_seconds'] ?? null,
                        'circuit_breaker_tempfail_rate' => $defaults['circuit_breaker_tempfail_rate'] ?? null,
                    ]
                );
            }
        }

        if (Schema::hasTable('engine_worker_pools')) {
            EngineWorkerPool::resolveDefaultId();
        }
    }
}
