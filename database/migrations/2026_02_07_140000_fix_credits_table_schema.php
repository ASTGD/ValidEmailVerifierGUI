<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('credits')) {
            Schema::create('credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->bigInteger('amount'); // in cents, can be negative
                $table->string('description')->nullable();
                $table->string('type')->default('manual'); // manual, purchase, usage
                $table->timestamps();
            });
        } else {
            Schema::table('credits', function (Blueprint $table) {
                if (!Schema::hasColumn('credits', 'user_id')) {
                    $table->foreignId('user_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
                }
                if (!Schema::hasColumn('credits', 'amount')) {
                    $table->bigInteger('amount')->after('user_id')->default(0);
                }
                if (!Schema::hasColumn('credits', 'description')) {
                    $table->string('description')->after('amount')->nullable();
                }
                if (!Schema::hasColumn('credits', 'type')) {
                    $table->string('type')->after('description')->default('manual');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
