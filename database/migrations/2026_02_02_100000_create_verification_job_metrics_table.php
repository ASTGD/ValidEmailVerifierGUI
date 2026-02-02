<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_job_metrics', function (Blueprint $table) {
            $table->uuid('verification_job_id')->primary();
            $table->string('phase', 32)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('processed_emails')->default(0);
            $table->unsignedInteger('total_emails')->nullable();
            $table->unsignedInteger('cache_hit_count')->default(0);
            $table->unsignedInteger('cache_miss_count')->default(0);
            $table->unsignedInteger('writeback_written_count')->default(0);
            $table->decimal('peak_cpu_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('cpu_time_ms')->nullable();
            $table->timestamp('cpu_sampled_at')->nullable();
            $table->decimal('peak_memory_mb', 8, 2)->nullable();
            $table->timestamp('phase_started_at')->nullable();
            $table->timestamp('phase_updated_at')->nullable();
            $table->timestamps();

            $table->foreign('verification_job_id')
                ->references('id')
                ->on('verification_jobs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_job_metrics');
    }
};
