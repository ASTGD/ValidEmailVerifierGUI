<?php

namespace App\Services\EmailVerificationOutcomes;

use App\Models\EmailVerificationOutcome;
use App\Support\EmailHashing;
use Illuminate\Support\Carbon;

class OutcomeIngestor
{
    private const BATCH_SIZE = 500;
    private const ERROR_SAMPLE_LIMIT = 10;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function ingest(array $items, string $defaultSource, Carbon $defaultObservedAt, ?int $userId = null): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rows = [];
        $now = now();

        foreach ($items as $item) {
            $email = trim((string) ($item['email'] ?? ''));
            $outcome = strtolower(trim((string) ($item['outcome'] ?? '')));
            $reason = trim((string) ($item['reason_code'] ?? ''));
            $source = trim((string) ($item['source'] ?? '')) ?: $defaultSource;
            $observedAt = $item['observed_at'] ?? null;

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $this->addError($errors, "Invalid email: {$email}");
                continue;
            }

            if (! in_array($outcome, ['valid', 'invalid', 'risky'], true)) {
                $skipped++;
                $this->addError($errors, "Invalid outcome for {$email}: {$outcome}");
                continue;
            }

            $parsedObservedAt = $defaultObservedAt;
            if ($observedAt) {
                try {
                    $parsedObservedAt = Carbon::parse($observedAt);
                } catch (\Throwable $exception) {
                    $skipped++;
                    $this->addError($errors, "Invalid observed_at for {$email}: {$observedAt}");
                    continue;
                }
            }

            $normalized = EmailHashing::normalizeEmail($email);
            $rows[] = [
                'email_hash' => EmailHashing::hashEmail($normalized),
                'email_normalized' => $normalized,
                'outcome' => $outcome,
                'reason_code' => $reason !== '' ? $reason : null,
                'details' => $item['details'] ?? null,
                'observed_at' => $parsedObservedAt,
                'source' => $source !== '' ? $source : null,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= self::BATCH_SIZE) {
                $imported += $this->flushRows($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $imported += $this->flushRows($rows);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function addError(array &$errors, string $message): void
    {
        if (count($errors) >= self::ERROR_SAMPLE_LIMIT) {
            return;
        }

        $errors[] = $message;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function flushRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        EmailVerificationOutcome::upsert(
            $rows,
            ['email_hash', 'outcome', 'observed_at'],
            ['reason_code', 'details', 'source', 'user_id', 'email_normalized', 'updated_at']
        );

        return count($rows);
    }
}
