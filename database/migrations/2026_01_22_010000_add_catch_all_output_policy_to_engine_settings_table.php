<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->string('catch_all_policy')->default('risky_only');
            $table->unsignedSmallInteger('catch_all_promote_threshold')->nullable();
        });

        DB::table('engine_settings')->update([
            'catch_all_policy' => (string) config('engine.catch_all_policy', 'risky_only'),
            'catch_all_promote_threshold' => config('engine.catch_all_promote_threshold'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn(['catch_all_policy', 'catch_all_promote_threshold']);
        });
    }
};
