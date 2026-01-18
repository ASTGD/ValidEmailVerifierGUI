<?php

namespace App\Jobs;

use App\Models\EmailVerificationOutcomeImport;
use App\Models\EmailVerificationOutcomeIngestion;
use App\Services\EmailVerificationOutcomes\OutcomeIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportEmailVerificationOutcomesFromCsv implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK_SIZE = 500;

    public function __construct(public int $importId)
    {
    }

    public function handle(OutcomeIngestor $ingestor): void
    {
        $import = EmailVerificationOutcomeImport::query()->find($this->importId);

        if (! $import) {
            return;
        }

        $import->update([
            'status' => EmailVerificationOutcomeImport::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $disk = $import->file_disk;
        $key = $import->file_key;

        try {
            $stream = Storage::disk($disk)->readStream($key);

            if (! is_resource($stream)) {
                throw new \RuntimeException('Unable to read import file stream.');
            }

            $header = fgetcsv($stream);
            $columns = $this->mapColumns($header ?: []);

            if (! isset($columns['email']) || ! isset($columns['outcome'])) {
                throw new \RuntimeException('CSV must include email and outcome columns.');
            }

            $defaultObservedAt = $import->created_at ?? now();
            $items = [];
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $itemCount = 0;

            while (($row = fgetcsv($stream)) !== false) {
                $item = $this->rowToItem($row, $columns);

                if ($item === null) {
                    continue;
                }

                $itemCount++;
                $items[] = $item;

                if (count($items) >= self::CHUNK_SIZE) {
                    $result = $ingestor->ingest($items, $import->source, $defaultObservedAt, $import->user_id);
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];
                    $errors = $this->mergeErrors($errors, $result['errors']);
                    $items = [];
                }
            }

            if ($items !== []) {
                $result = $ingestor->ingest($items, $import->source, $defaultObservedAt, $import->user_id);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $errors = $this->mergeErrors($errors, $result['errors']);
            }

            fclose($stream);

            $import->update([
                'status' => EmailVerificationOutcomeImport::STATUS_COMPLETED,
                'imported_count' => $imported,
                'skipped_count' => $skipped,
                'error_sample' => $errors ?: null,
                'finished_at' => now(),
            ]);

            EmailVerificationOutcomeIngestion::create([
                'type' => EmailVerificationOutcomeIngestion::TYPE_IMPORT,
                'source' => $import->source,
                'item_count' => $itemCount,
                'imported_count' => $imported,
                'skipped_count' => $skipped,
                'error_count' => $skipped,
                'user_id' => $import->user_id,
                'import_id' => $import->id,
            ]);
        } catch (Throwable $exception) {
            $import->update([
                'status' => EmailVerificationOutcomeImport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            EmailVerificationOutcomeIngestion::create([
                'type' => EmailVerificationOutcomeIngestion::TYPE_IMPORT,
                'source' => $import->source,
                'item_count' => 0,
                'imported_count' => 0,
                'skipped_count' => 0,
                'error_count' => 1,
                'user_id' => $import->user_id,
                'import_id' => $import->id,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $value) {
            $key = strtolower(trim((string) $value));
            $key = str_replace(' ', '_', $key);

            if ($key === '') {
                continue;
            }

            if ($key === 'reason') {
                $key = 'reason_code';
            }

            $columns[$key] = $index;
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>|null
     */
    private function rowToItem(array $row, array $columns): ?array
    {
        $emailIndex = $columns['email'] ?? null;
        $outcomeIndex = $columns['outcome'] ?? null;

        if ($emailIndex === null || $outcomeIndex === null) {
            return null;
        }

        $email = trim((string) ($row[$emailIndex] ?? ''));
        if ($email === '') {
            return null;
        }

        return [
            'email' => $email,
            'outcome' => $row[$outcomeIndex] ?? null,
            'reason_code' => $this->getValue($row, $columns, 'reason_code'),
            'observed_at' => $this->getValue($row, $columns, 'observed_at'),
            'source' => $this->getValue($row, $columns, 'source'),
        ];
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $columns
     */
    private function getValue(array $row, array $columns, string $key): ?string
    {
        $index = $columns[$key] ?? null;
        if ($index === null) {
            return null;
        }

        $value = trim((string) ($row[$index] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $existing
     * @param  array<int, string>  $incoming
     * @return array<int, string>
     */
    private function mergeErrors(array $existing, array $incoming): array
    {
        $merged = $existing;

        foreach ($incoming as $error) {
            if (count($merged) >= 10) {
                break;
            }
            $merged[] = $error;
        }

        return $merged;
    }
}
