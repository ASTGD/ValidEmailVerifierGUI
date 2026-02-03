<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_enabled')) {
                $table->boolean('cache_writeback_enabled')->default(false)->after('cache_failure_mode');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_statuses')) {
                $table->json('cache_writeback_statuses')->nullable()->after('cache_writeback_enabled');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_batch_size')) {
                $table->unsignedInteger('cache_writeback_batch_size')->default(25)->after('cache_writeback_statuses');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_max_writes_per_second')) {
                $table->unsignedInteger('cache_writeback_max_writes_per_second')->nullable()->after('cache_writeback_batch_size');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_retry_attempts')) {
                $table->unsignedInteger('cache_writeback_retry_attempts')->default(5)->after('cache_writeback_max_writes_per_second');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_backoff_base_ms')) {
                $table->unsignedInteger('cache_writeback_backoff_base_ms')->default(200)->after('cache_writeback_retry_attempts');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_backoff_max_ms')) {
                $table->unsignedInteger('cache_writeback_backoff_max_ms')->default(2000)->after('cache_writeback_backoff_base_ms');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_failure_mode')) {
                $table->string('cache_writeback_failure_mode')->default('fail_job')->after('cache_writeback_backoff_max_ms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            $columns = [
                'cache_writeback_failure_mode',
                'cache_writeback_backoff_max_ms',
                'cache_writeback_backoff_base_ms',
                'cache_writeback_retry_attempts',
                'cache_writeback_max_writes_per_second',
                'cache_writeback_batch_size',
                'cache_writeback_statuses',
                'cache_writeback_enabled',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('engine_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
