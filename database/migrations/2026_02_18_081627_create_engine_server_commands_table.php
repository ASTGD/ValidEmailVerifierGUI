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
        Schema::create('engine_server_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('engine_server_id')->constrained('engine_servers')->cascadeOnDelete();
            $table->string('action');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('go_control_plane_internal_api');
            $table->string('request_id')->nullable()->index();
            $table->string('idempotency_key')->nullable();
            $table->string('agent_command_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('agent_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['engine_server_id', 'action']);
            $table->unique(['engine_server_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engine_server_commands');
    }
};
