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
        Schema::create('engine_server_reputation_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->uuid('verification_job_chunk_id');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('tempfail_count')->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique('verification_job_chunk_id', 'ess_chunk_id_unique');
            $table->index(['engine_server_id', 'recorded_at'], 'ess_engine_server_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_server_reputation_samples');
    }
};
