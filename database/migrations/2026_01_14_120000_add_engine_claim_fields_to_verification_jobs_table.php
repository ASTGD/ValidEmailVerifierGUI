<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->foreignId('engine_server_id')
                ->nullable()
                ->after('user_id')
                ->constrained('engine_servers')
                ->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('engine_server_id');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
            $table->string('claim_token', 64)->nullable()->after('claim_expires_at');
            $table->unsignedInteger('engine_attempts')->default(0)->after('claim_token');

            $table->index('engine_server_id');
            $table->index('claim_expires_at');
            $table->unique('claim_token');
        });
    }

    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropUnique(['claim_token']);
            $table->dropIndex(['engine_server_id']);
            $table->dropIndex(['claim_expires_at']);
            $table->dropForeign(['engine_server_id']);
            $table->dropColumn([
                'engine_server_id',
                'claimed_at',
                'claim_expires_at',
                'claim_token',
                'engine_attempts',
            ]);
        });
    }
};
