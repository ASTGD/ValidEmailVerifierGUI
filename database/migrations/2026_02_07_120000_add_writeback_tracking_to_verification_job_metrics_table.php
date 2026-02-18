<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_job_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('verification_job_metrics', 'writeback_status')) {
                $table->string('writeback_status', 32)->nullable()->after('writeback_written_count');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'writeback_attempted_count')) {
                $table->unsignedInteger('writeback_attempted_count')->default(0)->after('writeback_status');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'writeback_last_error')) {
                $table->text('writeback_last_error')->nullable()->after('writeback_attempted_count');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'writeback_queued_at')) {
                $table->timestamp('writeback_queued_at')->nullable()->after('writeback_last_error');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'writeback_started_at')) {
                $table->timestamp('writeback_started_at')->nullable()->after('writeback_queued_at');
            }

            if (! Schema::hasColumn('verification_job_metrics', 'writeback_finished_at')) {
                $table->timestamp('writeback_finished_at')->nullable()->after('writeback_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_job_metrics', function (Blueprint $table): void {
            $columns = [
                'writeback_finished_at',
                'writeback_started_at',
                'writeback_queued_at',
                'writeback_last_error',
                'writeback_attempted_count',
                'writeback_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('verification_job_metrics', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
