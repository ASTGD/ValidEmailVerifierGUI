<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->string('failure_source')->nullable()->after('error_message');
            $table->string('failure_code')->nullable()->after('failure_source');
            $table->index('failure_source');
        });
    }

    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropIndex(['failure_source']);
            $table->dropColumn(['failure_source', 'failure_code']);
        });
    }
};
