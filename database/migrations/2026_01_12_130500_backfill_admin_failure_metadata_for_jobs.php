<?php

use App\Models\VerificationJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'verification_jobs';
        $logs = 'verification_job_logs';

        DB::table($table)
            ->whereNull('failure_source')
            ->where('status', 'failed')
            ->whereExists(function ($query) use ($logs, $table) {
                $query->select(DB::raw(1))
                    ->from($logs)
                    ->whereColumn("{$logs}.verification_job_id", "{$table}.id")
                    ->where("{$logs}.event", 'fraud');
            })
            ->update([
                'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                'failure_code' => 'fraud',
            ]);

        DB::table($table)
            ->whereNull('failure_source')
            ->where('status', 'failed')
            ->whereExists(function ($query) use ($logs, $table) {
                $query->select(DB::raw(1))
                    ->from($logs)
                    ->whereColumn("{$logs}.verification_job_id", "{$table}.id")
                    ->where("{$logs}.event", 'cancelled');
            })
            ->update([
                'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                'failure_code' => 'cancelled',
            ]);

        DB::table($table)
            ->whereNull('failure_source')
            ->where('status', 'failed')
            ->whereExists(function ($query) use ($logs, $table) {
                $query->select(DB::raw(1))
                    ->from($logs)
                    ->whereColumn("{$logs}.verification_job_id", "{$table}.id")
                    ->where("{$logs}.event", 'failed')
                    ->where("{$logs}.message", 'Job marked failed by admin.');
            })
            ->update([
                'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                'failure_code' => 'manual_fail',
            ]);
    }

    public function down(): void
    {
        DB::table('verification_jobs')
            ->where('failure_source', VerificationJob::FAILURE_SOURCE_ADMIN)
            ->update([
                'failure_source' => null,
                'failure_code' => null,
            ]);
    }
};
