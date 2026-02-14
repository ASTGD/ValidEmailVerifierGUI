<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('smtp_truth_labels')) {
            Schema::create('smtp_truth_labels', function (Blueprint $table): void {
                $table->id();
                $table->char('email_hash', 64);
                $table->string('provider', 32)->default('generic');
                $table->string('truth_label', 32);
                $table->string('confidence_hint', 16)->default('medium');
                $table->string('source', 32)->default('sg6_seed_send');
                $table->uuid('source_campaign_id')->nullable();
                $table->unsignedBigInteger('source_recipient_id')->nullable();
                $table->string('decision_class', 32)->nullable();
                $table->string('reason_tag', 64)->nullable();
                $table->json('evidence_payload')->nullable();
                $table->timestamp('observed_at')->nullable();
                $table->timestamps();

                $table->index(['provider', 'observed_at'], 'smtp_truth_labels_provider_observed_idx');
                $table->index(['email_hash', 'observed_at'], 'smtp_truth_labels_email_hash_observed_idx');
                $table->unique('source_recipient_id', 'smtp_truth_labels_source_recipient_unique');
            });
        }

        if (! Schema::hasTable('smtp_confidence_calibrations')) {
            Schema::create('smtp_confidence_calibrations', function (Blueprint $table): void {
                $table->id();
                $table->date('rollup_date');
                $table->string('provider', 32)->default('generic');
                $table->string('decision_class', 32)->default('unknown');
                $table->string('confidence_hint', 16)->default('low');
                $table->unsignedInteger('sample_count')->default(0);
                $table->unsignedInteger('match_count')->default(0);
                $table->unsignedInteger('unknown_count')->default(0);
                $table->decimal('precision_rate', 8, 5)->default(0);
                $table->json('supporting_metrics')->nullable();
                $table->timestamps();

                $table->unique(
                    ['rollup_date', 'provider', 'decision_class', 'confidence_hint'],
                    'smtp_conf_calib_rollup_provider_decision_conf_uq'
                );
                $table->index(['provider', 'rollup_date'], 'smtp_conf_calib_provider_rollup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_confidence_calibrations');
        Schema::dropIfExists('smtp_truth_labels');
    }
};
