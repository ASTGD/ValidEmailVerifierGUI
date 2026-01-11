<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('stripe_price_id')->nullable();
            $table->string('billing_interval')->nullable();
            $table->decimal('price_per_email', 10, 4)->nullable();
            $table->decimal('price_per_1000', 10, 2)->nullable();
            $table->unsignedInteger('credits_per_month')->nullable();
            $table->unsignedInteger('max_file_size_mb')->nullable();
            $table->unsignedInteger('concurrency_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};
