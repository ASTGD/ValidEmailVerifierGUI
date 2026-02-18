<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smtp_policy_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('smtp_policy_versions', 'validation_status')) {
                $table->string('validation_status', 32)->default('pending')->after('status');
                $table->index('validation_status', 'smtp_policy_versions_validation_status_idx');
            }

            if (! Schema::hasColumn('smtp_policy_versions', 'validation_errors')) {
                $table->longText('validation_errors')->nullable()->after('validation_status');
            }

            if (! Schema::hasColumn('smtp_policy_versions', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validation_errors');
            }

            if (! Schema::hasColumn('smtp_policy_versions', 'validated_by')) {
                $table->string('validated_by', 128)->nullable()->after('validated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('smtp_policy_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('smtp_policy_versions', 'validation_status')) {
                $table->dropIndex('smtp_policy_versions_validation_status_idx');
            }

            $columns = [
                'validated_by',
                'validated_at',
                'validation_errors',
                'validation_status',
            ];

            $dropColumns = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn('smtp_policy_versions', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
