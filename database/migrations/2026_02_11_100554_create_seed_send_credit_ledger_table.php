<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seed_send_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('campaign_id')->nullable();
            $table->foreignUuid('verification_job_id')->constrained('verification_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entry_type', 32);
            $table->unsignedInteger('credits');
            $table->string('reference_key', 191)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')
                ->references('id')
                ->on('seed_send_campaigns')
                ->cascadeOnDelete();
            $table->index(['user_id', 'created_at'], 'seed_send_credit_ledger_user_created_idx');
            $table->index(['campaign_id', 'entry_type'], 'seed_send_credit_ledger_campaign_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_send_credit_ledger');
    }
};
