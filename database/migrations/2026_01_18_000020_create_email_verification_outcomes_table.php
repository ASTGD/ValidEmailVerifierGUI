<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_outcomes', function (Blueprint $table) {
            $table->id();
            $table->char('email_hash', 64)->index();
            $table->string('email_normalized')->nullable();
            $table->string('outcome');
            $table->string('reason_code')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('observed_at')->index();
            $table->string('source')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['email_hash', 'outcome', 'observed_at'], 'email_outcome_observed_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_outcomes');
    }
};
