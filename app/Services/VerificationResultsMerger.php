<?php

namespace App\Services;

use App\Contracts\EmailVerificationCacheStore;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Support\EmailHashing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VerificationResultsMerger
{
    public function __construct(
        private JobStorage $storage,
        private EmailVerificationCacheStore $cacheStore,
        private VerificationOutputMapper $outputMapper
    )
    {
    }

    /**
     * @param Collection<int, VerificationJobChunk> $chunks
     * @return array{disk: string, keys: array<string, string>, counts: array<string, int>, missing: array<int, array{disk: string, key: string}>}
     */
    public function merge(VerificationJob $job, Collection $chunks, ?string $outputDisk = null): array
    {
        $outputDisk = $outputDisk ?: ($job->output_disk ?: ($job->input_disk ?: $this->storage->disk()));
        $missing = [];

        $writers = $this->initializeWriters($job);
        $batchSize = max(1, (int) config('engine.cache_batch_size', 100));

        foreach (['valid', 'invalid', 'risky'] as $type) {
            $sources = $this->collectSources($job, $chunks, $type, $outputDisk);

            foreach ($sources as $source) {
                $this->processSource($source, $type, $writers, $missing, $batchSize);
            }
        }

        $keys = [];
        $counts = [];

        foreach ($writers as $type => $writer) {
            $stream = $writer['stream'] ?? null;

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to finalize merge stream.');
            }

            $targetKey = $writer['key'];

            rewind($stream);
            Storage::disk($outputDisk)->put($targetKey, $stream);
            fclose($stream);

            $keys[$type] = $targetKey;
            $counts[$type] = $writer['count'] ?? 0;
        }

        return [
            'disk' => $outputDisk,
            'keys' => $keys,
            'counts' => $counts,
            'missing' => $missing,
        ];
    }

    /**
     * @param Collection<int, VerificationJobChunk> $chunks
     * @return array<int, array{disk: string, key: string}>
     */
    private function collectSources(VerificationJob $job, Collection $chunks, string $type, string $fallbackDisk): array
    {
        $sources = [];
        $cachedKey = $job->{'cached_'.$type.'_key'} ?? null;

        if ($cachedKey) {
            $sources[] = [
                'disk' => $job->input_disk ?: $fallbackDisk,
                'key' => $cachedKey,
            ];
        }

        foreach ($chunks as $chunk) {
            $key = $chunk->{$type.'_key'} ?? null;

            if (! $key) {
                continue;
            }

            $sources[] = [
                'disk' => $chunk->output_disk ?: ($chunk->input_disk ?: $fallbackDisk),
                'key' => $key,
            ];
        }

        return $sources;
    }

    /**
     * @param array<int, array{disk: string, key: string}> $missing
     */
    private function processSource(
        array $source,
        string $sourceStatus,
        array &$writers,
        array &$missing,
        int $batchSize
    ): void {
        if (! Storage::disk($source['disk'])->exists($source['key'])) {
            $missing[] = $source;

            return;
        }

        $stream = Storage::disk($source['disk'])->readStream($source['key']);

        if (! is_resource($stream)) {
            $missing[] = $source;

            return;
        }

        $buffer = [];

        while (($line = fgets($stream)) !== false) {
            $normalized = $this->normalizeLine($line);

            if ($normalized === '' || $this->isHeaderLine($normalized)) {
                continue;
            }

            $parsed = $this->parseRow($normalized);

            if (! $parsed) {
                continue;
            }

            $buffer[] = [
                'email' => $parsed['email'],
                'reason' => $parsed['reason'],
                'status' => $sourceStatus,
            ];

            if (count($buffer) >= $batchSize) {
                $this->flushBuffer($buffer, $writers);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->flushBuffer($buffer, $writers);
        }

        fclose($stream);
    }

    /**
     * @param array<int, array{email: string, reason: string, status: string}> $buffer
     * @param array<string, array{stream: resource, key: string, count: int}> $writers
     */
    private function flushBuffer(array $buffer, array &$writers): void
    {
        $emails = array_column($buffer, 'email');
        $cacheHits = $this->cacheStore->lookupMany($emails);

        foreach ($buffer as $row) {
            $normalized = EmailHashing::normalizeEmail($row['email']);
            $cacheOutcome = $normalized !== '' && isset($cacheHits[$normalized]) && is_array($cacheHits[$normalized])
                ? $cacheHits[$normalized]
                : null;

            $output = $this->outputMapper->map($row['email'], $row['status'], $row['reason'], $cacheOutcome);
            $status = $output['status'];

            if (! isset($writers[$status])) {
                $status = 'risky';
            }

            fputcsv($writers[$status]['stream'], [
                $output['email'],
                $output['status'],
                $output['sub_status'],
                $output['score'],
                $output['reason'],
            ]);

            $writers[$status]['count']++;
        }
    }

    private function normalizeLine(string $line): string
    {
        return rtrim($line, "\r\n");
    }

    private function isHeaderLine(string $line): bool
    {
        return ! str_contains($line, '@');
    }

    /**
     * @return array{email: string, reason: string}|null
     */
    private function parseRow(string $line): ?array
    {
        $columns = str_getcsv($line);

        if ($columns === []) {
            return null;
        }

        $email = trim((string) ($columns[0] ?? ''));

        if ($email === '') {
            return null;
        }

        if (count($columns) >= 5 && in_array(strtolower((string) $columns[1]), ['valid', 'invalid', 'risky'], true)) {
            return [
                'email' => $email,
                'reason' => trim((string) ($columns[4] ?? '')),
            ];
        }

        return [
            'email' => $email,
            'reason' => trim((string) ($columns[1] ?? '')),
        ];
    }

    /**
     * @return array<string, array{stream: resource, key: string, count: int}>
     */
    private function initializeWriters(VerificationJob $job): array
    {
        $writers = [];
        $header = ['email', 'status', 'sub_status', 'score', 'reason'];

        foreach (['valid', 'invalid', 'risky'] as $type) {
            $stream = tmpfile();

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to create temporary merge stream.');
            }

            fputcsv($stream, $header);

            $writers[$type] = [
                'stream' => $stream,
                'key' => $this->storage->finalResultKey($job, $type),
                'count' => 0,
            ];
        }

        return $writers;
    }
}
