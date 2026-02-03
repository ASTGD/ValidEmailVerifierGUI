<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->string('queue_connection')->nullable()->after('metrics_source');
            $table->string('cache_store')->nullable()->after('queue_connection');
            $table->boolean('horizon_enabled')->default(false)->after('cache_store');
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn(['queue_connection', 'cache_store', 'horizon_enabled']);
        });
    }
};
