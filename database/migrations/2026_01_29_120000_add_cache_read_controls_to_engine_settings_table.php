<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('engine_settings', 'cache_capacity_mode')) {
                $table->string('cache_capacity_mode')->default('on_demand')->after('cache_only_miss_status');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_batch_size')) {
                $table->unsignedInteger('cache_batch_size')->default(100)->after('cache_capacity_mode');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_consistent_read')) {
                $table->boolean('cache_consistent_read')->default(false)->after('cache_batch_size');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_ondemand_max_batches_per_second')) {
                $table->unsignedInteger('cache_ondemand_max_batches_per_second')->nullable()->after('cache_consistent_read');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_ondemand_sleep_ms_between_batches')) {
                $table->unsignedInteger('cache_ondemand_sleep_ms_between_batches')->default(0)->after('cache_ondemand_max_batches_per_second');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_max_batches_per_second')) {
                $table->unsignedInteger('cache_provisioned_max_batches_per_second')->default(5)->after('cache_ondemand_sleep_ms_between_batches');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_sleep_ms_between_batches')) {
                $table->unsignedInteger('cache_provisioned_sleep_ms_between_batches')->default(100)->after('cache_provisioned_max_batches_per_second');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_max_retries')) {
                $table->unsignedInteger('cache_provisioned_max_retries')->default(5)->after('cache_provisioned_sleep_ms_between_batches');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_backoff_base_ms')) {
                $table->unsignedInteger('cache_provisioned_backoff_base_ms')->default(200)->after('cache_provisioned_max_retries');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_backoff_max_ms')) {
                $table->unsignedInteger('cache_provisioned_backoff_max_ms')->default(2000)->after('cache_provisioned_backoff_base_ms');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_provisioned_jitter_enabled')) {
                $table->boolean('cache_provisioned_jitter_enabled')->default(true)->after('cache_provisioned_backoff_max_ms');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_failure_mode')) {
                $table->string('cache_failure_mode')->default('fail_job')->after('cache_provisioned_jitter_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            $columns = [
                'cache_failure_mode',
                'cache_provisioned_jitter_enabled',
                'cache_provisioned_backoff_max_ms',
                'cache_provisioned_backoff_base_ms',
                'cache_provisioned_max_retries',
                'cache_provisioned_sleep_ms_between_batches',
                'cache_provisioned_max_batches_per_second',
                'cache_ondemand_sleep_ms_between_batches',
                'cache_ondemand_max_batches_per_second',
                'cache_consistent_read',
                'cache_batch_size',
                'cache_capacity_mode',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('engine_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
