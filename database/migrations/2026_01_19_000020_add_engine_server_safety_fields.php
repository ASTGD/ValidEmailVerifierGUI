<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->boolean('drain_mode')->default(false)->after('is_active');
            $table->unsignedInteger('max_concurrency')->nullable()->after('drain_mode');
        });
    }

    public function down(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->dropColumn(['drain_mode', 'max_concurrency']);
        });
    }
};
