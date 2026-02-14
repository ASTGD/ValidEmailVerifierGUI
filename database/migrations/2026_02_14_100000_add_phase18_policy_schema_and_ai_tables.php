<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smtp_policy_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('smtp_policy_versions', 'schema_version')) {
                $table->string('schema_version', 16)->default('v2')->after('version');
                $table->index('schema_version', 'smtp_policy_versions_schema_version_idx');
            }

            if (! Schema::hasColumn('smtp_policy_versions', 'mode_semantics_hash')) {
                $table->string('mode_semantics_hash', 64)->nullable()->after('policy_payload');
                $table->index('mode_semantics_hash', 'smtp_policy_versions_mode_semantics_hash_idx');
            }
        });

        if (! Schema::hasTable('smtp_policy_suggestions')) {
            Schema::create('smtp_policy_suggestions', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32)->default('generic');
                $table->string('status', 32)->default('draft');
                $table->string('suggestion_type', 64);
                $table->string('source_window', 64)->nullable();
                $table->json('suggestion_payload');
                $table->json('supporting_metrics')->nullable();
                $table->unsignedInteger('sample_size')->default(0);
                $table->string('created_by', 128)->default('system');
                $table->timestamp('reviewed_at')->nullable();
                $table->string('reviewed_by', 128)->nullable();
                $table->json('review_notes')->nullable();
                $table->timestamps();

                $table->index(['provider', 'status'], 'smtp_policy_suggestions_provider_status_idx');
                $table->index('suggestion_type', 'smtp_policy_suggestions_type_idx');
            });
        }

        if (! Schema::hasTable('smtp_unknown_clusters')) {
            Schema::create('smtp_unknown_clusters', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 32)->default('generic');
                $table->string('cluster_signature', 128);
                $table->unsignedInteger('sample_count')->default(0);
                $table->json('feature_tokens')->nullable();
                $table->json('example_messages')->nullable();
                $table->json('recommended_tags')->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'cluster_signature'], 'smtp_unknown_clusters_provider_signature_unique');
                $table->index(['provider', 'status'], 'smtp_unknown_clusters_provider_status_idx');
            });
        }

        if (! Schema::hasTable('smtp_policy_action_audits')) {
            Schema::create('smtp_policy_action_audits', function (Blueprint $table): void {
                $table->id();
                $table->string('action', 64);
                $table->string('policy_version', 64)->nullable();
                $table->string('provider', 32)->default('generic');
                $table->string('source', 32)->default('manual');
                $table->string('actor', 128)->nullable();
                $table->string('result', 32)->default('success');
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['provider', 'action'], 'smtp_policy_action_audits_provider_action_idx');
                $table->index(['policy_version', 'action'], 'smtp_policy_action_audits_version_action_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_policy_action_audits');
        Schema::dropIfExists('smtp_unknown_clusters');
        Schema::dropIfExists('smtp_policy_suggestions');

        Schema::table('smtp_policy_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('smtp_policy_versions', 'schema_version')) {
                $table->dropIndex('smtp_policy_versions_schema_version_idx');
            }
            if (Schema::hasColumn('smtp_policy_versions', 'mode_semantics_hash')) {
                $table->dropIndex('smtp_policy_versions_mode_semantics_hash_idx');
            }

            $columns = [];
            if (Schema::hasColumn('smtp_policy_versions', 'mode_semantics_hash')) {
                $columns[] = 'mode_semantics_hash';
            }
            if (Schema::hasColumn('smtp_policy_versions', 'schema_version')) {
                $columns[] = 'schema_version';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
