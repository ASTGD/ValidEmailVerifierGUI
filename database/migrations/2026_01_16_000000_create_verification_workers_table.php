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
        Schema::create('verification_workers', function (Blueprint $table) {
            $table->id();
            $table->string('worker_id', 128)->unique();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->string('version', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignUuid('current_job_chunk_id')->nullable()->constrained('verification_job_chunks')->nullOnDelete();
            $table->timestamps();

            $table->index('engine_server_id');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_workers');
    }
};
