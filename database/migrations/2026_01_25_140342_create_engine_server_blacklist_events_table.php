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
        Schema::create('engine_server_blacklist_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->string('rbl', 255);
            $table->string('status', 32)->default('active');
            $table->string('severity', 32)->default('warning');
            $table->timestamp('first_seen');
            $table->timestamp('last_seen');
            $table->string('last_response', 255)->nullable();
            $table->unsignedInteger('listed_count')->default(1);
            $table->timestamps();

            $table->unique(['engine_server_id', 'rbl'], 'esbe_server_rbl_unique');
            $table->index(['engine_server_id', 'status'], 'esbe_server_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_server_blacklist_events');
    }
};
