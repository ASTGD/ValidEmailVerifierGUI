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
        Schema::create('engine_server_delist_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->string('rbl', 255);
            $table->string('status', 32)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['engine_server_id', 'status'], 'esdr_server_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_server_delist_requests');
    }
};
