<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('engine_settings', 'cache_only_mode_enabled')) {
                $table->boolean('cache_only_mode_enabled')->default(false)->after('catch_all_promote_threshold');
            }
            if (! Schema::hasColumn('engine_settings', 'cache_only_miss_status')) {
                $table->string('cache_only_miss_status')->default('risky')->after('cache_only_mode_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('engine_settings', 'cache_only_miss_status')) {
                $table->dropColumn('cache_only_miss_status');
            }
            if (Schema::hasColumn('engine_settings', 'cache_only_mode_enabled')) {
                $table->dropColumn('cache_only_mode_enabled');
            }
        });
    }
};
