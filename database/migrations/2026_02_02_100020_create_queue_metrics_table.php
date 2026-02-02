<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 32);
            $table->string('queue', 64);
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('oldest_age_seconds')->nullable();
            $table->unsignedInteger('throughput_per_min')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['driver', 'queue', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metrics');
    }
};
