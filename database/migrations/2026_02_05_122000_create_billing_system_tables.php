<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('Unpaid'); // Unpaid, Paid, Cancelled, Refunded, Collections
            $table->datetime('date');
            $table->datetime('due_date');
            $table->datetime('paid_at')->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('currency')->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->bigInteger('amount');
            $table->string('type')->default('Order'); // Order, Credit, Adjustment
            $table->string('rel_type')->nullable(); // Model class
            $table->unsignedBigInteger('rel_id')->nullable(); // Model ID
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_id')->nullable(); // Gateway ID
            $table->string('payment_method')->nullable();
            $table->bigInteger('amount');
            $table->datetime('date');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'balance')) {
                $table->bigInteger('balance')->default(0)->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
