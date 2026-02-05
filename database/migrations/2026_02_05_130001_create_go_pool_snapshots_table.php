<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('go_pool_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('captured_at')->useCurrent();
            $table->string('pool', 128);
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('desired')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pool', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('go_pool_snapshots');
    }
};
