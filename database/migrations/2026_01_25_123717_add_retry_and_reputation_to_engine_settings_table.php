<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->boolean('tempfail_retry_enabled')->default(false)->after('provider_policies');
            $table->unsignedInteger('tempfail_retry_max_attempts')->default(2)->after('tempfail_retry_enabled');
            $table->string('tempfail_retry_backoff_minutes', 255)->nullable()->after('tempfail_retry_max_attempts');
            $table->string('tempfail_retry_reasons', 255)->nullable()->after('tempfail_retry_backoff_minutes');
            $table->unsignedInteger('reputation_window_hours')->default(24)->after('tempfail_retry_reasons');
            $table->unsignedInteger('reputation_min_samples')->default(100)->after('reputation_window_hours');
            $table->decimal('reputation_tempfail_warn_rate', 5, 2)->default(0.2)->after('reputation_min_samples');
            $table->decimal('reputation_tempfail_critical_rate', 5, 2)->default(0.4)->after('reputation_tempfail_warn_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn([
                'tempfail_retry_enabled',
                'tempfail_retry_max_attempts',
                'tempfail_retry_backoff_minutes',
                'tempfail_retry_reasons',
                'reputation_window_hours',
                'reputation_min_samples',
                'reputation_tempfail_warn_rate',
                'reputation_tempfail_critical_rate',
            ]);
        });
    }
};
