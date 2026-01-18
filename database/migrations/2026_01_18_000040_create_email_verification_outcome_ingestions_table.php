<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_outcome_ingestions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('source')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_name')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->foreignId('import_id')->nullable()->constrained('email_verification_outcome_imports')->nullOnDelete();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_outcome_ingestions');
    }
};
