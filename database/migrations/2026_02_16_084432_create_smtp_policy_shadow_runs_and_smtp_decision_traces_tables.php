<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('smtp_policy_shadow_runs')) {
            Schema::create('smtp_policy_shadow_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_uuid')->unique();
                $table->string('candidate_version', 64);
                $table->string('active_version', 64)->nullable();
                $table->string('provider', 32)->default('generic');
                $table->string('status', 32)->default('queued');
                $table->unsignedInteger('sample_size')->default(0);
                $table->decimal('unknown_rate_delta', 8, 5)->default(0);
                $table->decimal('tempfail_recovery_delta', 8, 5)->default(0);
                $table->decimal('policy_block_rate_delta', 8, 5)->default(0);
                $table->json('drift_summary')->nullable();
                $table->timestamp('evaluated_at')->nullable();
                $table->string('created_by', 128)->default('system');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['provider', 'status'], 'smtp_policy_shadow_runs_provider_status_idx');
                $table->index(['candidate_version', 'created_at'], 'smtp_policy_shadow_runs_version_created_idx');
                $table->index(['evaluated_at', 'status'], 'smtp_policy_shadow_runs_evaluated_status_idx');
            });
        }

        if (! Schema::hasTable('smtp_decision_traces')) {
            Schema::create('smtp_decision_traces', function (Blueprint $table): void {
                $table->id();
                $table->uuid('verification_job_id')->nullable();
                $table->uuid('verification_job_chunk_id')->nullable();
                $table->char('email_hash', 64);
                $table->string('provider', 32)->default('generic');
                $table->string('policy_version', 64)->nullable();
                $table->string('matched_rule_id', 128)->nullable();
                $table->string('decision_class', 32)->nullable();
                $table->string('smtp_code', 32)->nullable();
                $table->string('enhanced_code', 32)->nullable();
                $table->string('retry_strategy', 32)->nullable();
                $table->string('reason_tag', 64)->nullable();
                $table->string('confidence_hint', 16)->default('medium');
                $table->string('session_strategy_id', 128)->nullable();
                $table->json('attempt_route')->nullable();
                $table->json('trace_payload')->nullable();
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();

                $table->index(['provider', 'observed_at'], 'smtp_decision_traces_provider_observed_idx');
                $table->index(['decision_class', 'observed_at'], 'smtp_decision_traces_decision_observed_idx');
                $table->index(['email_hash', 'observed_at'], 'smtp_decision_traces_email_observed_idx');
                $table->index(['verification_job_id', 'observed_at'], 'smtp_decision_traces_job_observed_idx');
                $table->index(
                    ['verification_job_chunk_id', 'observed_at'],
                    'smtp_decision_traces_chunk_observed_idx'
                );
                $table->unique(
                    ['verification_job_chunk_id', 'email_hash', 'decision_class'],
                    'smtp_decision_traces_chunk_email_decision_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_decision_traces');
        Schema::dropIfExists('smtp_policy_shadow_runs');
    }
};
