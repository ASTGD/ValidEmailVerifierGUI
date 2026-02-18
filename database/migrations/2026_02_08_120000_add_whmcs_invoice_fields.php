<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'tax')) {
                $table->bigInteger('tax')->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('invoices', 'discount')) {
                $table->bigInteger('discount')->default(0)->after('tax');
            }
            if (!Schema::hasColumn('invoices', 'credit_applied')) {
                $table->bigInteger('credit_applied')->default(0)->after('total');
            }
            if (!Schema::hasColumn('invoices', 'balance_due')) {
                $table->bigInteger('balance_due')->default(0)->after('credit_applied');
            }
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('currency');
            }
        });

        Schema::table('credits', function (Blueprint $table) {
            if (!Schema::hasColumn('credits', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax', 'discount', 'credit_applied', 'balance_due', 'payment_method']);
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
        });
    }
};
