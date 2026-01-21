<?php

namespace App\Filament\Resources\EngineSettings\Pages\Concerns;

use App\Models\EngineVerificationPolicy;
use Illuminate\Support\Arr;

trait HandlesPolicySettings
{
    protected array $policyData = [];

    protected function fillPolicyFormData(array $data): array
    {
        $policies = EngineVerificationPolicy::query()->get()->keyBy('mode');

        foreach (['standard', 'enhanced'] as $mode) {
            $policy = $policies->get($mode);
            $data = array_merge($data, $this->policyFormData($mode, $policy?->toArray() ?? []));
        }

        return $data;
    }

    protected function capturePolicyData(array $data): array
    {
        [$engineData, $policyData] = $this->splitPolicyData($data);
        $this->policyData = $policyData;

        return $engineData;
    }

    protected function persistPolicyData(): void
    {
        foreach (['standard', 'enhanced'] as $mode) {
            if (! isset($this->policyData[$mode])) {
                continue;
            }

            $payload = $this->policyData[$mode];
            $payload['mode'] = $mode;

            EngineVerificationPolicy::query()->updateOrCreate(
                ['mode' => $mode],
                $payload
            );
        }
    }

    private function policyFormData(string $mode, array $data): array
    {
        $defaults = config('engine.policy_defaults.'.$mode, []);

        return [
            'policy_'.$mode.'_enabled' => (bool) ($data['enabled'] ?? $defaults['enabled'] ?? false),
            'policy_'.$mode.'_dns_timeout_ms' => $data['dns_timeout_ms'] ?? $defaults['dns_timeout_ms'] ?? null,
            'policy_'.$mode.'_smtp_connect_timeout_ms' => $data['smtp_connect_timeout_ms'] ?? $defaults['smtp_connect_timeout_ms'] ?? null,
            'policy_'.$mode.'_smtp_read_timeout_ms' => $data['smtp_read_timeout_ms'] ?? $defaults['smtp_read_timeout_ms'] ?? null,
            'policy_'.$mode.'_max_mx_attempts' => $data['max_mx_attempts'] ?? $defaults['max_mx_attempts'] ?? null,
            'policy_'.$mode.'_max_concurrency_default' => $data['max_concurrency_default'] ?? $defaults['max_concurrency_default'] ?? null,
            'policy_'.$mode.'_per_domain_concurrency' => $data['per_domain_concurrency'] ?? $defaults['per_domain_concurrency'] ?? null,
            'policy_'.$mode.'_catch_all_detection_enabled' => (bool) ($data['catch_all_detection_enabled'] ?? $defaults['catch_all_detection_enabled'] ?? false),
            'policy_'.$mode.'_global_connects_per_minute' => $data['global_connects_per_minute'] ?? $defaults['global_connects_per_minute'] ?? null,
            'policy_'.$mode.'_tempfail_backoff_seconds' => $data['tempfail_backoff_seconds'] ?? $defaults['tempfail_backoff_seconds'] ?? null,
            'policy_'.$mode.'_circuit_breaker_tempfail_rate' => $data['circuit_breaker_tempfail_rate'] ?? $defaults['circuit_breaker_tempfail_rate'] ?? null,
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    private function splitPolicyData(array $data): array
    {
        $policyData = [];

        foreach (['standard', 'enhanced'] as $mode) {
            $prefix = 'policy_'.$mode.'_';
            $entries = Arr::where($data, fn ($value, $key) => str_starts_with($key, $prefix));

            foreach (array_keys($entries) as $key) {
                unset($data[$key]);
            }

            if ($entries === []) {
                continue;
            }

            $policyData[$mode] = [
                'enabled' => (bool) ($entries[$prefix.'enabled'] ?? false),
                'dns_timeout_ms' => $this->toInt($entries[$prefix.'dns_timeout_ms'] ?? null),
                'smtp_connect_timeout_ms' => $this->toInt($entries[$prefix.'smtp_connect_timeout_ms'] ?? null),
                'smtp_read_timeout_ms' => $this->toInt($entries[$prefix.'smtp_read_timeout_ms'] ?? null),
                'max_mx_attempts' => $this->toInt($entries[$prefix.'max_mx_attempts'] ?? null),
                'max_concurrency_default' => $this->toInt($entries[$prefix.'max_concurrency_default'] ?? null),
                'per_domain_concurrency' => $this->toInt($entries[$prefix.'per_domain_concurrency'] ?? null),
                'catch_all_detection_enabled' => (bool) ($entries[$prefix.'catch_all_detection_enabled']
                    ?? data_get(config('engine.policy_defaults'), $mode.'.catch_all_detection_enabled', false)),
                'global_connects_per_minute' => $this->toOptionalInt($entries[$prefix.'global_connects_per_minute'] ?? null),
                'tempfail_backoff_seconds' => $this->toOptionalInt($entries[$prefix.'tempfail_backoff_seconds'] ?? null),
                'circuit_breaker_tempfail_rate' => $this->toOptionalFloat($entries[$prefix.'circuit_breaker_tempfail_rate'] ?? null),
            ];
        }

        return [$data, $policyData];
    }

    private function toInt(mixed $value): int
    {
        return (int) $value;
    }

    private function toOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toOptionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
