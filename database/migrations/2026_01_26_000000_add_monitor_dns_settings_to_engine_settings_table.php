<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->string('monitor_dns_mode', 16)->default('system')->after('monitor_rbl_list');
            $table->string('monitor_dns_server_ip', 64)->nullable()->after('monitor_dns_mode');
            $table->unsignedSmallInteger('monitor_dns_server_port')->default(53)->after('monitor_dns_server_ip');
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn(['monitor_dns_mode', 'monitor_dns_server_ip', 'monitor_dns_server_port']);
        });
    }
};
