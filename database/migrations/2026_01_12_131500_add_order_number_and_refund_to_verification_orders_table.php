<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('id');
            $table->timestamp('refunded_at')->nullable()->after('currency');
            $table->unique('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropColumn(['order_number', 'refunded_at']);
        });
    }
};
