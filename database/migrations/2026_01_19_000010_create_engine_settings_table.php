<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('engine_paused')->default(false);
            $table->boolean('enhanced_mode_enabled')->default(false);
            $table->timestamps();
        });

        DB::table('engine_settings')->insert([
            'engine_paused' => (bool) config('engine.engine_paused', false),
            'enhanced_mode_enabled' => (bool) config('engine.enhanced_mode_enabled', false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_settings');
    }
};
