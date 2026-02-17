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
            $table->string('type')->default('order')->after('status');

            // Make order-specific fields nullable
            $table->string('original_filename')->nullable()->change();
            $table->string('temp_disk')->nullable()->change();
            $table->string('temp_key')->nullable()->change();
            $table->integer('email_count')->nullable()->change();
            $table->uuid('pricing_plan_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->dropColumn('type');

            // Revert fields to non-nullable (careful if we have data)
            // For now, only dropping the type column
        });
    }
};
