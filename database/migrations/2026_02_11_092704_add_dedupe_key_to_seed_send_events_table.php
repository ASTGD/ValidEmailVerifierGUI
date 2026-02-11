<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seed_send_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('seed_send_events', 'dedupe_key')) {
                $table->char('dedupe_key', 64)->nullable()->after('provider_message_id');
                $table->unique('dedupe_key', 'seed_send_events_dedupe_key_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seed_send_events', function (Blueprint $table): void {
            if (Schema::hasColumn('seed_send_events', 'dedupe_key')) {
                $table->dropUnique('seed_send_events_dedupe_key_unique');
                $table->dropColumn('dedupe_key');
            }
        });
    }
};
