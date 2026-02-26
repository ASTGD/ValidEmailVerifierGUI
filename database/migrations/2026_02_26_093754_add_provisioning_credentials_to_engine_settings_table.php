<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('engine_settings', 'provisioning_ghcr_username')) {
                $table->string('provisioning_ghcr_username')->nullable();
            }

            if (! Schema::hasColumn('engine_settings', 'provisioning_ghcr_token')) {
                $table->text('provisioning_ghcr_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            if (Schema::hasColumn('engine_settings', 'provisioning_ghcr_token')) {
                $table->dropColumn('provisioning_ghcr_token');
            }
            if (Schema::hasColumn('engine_settings', 'provisioning_ghcr_username')) {
                $table->dropColumn('provisioning_ghcr_username');
            }
        });
    }
};
