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
            $table->string('original_filename')->nullable()->change();
            $table->string('temp_disk', 64)->nullable()->change();
            $table->string('temp_key', 1024)->nullable()->change();
            $table->unsignedInteger('email_count')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->string('original_filename')->nullable(false)->change();
            $table->string('temp_disk', 64)->nullable(false)->change();
            $table->string('temp_key', 1024)->nullable(false)->change();
            $table->unsignedInteger('email_count')->nullable(false)->change();
        });
    }
};
