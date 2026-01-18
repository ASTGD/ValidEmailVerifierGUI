<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->unsignedInteger('cached_count')->nullable()->after('unknown_count');
            $table->timestamp('prepared_at')->nullable()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropColumn(['cached_count', 'prepared_at']);
        });
    }
};
