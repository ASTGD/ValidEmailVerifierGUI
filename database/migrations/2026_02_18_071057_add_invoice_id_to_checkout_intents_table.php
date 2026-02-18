<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->nullable()->after('type')->comment('Linked invoice for invoice payment intents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }
};
