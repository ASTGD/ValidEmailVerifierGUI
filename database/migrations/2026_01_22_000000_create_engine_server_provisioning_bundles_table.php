<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_server_provisioning_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engine_server_id')->constrained()->cascadeOnDelete();
            $table->uuid('bundle_uuid')->unique();
            $table->string('env_key');
            $table->string('script_key');
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_server_provisioning_bundles');
    }
};
