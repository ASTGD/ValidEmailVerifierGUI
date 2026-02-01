<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_test_mode_enabled')) {
                $table->boolean('cache_writeback_test_mode_enabled')->default(false)->after('cache_writeback_failure_mode');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_writeback_test_table')) {
                $table->string('cache_writeback_test_table')->nullable()->after('cache_writeback_test_mode_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('engine_settings', 'cache_writeback_test_table')) {
                $table->dropColumn('cache_writeback_test_table');
            }
            if (Schema::hasColumn('engine_settings', 'cache_writeback_test_mode_enabled')) {
                $table->dropColumn('cache_writeback_test_mode_enabled');
            }
        });
    }
};
