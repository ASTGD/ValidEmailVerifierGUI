<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('issue_key', 191)->index();
            $table->string('severity', 32)->index();
            $table->string('status', 32)->index();
            $table->string('lane', 64)->nullable()->index();
            $table->string('title', 255);
            $table->text('detail')->nullable();
            $table->timestamp('first_detected_at')->index();
            $table->timestamp('last_detected_at')->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->index();
            $table->timestamp('mitigated_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['issue_key', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_incidents');
    }
};
