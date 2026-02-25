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
                $table->string('temp_disk', 64)->nullable()->change();
            }
            if (Schema::hasColumn('checkout_intents', 'temp_key')) {
                $table->string('temp_key', 1024)->nullable()->change();
            }
            if (Schema::hasColumn('checkout_intents', 'email_count')) {
                $table->unsignedInteger('email_count')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            if (Schema::hasColumn('checkout_intents', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('checkout_intents', 'original_filename')) {
                $table->string('original_filename')->nullable(false)->change();
            }
            if (Schema::hasColumn('checkout_intents', 'temp_disk')) {
                $table->string('temp_disk', 64)->nullable(false)->change();
            }
            if (Schema::hasColumn('checkout_intents', 'temp_key')) {
                $table->string('temp_key', 1024)->nullable(false)->change();
            }
            if (Schema::hasColumn('checkout_intents', 'email_count')) {
                $table->unsignedInteger('email_count')->nullable(false)->change();
            }
        });
    }
};
