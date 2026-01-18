<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VerificationResultsMerger
{
    public function __construct(private JobStorage $storage)
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
        $keys = [];
        $counts = [];

        foreach (['valid', 'invalid', 'risky'] as $type) {
            $sources = $this->collectSources($job, $chunks, $type, $outputDisk);
            $headerLine = $this->detectHeaderLine($sources);
            $targetKey = $this->storage->finalResultKey($job, $type);

            $counts[$type] = $this->mergeSources(
                $sources,
                $outputDisk,
                $targetKey,
                $headerLine,
                $missing
            );

            $keys[$type] = $targetKey;
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
     * @param array<int, array{disk: string, key: string}> $sources
     */
    private function detectHeaderLine(array $sources): ?string
    {
        foreach ($sources as $source) {
            $stream = Storage::disk($source['disk'])->readStream($source['key']);

            if (! is_resource($stream)) {
                continue;
            }

            $line = $this->firstNonEmptyLine($stream);
            fclose($stream);

            if ($line !== null && $this->isHeaderLine($line)) {
                return $this->normalizeLine($line);
            }
        }

        return null;
    }

    /**
     * @param array<int, array{disk: string, key: string}> $sources
     * @param array<int, array{disk: string, key: string}> $missing
     */
    private function mergeSources(
        array $sources,
        string $outputDisk,
        string $targetKey,
        ?string $headerLine,
        array &$missing
    ): int {
        $temp = tmpfile();

        if (! is_resource($temp)) {
            throw new RuntimeException('Unable to create temporary merge stream.');
        }

        $count = 0;

        if ($headerLine) {
            fwrite($temp, $headerLine.PHP_EOL);
        }

        foreach ($sources as $source) {
            if (! Storage::disk($source['disk'])->exists($source['key'])) {
                $missing[] = $source;
                continue;
            }

            $stream = Storage::disk($source['disk'])->readStream($source['key']);

            if (! is_resource($stream)) {
                $missing[] = $source;
                continue;
            }

            $firstLine = null;

            while (($line = fgets($stream)) !== false) {
                $normalized = $this->normalizeLine($line);

                if ($normalized === '') {
                    continue;
                }

                $firstLine = $normalized;
                break;
            }

            if ($firstLine !== null) {
                $isHeader = $this->isHeaderLine($firstLine);

                if (! $isHeader) {
                    fwrite($temp, $firstLine.PHP_EOL);
                    $count++;
                }
            }

            while (($line = fgets($stream)) !== false) {
                $normalized = $this->normalizeLine($line);

                if ($normalized === '') {
                    continue;
                }

                fwrite($temp, $normalized.PHP_EOL);
                $count++;
            }

            fclose($stream);
        }

        rewind($temp);
        Storage::disk($outputDisk)->put($targetKey, $temp);
        fclose($temp);

        return $count;
    }

    private function firstNonEmptyLine($stream): ?string
    {
        while (($line = fgets($stream)) !== false) {
            $normalized = $this->normalizeLine($line);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeLine(string $line): string
    {
        return rtrim($line, "\r\n");
    }

    private function isHeaderLine(string $line): bool
    {
        return ! str_contains($line, '@');
    }
}
