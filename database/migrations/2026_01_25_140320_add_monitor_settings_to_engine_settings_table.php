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
            $table->boolean('monitor_enabled')->default(false)->after('show_single_checks_in_admin');
            $table->unsignedInteger('monitor_interval_minutes')->default(60)->after('monitor_enabled');
            $table->text('monitor_rbl_list')->nullable()->after('monitor_interval_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn(['monitor_enabled', 'monitor_interval_minutes', 'monitor_rbl_list']);
        });
    }
};
