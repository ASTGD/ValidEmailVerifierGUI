<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'ticket_number')) {
                $table->string('ticket_number')->nullable()->unique()->after('user_id');
            }

            if (! Schema::hasColumn('support_tickets', 'category')) {
                $table->string('category')->default('General')->after('subject');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'ticket_number')) {
                $table->dropUnique(['ticket_number']);
                $table->dropColumn('ticket_number');
            }

            if (Schema::hasColumn('support_tickets', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
