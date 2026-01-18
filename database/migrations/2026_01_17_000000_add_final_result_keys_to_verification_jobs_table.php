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
            $table->string('valid_key', 1024)->nullable()->after('output_key');
            $table->string('invalid_key', 1024)->nullable()->after('valid_key');
            $table->string('risky_key', 1024)->nullable()->after('invalid_key');
            $table->string('cached_valid_key', 1024)->nullable()->after('risky_key');
            $table->string('cached_invalid_key', 1024)->nullable()->after('cached_valid_key');
            $table->string('cached_risky_key', 1024)->nullable()->after('cached_invalid_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'valid_key',
                'invalid_key',
                'risky_key',
                'cached_valid_key',
                'cached_invalid_key',
                'cached_risky_key',
            ]);
        });
    }
};
