<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_metrics_rollups', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 32)->index();
            $table->string('queue', 64)->index();
            $table->string('period_type', 16)->index();
            $table->timestamp('period_start')->index();
            $table->unsignedInteger('samples')->default(0);
            $table->decimal('avg_depth', 10, 2)->default(0);
            $table->unsignedInteger('max_depth')->default(0);
            $table->decimal('avg_oldest_age_seconds', 10, 2)->nullable();
            $table->unsignedInteger('max_oldest_age_seconds')->nullable();
            $table->decimal('avg_failed_count', 10, 2)->default(0);
            $table->unsignedInteger('max_failed_count')->default(0);
            $table->decimal('avg_throughput_per_min', 10, 2)->nullable();
            $table->unsignedInteger('max_throughput_per_min')->nullable();
            $table->timestamps();

            $table->unique(['driver', 'queue', 'period_type', 'period_start'], 'queue_metrics_rollups_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metrics_rollups');
    }
};
