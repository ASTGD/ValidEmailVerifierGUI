<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->string('input_disk')->nullable()->after('original_filename');
            $table->string('input_key')->nullable()->after('input_disk');
        });
    }

    public function down(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->dropColumn(['input_disk', 'input_key']);
        });
    }
};
