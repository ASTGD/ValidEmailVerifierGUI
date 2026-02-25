<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->foreignId('worker_pool_id')
                ->nullable()
                ->after('verifier_domain_id')
                ->constrained('engine_worker_pools')
                ->nullOnDelete();
        });

        $defaultPoolID = DB::table('engine_worker_pools')
            ->where('is_default', true)
            ->value('id');

        if ($defaultPoolID) {
            DB::table('engine_servers')->update([
                'worker_pool_id' => $defaultPoolID,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engine_servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('worker_pool_id');
        });
    }
};
