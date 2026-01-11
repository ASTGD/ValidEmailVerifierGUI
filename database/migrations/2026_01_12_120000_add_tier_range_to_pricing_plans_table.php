<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->unsignedInteger('min_emails')->nullable()->after('price_per_1000');
            $table->unsignedInteger('max_emails')->nullable()->after('min_emails');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn(['min_emails', 'max_emails']);
        });
    }
};
