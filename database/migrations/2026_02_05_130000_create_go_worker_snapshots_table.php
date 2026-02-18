<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('go_worker_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('captured_at')->useCurrent();
            $table->unsignedInteger('total_workers')->default(0);
            $table->unsignedInteger('pool_count')->default(0);
            $table->unsignedInteger('desired_total')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('go_worker_snapshots');
    }
};
