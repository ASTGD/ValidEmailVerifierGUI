<?php

use App\Enums\VerificationMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->string('verification_mode')
                ->default(VerificationMode::Standard->value)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('verification_jobs', function (Blueprint $table) {
            $table->dropColumn('verification_mode');
        });
    }
};
