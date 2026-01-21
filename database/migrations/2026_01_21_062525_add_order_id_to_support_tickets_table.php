<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // We add the column as a UUID string.
            // We handle the relationship in the Model logic to avoid SQL compatibility errors.
            if (!Schema::hasColumn('support_tickets', 'verification_order_id')) {
                $table->uuid('verification_order_id')->nullable()->after('user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('verification_order_id');
        });
    }
};
