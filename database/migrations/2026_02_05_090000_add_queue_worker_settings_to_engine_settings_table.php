<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->string('queue_worker_name')->nullable()->after('horizon_enabled');
            $table->unsignedSmallInteger('queue_worker_processes')->nullable()->after('queue_worker_name');
            $table->unsignedSmallInteger('queue_worker_memory')->nullable()->after('queue_worker_processes');
            $table->unsignedInteger('queue_worker_timeout')->nullable()->after('queue_worker_memory');
            $table->unsignedSmallInteger('queue_worker_tries')->nullable()->after('queue_worker_timeout');
            $table->unsignedSmallInteger('queue_worker_sleep')->nullable()->after('queue_worker_tries');
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn([
                'queue_worker_name',
                'queue_worker_processes',
                'queue_worker_memory',
                'queue_worker_timeout',
                'queue_worker_tries',
                'queue_worker_sleep',
            ]);
        });
    }
};
