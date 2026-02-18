<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_job_chunks', function (Blueprint $table): void {
            if (! Schema::hasColumn('verification_job_chunks', 'routing_provider')) {
                $table->string('routing_provider', 32)->nullable()->after('source_stage');
                $table->index('routing_provider', 'verification_job_chunks_routing_provider_idx');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'routing_domain')) {
                $table->string('routing_domain', 191)->nullable()->after('routing_provider');
                $table->index('routing_domain', 'verification_job_chunks_routing_domain_idx');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'preferred_pool')) {
                $table->string('preferred_pool', 64)->nullable()->after('routing_domain');
                $table->index('preferred_pool', 'verification_job_chunks_preferred_pool_idx');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'rotation_group_id')) {
                $table->uuid('rotation_group_id')->nullable()->after('preferred_pool');
                $table->index('rotation_group_id', 'verification_job_chunks_rotation_group_idx');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'last_worker_ids')) {
                $table->json('last_worker_ids')->nullable()->after('rotation_group_id');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'max_probe_attempts')) {
                $table->unsignedSmallInteger('max_probe_attempts')->default(3)->after('last_worker_ids');
            }
        });

        Schema::create('smtp_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 64)->unique();
            $table->string('status', 32)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->json('policy_payload');
            $table->string('created_by', 128)->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });

        Schema::create('smtp_policy_rollouts', function (Blueprint $table): void {
            $table->id();
            $table->string('policy_version', 64);
            $table->string('provider', 32)->default('generic');
            $table->unsignedTinyInteger('canary_percent')->default(100);
            $table->string('status', 32)->default('active');
            $table->string('triggered_by', 128)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['policy_version', 'provider']);
        });

        Schema::create('smtp_probe_quality_rollups', function (Blueprint $table): void {
            $table->id();
            $table->date('rollup_date');
            $table->string('provider', 32)->default('generic');
            $table->unsignedInteger('sample_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->unsignedInteger('tempfail_count')->default(0);
            $table->unsignedInteger('policy_blocked_count')->default(0);
            $table->unsignedInteger('retry_success_count')->default(0);
            $table->decimal('unknown_rate', 8, 5)->default(0);
            $table->decimal('tempfail_recovery_rate', 8, 5)->default(0);
            $table->decimal('policy_blocked_rate', 8, 5)->default(0);
            $table->decimal('retry_waste_rate', 8, 5)->default(0);
            $table->timestamps();

            $table->unique(['rollup_date', 'provider'], 'smtp_probe_quality_rollups_date_provider_unique');
            $table->index(['provider', 'rollup_date'], 'smtp_probe_quality_rollups_provider_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_probe_quality_rollups');
        Schema::dropIfExists('smtp_policy_rollouts');
        Schema::dropIfExists('smtp_policy_versions');

        Schema::table('verification_job_chunks', function (Blueprint $table): void {
            if (Schema::hasColumn('verification_job_chunks', 'routing_provider')) {
                $table->dropIndex('verification_job_chunks_routing_provider_idx');
            }

            if (Schema::hasColumn('verification_job_chunks', 'routing_domain')) {
                $table->dropIndex('verification_job_chunks_routing_domain_idx');
            }

            if (Schema::hasColumn('verification_job_chunks', 'preferred_pool')) {
                $table->dropIndex('verification_job_chunks_preferred_pool_idx');
            }

            if (Schema::hasColumn('verification_job_chunks', 'rotation_group_id')) {
                $table->dropIndex('verification_job_chunks_rotation_group_idx');
            }

            $columns = [
                'max_probe_attempts',
                'last_worker_ids',
                'rotation_group_id',
                'preferred_pool',
                'routing_domain',
                'routing_provider',
            ];
            $toDrop = [];

            foreach ($columns as $column) {
                if (Schema::hasColumn('verification_job_chunks', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
