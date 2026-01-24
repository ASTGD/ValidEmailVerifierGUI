<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->string('origin')->default('list_upload')->after('verification_mode');
            $table->string('subject_email')->nullable()->after('original_filename');
            $table->string('single_result_status')->nullable()->after('cached_count');
            $table->string('single_result_sub_status')->nullable()->after('single_result_status');
            $table->integer('single_result_score')->nullable()->after('single_result_sub_status');
            $table->string('single_result_reason')->nullable()->after('single_result_score');
            $table->timestamp('single_result_verified_at')->nullable()->after('single_result_reason');
        });
    }

    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'origin',
                'subject_email',
                'single_result_status',
                'single_result_sub_status',
                'single_result_score',
                'single_result_reason',
                'single_result_verified_at',
            ]);
        });
    }
};
