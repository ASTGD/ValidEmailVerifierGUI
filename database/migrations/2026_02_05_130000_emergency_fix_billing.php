<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Invoices
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('invoice_number')->unique();
                $table->string('status')->default('Unpaid');
                $table->datetime('date');
                $table->datetime('due_date');
                $table->datetime('paid_at')->nullable();
                $table->bigInteger('subtotal')->default(0);
                $table->bigInteger('total')->default(0);
                $table->string('currency')->default('USD');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Invoice Items
        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->string('description');
                $table->bigInteger('amount');
                $table->string('type')->default('Order');
                $table->string('rel_type')->nullable();
                $table->unsignedBigInteger('rel_id')->nullable();
                $table->timestamps();
            });
        }

        // 3. Transactions
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('transaction_id')->nullable();
                $table->string('payment_method')->nullable();
                $table->bigInteger('amount');
                $table->datetime('date');
                $table->timestamps();
            });
        }

        // 4. Users Balance
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'balance')) {
                $table->bigInteger('balance')->default(0)->after('email');
            }
        });

        // 5. Checkout Intents Credit Applied
        Schema::table('checkout_intents', function (Blueprint $table) {
            if (!Schema::hasColumn('checkout_intents', 'credit_applied')) {
                $table->integer('credit_applied')->default(0);
            }
        });
    }

    public function down(): void
    {
        // No down, this is a fix-forward migration
    }
};
