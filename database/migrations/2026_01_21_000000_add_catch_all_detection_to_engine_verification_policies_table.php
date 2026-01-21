<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engine_verification_policies', function (Blueprint $table) {
            $table->boolean('catch_all_detection_enabled')->default(false);
        });

        $defaults = config('engine.policy_defaults', []);

        foreach (['standard', 'enhanced'] as $mode) {
            $value = (bool) data_get($defaults, $mode.'.catch_all_detection_enabled', false);

            DB::table('engine_verification_policies')
                ->where('mode', $mode)
                ->update([
                    'catch_all_detection_enabled' => $value,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('engine_verification_policies', function (Blueprint $table) {
            $table->dropColumn('catch_all_detection_enabled');
        });
    }
};
