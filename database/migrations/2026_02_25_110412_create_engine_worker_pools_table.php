<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('engine_worker_pools', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('provider_profiles')->nullable();
            $table->timestamps();
        });

        DB::table('engine_worker_pools')->insert([
            'slug' => 'default',
            'name' => 'Default Pool',
            'description' => 'Default worker pool',
            'is_active' => true,
            'is_default' => true,
            'provider_profiles' => json_encode([
                'generic' => 'standard',
                'gmail' => 'standard',
                'microsoft' => 'standard',
                'yahoo' => 'standard',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_worker_pools');
    }
};
