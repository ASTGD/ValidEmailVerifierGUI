<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->string('environment')->nullable();
            $table->string('region')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('ip_address');
            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_servers');
    }
};
