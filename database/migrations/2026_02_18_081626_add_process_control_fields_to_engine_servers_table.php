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
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->string('process_control_mode')->default('control_plane_only')->after('notes');
            $table->boolean('agent_enabled')->default(false)->after('process_control_mode');
            $table->string('agent_base_url')->nullable()->after('agent_enabled');
            $table->unsignedSmallInteger('agent_timeout_seconds')->default(8)->after('agent_base_url');
            $table->boolean('agent_verify_tls')->default(true)->after('agent_timeout_seconds');
            $table->string('agent_service_name')->default('vev-worker.service')->after('agent_verify_tls');
            $table->json('last_agent_status')->nullable()->after('agent_service_name');
            $table->timestamp('last_agent_seen_at')->nullable()->after('last_agent_status');
            $table->text('last_agent_error')->nullable()->after('last_agent_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->dropColumn([
                'process_control_mode',
                'agent_enabled',
                'agent_base_url',
                'agent_timeout_seconds',
                'agent_verify_tls',
                'agent_service_name',
                'last_agent_status',
                'last_agent_seen_at',
                'last_agent_error',
            ]);
        });
    }
};
