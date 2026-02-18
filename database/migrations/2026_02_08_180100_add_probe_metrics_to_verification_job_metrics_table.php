<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_job_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('verification_job_metrics', 'screening_total_count')) {
                $table->unsignedInteger('screening_total_count')
                    ->default(0)
                    ->after('cache_miss_count');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'probe_candidate_count')) {
                $table->unsignedInteger('probe_candidate_count')
                    ->default(0)
                    ->after('screening_total_count');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'probe_completed_count')) {
                $table->unsignedInteger('probe_completed_count')
                    ->default(0)
                    ->after('probe_candidate_count');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'probe_unknown_count')) {
                $table->unsignedInteger('probe_unknown_count')
                    ->default(0)
                    ->after('probe_completed_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_job_metrics', function (Blueprint $table): void {
            $columns = [
                'probe_unknown_count',
                'probe_completed_count',
                'probe_candidate_count',
                'screening_total_count',
            ];

            $toDrop = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn('verification_job_metrics', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
