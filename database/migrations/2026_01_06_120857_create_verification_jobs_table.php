<?php

use App\Enums\VerificationJobStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('verification_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(VerificationJobStatus::Pending->value);
            $table->string('original_filename');
            $table->string('input_disk', 64)->nullable();
            $table->string('input_key', 1024);
            $table->string('output_disk', 64)->nullable();
            $table->string('output_key', 1024)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_emails')->nullable();
            $table->unsignedInteger('valid_count')->nullable();
            $table->unsignedInteger('invalid_count')->nullable();
            $table->unsignedInteger('risky_count')->nullable();
            $table->unsignedInteger('unknown_count')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_jobs');
    }
};
