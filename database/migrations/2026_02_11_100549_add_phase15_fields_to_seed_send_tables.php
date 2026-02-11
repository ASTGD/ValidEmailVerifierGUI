<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seed_send_consents', function (Blueprint $table): void {
            if (! Schema::hasColumn('seed_send_consents', 'consent_text_snapshot')) {
                $table->text('consent_text_snapshot')->nullable()->after('consent_text_version');
            }

            if (! Schema::hasColumn('seed_send_consents', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('consented_at');
            }

            if (! Schema::hasColumn('seed_send_consents', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('seed_send_consents', 'revoked_by_admin_id')) {
                $table->foreignId('revoked_by_admin_id')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('seed_send_consents', 'revocation_reason')) {
                $table->text('revocation_reason')->nullable()->after('rejection_reason');
            }

            $table->index(['status', 'expires_at'], 'seed_send_consents_status_expires_idx');
        });

        Schema::table('seed_send_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('seed_send_campaigns', 'report_disk')) {
                $table->string('report_disk', 64)->nullable()->after('provider_campaign_ref');
            }

            if (! Schema::hasColumn('seed_send_campaigns', 'report_key')) {
                $table->string('report_key', 512)->nullable()->after('report_disk');
            }

            $table->index(['status', 'updated_at'], 'seed_send_campaigns_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('seed_send_campaigns', function (Blueprint $table): void {
            $table->dropIndex('seed_send_campaigns_status_updated_idx');

            if (Schema::hasColumn('seed_send_campaigns', 'report_key')) {
                $table->dropColumn('report_key');
            }

            if (Schema::hasColumn('seed_send_campaigns', 'report_disk')) {
                $table->dropColumn('report_disk');
            }
        });

        Schema::table('seed_send_consents', function (Blueprint $table): void {
            $table->dropIndex('seed_send_consents_status_expires_idx');

            if (Schema::hasColumn('seed_send_consents', 'revocation_reason')) {
                $table->dropColumn('revocation_reason');
            }

            if (Schema::hasColumn('seed_send_consents', 'revoked_by_admin_id')) {
                $table->dropConstrainedForeignId('revoked_by_admin_id');
            }

            if (Schema::hasColumn('seed_send_consents', 'revoked_at')) {
                $table->dropColumn('revoked_at');
            }

            if (Schema::hasColumn('seed_send_consents', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('seed_send_consents', 'consent_text_snapshot')) {
                $table->dropColumn('consent_text_snapshot');
            }
        });
    }
};
