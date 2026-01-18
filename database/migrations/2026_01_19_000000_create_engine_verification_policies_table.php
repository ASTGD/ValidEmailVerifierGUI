<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_verification_policies', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 32)->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('dns_timeout_ms');
            $table->unsignedInteger('smtp_connect_timeout_ms');
            $table->unsignedInteger('smtp_read_timeout_ms');
            $table->unsignedInteger('max_mx_attempts');
            $table->unsignedInteger('max_concurrency_default');
            $table->unsignedInteger('per_domain_concurrency');
            $table->unsignedInteger('global_connects_per_minute')->nullable();
            $table->unsignedInteger('tempfail_backoff_seconds')->nullable();
            $table->decimal('circuit_breaker_tempfail_rate', 5, 2)->nullable();
            $table->timestamps();
        });

        $defaults = config('engine.policy_defaults', []);
        $now = now();
        $rows = [];

        foreach (['standard', 'enhanced'] as $mode) {
            $policy = $defaults[$mode] ?? null;
            if (! is_array($policy)) {
                continue;
            }

            $rows[] = [
                'mode' => $mode,
                'enabled' => (bool) ($policy['enabled'] ?? false),
                'dns_timeout_ms' => (int) ($policy['dns_timeout_ms'] ?? 0),
                'smtp_connect_timeout_ms' => (int) ($policy['smtp_connect_timeout_ms'] ?? 0),
                'smtp_read_timeout_ms' => (int) ($policy['smtp_read_timeout_ms'] ?? 0),
                'max_mx_attempts' => (int) ($policy['max_mx_attempts'] ?? 0),
                'max_concurrency_default' => (int) ($policy['max_concurrency_default'] ?? 0),
                'per_domain_concurrency' => (int) ($policy['per_domain_concurrency'] ?? 0),
                'global_connects_per_minute' => $policy['global_connects_per_minute'] ?? null,
                'tempfail_backoff_seconds' => $policy['tempfail_backoff_seconds'] ?? null,
                'circuit_breaker_tempfail_rate' => $policy['circuit_breaker_tempfail_rate'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('engine_verification_policies')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_verification_policies');
    }
};
