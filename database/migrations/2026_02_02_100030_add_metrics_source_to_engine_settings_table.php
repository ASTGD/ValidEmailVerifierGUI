<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('engine_settings', 'metrics_source')) {
                $table->string('metrics_source', 16)->default('container')->after('monitor_dns_server_port');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            if (Schema::hasColumn('engine_settings', 'metrics_source')) {
                $table->dropColumn('metrics_source');
            }
        });
    }
};
