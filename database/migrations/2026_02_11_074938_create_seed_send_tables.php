<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seed_send_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('verification_job_id')->constrained('verification_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 32)->default('full_list');
            $table->string('consent_text_version', 32)->default('v1');
            $table->timestamp('consented_at')->nullable();
            $table->foreignId('consented_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('requested');
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['verification_job_id', 'status'], 'seed_send_consents_job_status_idx');
        });

        Schema::create('seed_send_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('verification_job_id')->constrained('verification_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seed_send_consent_id')->constrained('seed_send_consents')->cascadeOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('target_scope', 32)->default('full_list');
            $table->string('provider', 64)->default('log');
            $table->string('provider_campaign_ref', 191)->nullable();
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('deferred_count')->default(0);
            $table->unsignedInteger('credits_reserved')->default(0);
            $table->unsignedInteger('credits_used')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason', 255)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['verification_job_id', 'status'], 'seed_send_campaigns_job_status_idx');
            $table->index(['provider', 'status'], 'seed_send_campaigns_provider_status_idx');
        });

        Schema::create('seed_send_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('campaign_id')->constrained('seed_send_campaigns')->cascadeOnDelete();
            $table->string('email', 320);
            $table->char('email_hash', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('evidence_payload')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'email_hash'], 'seed_send_recipients_campaign_email_hash_unique');
            $table->index(['campaign_id', 'status'], 'seed_send_recipients_campaign_status_idx');
            $table->index('provider_message_id', 'seed_send_recipients_provider_msg_idx');
        });

        Schema::create('seed_send_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('campaign_id')->constrained('seed_send_campaigns')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('seed_send_recipients')->nullOnDelete();
            $table->string('provider', 64)->default('log');
            $table->string('event_type', 64);
            $table->timestamp('event_time')->nullable();
            $table->string('smtp_code', 16)->nullable();
            $table->string('enhanced_code', 16)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'event_type'], 'seed_send_events_campaign_type_idx');
            $table->index('provider_message_id', 'seed_send_events_provider_msg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_send_events');
        Schema::dropIfExists('seed_send_recipients');
        Schema::dropIfExists('seed_send_campaigns');
        Schema::dropIfExists('seed_send_consents');
    }
};
