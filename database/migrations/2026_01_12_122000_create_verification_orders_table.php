<?php

use App\Enums\VerificationOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('verification_job_id')->nullable()->constrained('verification_jobs')->nullOnDelete();
            $table->foreignUuid('checkout_intent_id')->nullable()->constrained('checkout_intents')->nullOnDelete();
            $table->foreignId('pricing_plan_id')->nullable()->constrained('pricing_plans')->nullOnDelete();
            $table->string('status')->default(VerificationOrderStatus::Pending->value);
            $table->string('original_filename');
            $table->unsignedInteger('email_count');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_orders');
    }
};
