<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('verification_orders', 'legacy_uuid')) {
            Schema::table('verification_orders', function (Blueprint $table) {
                $table->string('legacy_uuid')->nullable()->after('id');
            });
        }

        DB::table('verification_orders')
            ->whereNull('legacy_uuid')
            ->update(['legacy_uuid' => DB::raw('id')]);

        DB::statement('ALTER TABLE verification_orders DROP PRIMARY KEY');
        DB::statement('ALTER TABLE verification_orders ADD COLUMN id_new BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        DB::statement('ALTER TABLE verification_orders DROP COLUMN id');
        DB::statement('ALTER TABLE verification_orders CHANGE COLUMN id_new id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('verification_orders', 'legacy_uuid')) {
            return;
        }

        DB::statement('ALTER TABLE verification_orders DROP PRIMARY KEY');
        DB::statement('ALTER TABLE verification_orders ADD COLUMN id_old CHAR(36) NULL FIRST');
        DB::statement('UPDATE verification_orders SET id_old = COALESCE(legacy_uuid, UUID())');
        DB::statement('ALTER TABLE verification_orders DROP COLUMN id');
        DB::statement('ALTER TABLE verification_orders CHANGE COLUMN id_old id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE verification_orders ADD PRIMARY KEY (id)');

        Schema::table('verification_orders', function (Blueprint $table) {
            $table->dropColumn('legacy_uuid');
        });
    }
};
