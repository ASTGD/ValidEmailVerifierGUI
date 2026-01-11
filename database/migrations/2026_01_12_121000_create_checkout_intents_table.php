<?php

use App\Enums\CheckoutIntentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(CheckoutIntentStatus::Pending->value);
            $table->string('original_filename');
            $table->string('temp_disk', 64);
            $table->string('temp_key', 1024);
            $table->unsignedInteger('email_count');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->foreignId('pricing_plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};
