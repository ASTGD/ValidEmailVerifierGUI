<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('verification_jobs', 'cache_miss_key')) {
                $table->string('cache_miss_key', 1024)->nullable()->after('cached_risky_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('verification_jobs', 'cache_miss_key')) {
                $table->dropColumn('cache_miss_key');
            }
        });
    }
};
