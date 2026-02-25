<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            if (! Schema::hasColumn('checkout_intents', 'type')) {
                $table->string('type')->default('order')->after('status');
            }

            if (Schema::hasColumn('checkout_intents', 'original_filename')) {
                $table->string('original_filename')->nullable()->change();
            }
            if (Schema::hasColumn('checkout_intents', 'temp_disk')) {
                $table->string('temp_disk')->nullable()->change();
            }
            if (Schema::hasColumn('checkout_intents', 'temp_key')) {
                $table->string('temp_key')->nullable()->change();
            }
            if (Schema::hasColumn('checkout_intents', 'email_count')) {
                $table->integer('email_count')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('checkout_intents', 'type')) {
            Schema::table('checkout_intents', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
