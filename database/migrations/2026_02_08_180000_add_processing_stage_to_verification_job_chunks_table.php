<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_job_chunks', function (Blueprint $table): void {
            if (! Schema::hasColumn('verification_job_chunks', 'processing_stage')) {
                $table->string('processing_stage', 32)
                    ->default('screening')
                    ->after('status');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'parent_chunk_id')) {
                $table->uuid('parent_chunk_id')
                    ->nullable()
                    ->after('processing_stage');
            }

            if (! Schema::hasColumn('verification_job_chunks', 'source_stage')) {
                $table->string('source_stage', 32)
                    ->nullable()
                    ->after('parent_chunk_id');
            }

            $table->index(['processing_stage', 'status'], 'verification_job_chunks_stage_status_idx');
            $table->index('parent_chunk_id', 'verification_job_chunks_parent_chunk_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('verification_job_chunks', function (Blueprint $table): void {
            if (Schema::hasColumn('verification_job_chunks', 'processing_stage')) {
                $table->dropIndex('verification_job_chunks_stage_status_idx');
            }

            if (Schema::hasColumn('verification_job_chunks', 'parent_chunk_id')) {
                $table->dropIndex('verification_job_chunks_parent_chunk_id_idx');
            }

            $columns = ['source_stage', 'parent_chunk_id', 'processing_stage'];
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
