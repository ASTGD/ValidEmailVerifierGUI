<?php

use App\Enums\VerificationJobStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $values = array_map(
            static fn (VerificationJobStatus $status) => $status->value,
            VerificationJobStatus::cases()
        );

        $enum = implode("','", $values);

        DB::statement(sprintf(
            "ALTER TABLE verification_jobs MODIFY status ENUM('%s') NOT NULL DEFAULT '%s'",
            $enum,
            VerificationJobStatus::Pending->value
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf(
            "ALTER TABLE verification_jobs MODIFY status VARCHAR(255) NOT NULL DEFAULT '%s'",
            VerificationJobStatus::Pending->value
        ));
    }
};
