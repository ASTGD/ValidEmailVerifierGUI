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
        Schema::table('verification_job_chunks', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->after('claim_expires_at');
            $table->unsignedInteger('retry_attempt')->default(0)->after('available_at');
            $table->uuid('retry_parent_id')->nullable()->after('retry_attempt');

            $table->index('available_at');
            $table->index('retry_parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_job_chunks', function (Blueprint $table) {
            $table->dropIndex(['available_at']);
            $table->dropIndex(['retry_parent_id']);
            $table->dropColumn(['available_at', 'retry_attempt', 'retry_parent_id']);
        });
    }
};
