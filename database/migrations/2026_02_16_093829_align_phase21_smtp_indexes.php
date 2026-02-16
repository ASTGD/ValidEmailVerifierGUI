<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smtp_policy_shadow_runs') && ! $this->indexExists('smtp_policy_shadow_runs', 'smtp_policy_shadow_runs_evaluated_status_idx')) {
            Schema::table('smtp_policy_shadow_runs', function (Blueprint $table): void {
                $table->index(['evaluated_at', 'status'], 'smtp_policy_shadow_runs_evaluated_status_idx');
            });
        }

        if (Schema::hasTable('smtp_decision_traces') && ! $this->indexExists('smtp_decision_traces', 'smtp_decision_traces_chunk_email_decision_unique')) {
            Schema::table('smtp_decision_traces', function (Blueprint $table): void {
                $table->unique(
                    ['verification_job_chunk_id', 'email_hash', 'decision_class'],
                    'smtp_decision_traces_chunk_email_decision_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('smtp_policy_shadow_runs') && $this->indexExists('smtp_policy_shadow_runs', 'smtp_policy_shadow_runs_evaluated_status_idx')) {
            Schema::table('smtp_policy_shadow_runs', function (Blueprint $table): void {
                $table->dropIndex('smtp_policy_shadow_runs_evaluated_status_idx');
            });
        }

        if (Schema::hasTable('smtp_decision_traces') && $this->indexExists('smtp_decision_traces', 'smtp_decision_traces_chunk_email_decision_unique')) {
            Schema::table('smtp_decision_traces', function (Blueprint $table): void {
                $table->dropUnique('smtp_decision_traces_chunk_email_decision_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexName = strtolower($indexName);
        $rows = DB::select(sprintf('SHOW INDEX FROM `%s`', str_replace('`', '', $table)));

        foreach ($rows as $row) {
            $keyName = strtolower((string) ($row->Key_name ?? $row->key_name ?? ''));
            if ($keyName === $indexName) {
                return true;
            }
        }

        return false;
    }
};
