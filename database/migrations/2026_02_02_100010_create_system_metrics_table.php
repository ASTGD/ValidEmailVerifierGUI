<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16);
            $table->timestamp('captured_at')->index();
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('cpu_total_ticks')->nullable();
            $table->unsignedBigInteger('cpu_idle_ticks')->nullable();
            $table->unsignedBigInteger('mem_total_mb')->nullable();
            $table->unsignedBigInteger('mem_used_mb')->nullable();
            $table->unsignedBigInteger('disk_total_gb')->nullable();
            $table->unsignedBigInteger('disk_used_gb')->nullable();
            $table->unsignedBigInteger('io_read_mb')->nullable();
            $table->unsignedBigInteger('io_write_mb')->nullable();
            $table->unsignedBigInteger('io_read_bytes_total')->nullable();
            $table->unsignedBigInteger('io_write_bytes_total')->nullable();
            $table->unsignedBigInteger('net_in_mb')->nullable();
            $table->unsignedBigInteger('net_out_mb')->nullable();
            $table->unsignedBigInteger('net_in_bytes_total')->nullable();
            $table->unsignedBigInteger('net_out_bytes_total')->nullable();
            $table->timestamps();

            $table->index(['source', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
