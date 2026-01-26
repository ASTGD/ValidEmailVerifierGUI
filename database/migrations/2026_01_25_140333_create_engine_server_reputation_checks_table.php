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
        Schema::create('engine_server_reputation_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->string('ip_address', 64);
            $table->string('rbl', 255);
            $table->string('status', 32);
            $table->string('response', 255)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['engine_server_id', 'checked_at'], 'esrc_server_checked_at');
            $table->index(['engine_server_id', 'rbl'], 'esrc_server_rbl');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_server_reputation_checks');
    }
};
