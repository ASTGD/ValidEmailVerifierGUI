<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->string('helo_name')->nullable()->after('ip_address');
            $table->string('mail_from_address')->nullable()->after('helo_name');
            $table->string('identity_domain')->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->dropColumn(['helo_name', 'mail_from_address', 'identity_domain']);
        });
    }
};
