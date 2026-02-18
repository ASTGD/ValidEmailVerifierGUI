<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_recovery_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 64)->index();
            $table->string('strategy', 64)->index();
            $table->string('status', 32)->index();
            $table->string('lane', 64)->nullable()->index();
            $table->string('job_class', 191)->nullable()->index();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->boolean('dry_run')->default(false)->index();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('executed_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_recovery_actions');
    }
};
