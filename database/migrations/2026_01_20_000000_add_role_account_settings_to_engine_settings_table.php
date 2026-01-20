<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->string('role_accounts_behavior')->default('risky');
            $table->text('role_accounts_list')->nullable();
        });

        DB::table('engine_settings')->update([
            'role_accounts_behavior' => (string) config('engine.role_accounts_behavior', 'risky'),
            'role_accounts_list' => (string) config('engine.role_accounts_list', ''),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('engine_settings', function (Blueprint $table) {
            $table->dropColumn(['role_accounts_behavior', 'role_accounts_list']);
        });
    }
};
