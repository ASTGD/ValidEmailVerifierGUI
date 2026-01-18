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
        Schema::create('verification_job_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('verification_job_id')->constrained('verification_jobs')->cascadeOnDelete();
            $table->unsignedInteger('chunk_no');
            $table->string('status')->default('pending');
            $table->string('input_disk', 64)->nullable();
            $table->string('input_key', 1024);
            $table->string('output_disk', 64)->nullable();
            $table->string('valid_key', 1024)->nullable();
            $table->string('invalid_key', 1024)->nullable();
            $table->string('risky_key', 1024)->nullable();
            $table->unsignedInteger('email_count')->default(0);
            $table->unsignedInteger('valid_count')->nullable();
            $table->unsignedInteger('invalid_count')->nullable();
            $table->unsignedInteger('risky_count')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('engine_server_id')->nullable()->constrained('engine_servers')->nullOnDelete();
            $table->string('assigned_worker_id', 128)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->string('claim_token', 120)->nullable();
            $table->timestamps();

            $table->unique(['verification_job_id', 'chunk_no']);
            $table->index(['verification_job_id', 'status']);
            $table->index('status');
            $table->index('engine_server_id');
            $table->index('claim_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_job_chunks');
    }
};
