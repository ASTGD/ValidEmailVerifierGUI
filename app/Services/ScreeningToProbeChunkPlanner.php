<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ScreeningToProbeChunkPlanner
{
    public function __construct(private JobStorage $storage) {}

    /**
     * @return array{candidate_count: int, hard_invalid_count: int, probe_chunk_id: string|null, probe_chunk_ids: array<int, string>, probe_chunk_count: int}
     */
    public function plan(VerificationJob $job, VerificationJobChunk $chunk, string $outputDisk): array
    {
        if ($outputDisk === '') {
            $outputDisk = $this->storage->disk();
        }

        $rows = [
            'valid' => $this->readResultRows($outputDisk, (string) $chunk->valid_key, 'valid'),
            'invalid' => $this->readResultRows($outputDisk, (string) $chunk->invalid_key, 'invalid'),
            'risky' => $this->readResultRows($outputDisk, (string) $chunk->risky_key, 'risky'),
        ];

        $hardInvalidReasons = $this->hardInvalidReasons();
        $hardInvalidRows = [];
        $candidateMap = [];

        foreach (['valid', 'invalid', 'risky'] as $status) {
            foreach ($rows[$status] as $row) {
                $email = $this->normalizeEmail($row['email'] ?? '');
                if ($email === null) {
                    continue;
                }

                $reason = strtolower(trim((string) ($row['reason'] ?? '')));
                $reasonBase = explode(':', $reason, 2)[0];

                if ($status === 'invalid' && in_array($reasonBase, $hardInvalidReasons, true)) {
                    $hardInvalidRows[] = [
                        'email' => $email,
                        'reason' => $reasonBase,
                    ];

                    continue;
                }

                if (! array_key_exists($email, $candidateMap)) {
                    [$provider, $domainHash] = $this->routingHintsForEmail($email);
                    $candidateMap[$email] = [
                        'provider' => $provider,
                        'domain_hash' => $domainHash,
                    ];
                }
            }
        }

        $candidateCount = count($candidateMap);
        if ($candidateCount === 0) {
            return [
                'candidate_count' => 0,
                'hard_invalid_count' => count($hardInvalidRows),
                'probe_chunk_id' => null,
                'probe_chunk_ids' => [],
                'probe_chunk_count' => 0,
            ];
        }

        $probeShards = $this->buildProbeShards($candidateMap);
        $probeChunkIds = $this->createProbeChunks($job, $chunk, $outputDisk, $probeShards);

        $this->rewriteScreeningOutputs($outputDisk, $chunk, $hardInvalidRows);

        return [
            'candidate_count' => $candidateCount,
            'hard_invalid_count' => count($hardInvalidRows),
            'probe_chunk_id' => $probeChunkIds[0] ?? null,
            'probe_chunk_ids' => $probeChunkIds,
            'probe_chunk_count' => count($probeChunkIds),
        ];
    }

    /**
     * @param  array<string, array{provider: string, domain_hash: string}>  $candidateMap
     * @return array<int, array{emails: array<int, string>, provider: string, domain_hash: string, preferred_pool: string|null}>
     */
    private function buildProbeShards(array $candidateMap): array
    {
        $shardingEnabled = (bool) config('engine.probe_sharding_enabled', true);
        $targetSize = max(1, (int) config('engine.probe_shard_target_size', 1000));
        $minSize = max(1, (int) config('engine.probe_shard_min_size', 200));
        $maxSize = max($minSize, (int) config('engine.probe_shard_max_size', 2000));
        $shardSize = min($maxSize, max($minSize, $targetSize));

        if (! $shardingEnabled) {
            $emails = array_keys($candidateMap);
            sort($emails);

            return [[
                'emails' => $emails,
                'provider' => 'generic',
                'domain_hash' => 'sha1:generic',
                'preferred_pool' => $this->preferredPoolForProvider('generic'),
            ]];
        }

        $buckets = [];
        foreach ($candidateMap as $email => $hint) {
            $provider = $this->normalizeProvider((string) ($hint['provider'] ?? 'generic'));
            $domainHash = (string) ($hint['domain_hash'] ?? 'sha1:generic');
            $bucketKey = $provider.'|'.$domainHash;

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'provider' => $provider,
                    'domain_hash' => $domainHash,
                    'emails' => [],
                ];
            }

            $buckets[$bucketKey]['emails'][] = $email;
        }

        ksort($buckets);

        $shards = [];
        foreach ($buckets as $bucket) {
            $emails = array_values(array_unique($bucket['emails']));
            sort($emails);

            $chunks = array_chunk($emails, $shardSize);
            if (count($chunks) > 1) {
                $last = array_pop($chunks);
                if (is_array($last) && count($last) < $minSize) {
                    $chunks[count($chunks) - 1] = array_merge($chunks[count($chunks) - 1], $last);
                } elseif (is_array($last)) {
                    $chunks[] = $last;
                }
            }

            foreach ($chunks as $chunkEmails) {
                $shards[] = [
                    'emails' => $chunkEmails,
                    'provider' => $bucket['provider'],
                    'domain_hash' => $bucket['domain_hash'],
                    'preferred_pool' => $this->preferredPoolForProvider($bucket['provider']),
                ];
            }
        }

        return $shards;
    }

    /**
     * @param  array<int, array{emails: array<int, string>, provider: string, domain_hash: string, preferred_pool: string|null}>  $shards
     * @return array<int, string>
     */
    private function createProbeChunks(
        VerificationJob $job,
        VerificationJobChunk $chunk,
        string $disk,
        array $shards
    ): array {
        $maxProbeAttempts = max(1, (int) config('engine.probe_max_attempts', 3));

        return DB::transaction(function () use ($job, $chunk, $disk, $shards, $maxProbeAttempts): array {
            VerificationJob::query()
                ->where('id', $job->id)
                ->lockForUpdate()
                ->first();

            $existingChunks = VerificationJobChunk::query()
                ->where('verification_job_id', $job->id)
                ->where('processing_stage', 'smtp_probe')
                ->where('source_stage', 'screening')
                ->where('parent_chunk_id', $chunk->id)
                ->orderBy('chunk_no')
                ->pluck('id')
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values()
                ->all();

            if ($existingChunks !== []) {
                return $existingChunks;
            }

            $nextChunkNo = (int) VerificationJobChunk::query()
                ->where('verification_job_id', $job->id)
                ->max('chunk_no');
            $nextChunkNo++;

            $rotationGroupId = (string) Str::uuid();
            $createdChunkIds = [];

            foreach ($shards as $shard) {
                $emails = $shard['emails'];
                if ($emails === []) {
                    continue;
                }

                $inputKey = $this->storage->chunkInputKey($job, $nextChunkNo, 'txt');
                $this->writeLines($disk, $inputKey, $emails);

                $probeChunk = VerificationJobChunk::create([
                    'verification_job_id' => $job->id,
                    'chunk_no' => $nextChunkNo,
                    'status' => 'pending',
                    'processing_stage' => 'smtp_probe',
                    'source_stage' => 'screening',
                    'parent_chunk_id' => $chunk->id,
                    'routing_provider' => $this->normalizeProvider((string) $shard['provider']),
                    'routing_domain' => (string) $shard['domain_hash'],
                    'preferred_pool' => $shard['preferred_pool'] ?: null,
                    'rotation_group_id' => $rotationGroupId,
                    'last_worker_ids' => [],
                    'max_probe_attempts' => $maxProbeAttempts,
                    'input_disk' => $disk,
                    'input_key' => $inputKey,
                    'email_count' => count($emails),
                ]);

                $createdChunkIds[] = (string) $probeChunk->id;
                $nextChunkNo++;
            }

            return $createdChunkIds;
        });
    }

    /**
     * @param  array<int, array{email: string, reason: string}>  $hardInvalidRows
     */
    private function rewriteScreeningOutputs(string $disk, VerificationJobChunk $chunk, array $hardInvalidRows): void
    {
        $this->writeRows($disk, (string) $chunk->valid_key, []);
        $this->writeRows($disk, (string) $chunk->risky_key, []);
        $this->writeRows($disk, (string) $chunk->invalid_key, $hardInvalidRows);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function routingHintsForEmail(string $email): array
    {
        $parts = explode('@', strtolower(trim($email)), 2);
        $domain = $parts[1] ?? '';
        $domain = trim($domain);

        if ($domain === '') {
            return ['generic', 'sha1:generic'];
        }

        return [
            $this->providerForDomain($domain),
            'sha1:'.sha1($domain),
        ];
    }

    private function providerForDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return 'generic';
        }

        if ($this->domainMatches($domain, ['gmail.com', 'googlemail.com'])) {
            return 'gmail';
        }

        if ($this->domainMatches($domain, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'office365.com'])) {
            return 'microsoft';
        }

        if ($this->domainMatches($domain, ['yahoo.com', 'ymail.com', 'rocketmail.com'])) {
            return 'yahoo';
        }

        return 'generic';
    }

    /**
     * @param  array<int, string>  $suffixes
     */
    private function domainMatches(string $domain, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            $suffix = strtolower(trim($suffix));
            if ($suffix === '') {
                continue;
            }

            if ($domain === $suffix || str_ends_with($domain, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return in_array($provider, ['gmail', 'microsoft', 'yahoo', 'generic'], true)
            ? $provider
            : 'generic';
    }

    private function preferredPoolForProvider(string $provider): ?string
    {
        $map = $this->preferredPoolMap();
        $provider = $this->normalizeProvider($provider);

        $pool = trim((string) ($map[$provider] ?? ''));

        return $pool === '' ? null : $pool;
    }

    /**
     * @return array<string, string>
     */
    private function preferredPoolMap(): array
    {
        $raw = config('engine.probe_preferred_pools', '');
        if (is_array($raw)) {
            $normalized = [];
            foreach ($raw as $provider => $pool) {
                $provider = $this->normalizeProvider((string) $provider);
                $pool = trim((string) $pool);
                if ($pool !== '') {
                    $normalized[$provider] = $pool;
                }
            }

            return $normalized;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return [];
        }

        $normalized = [];
        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || ! str_contains($entry, ':')) {
                continue;
            }

            [$provider, $pool] = array_pad(explode(':', $entry, 2), 2, '');
            $provider = $this->normalizeProvider($provider);
            $pool = trim($pool);

            if ($pool !== '') {
                $normalized[$provider] = $pool;
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{email: string, reason: string}>
     */
    private function readResultRows(string $disk, string $key, string $fallbackStatus): array
    {
        if ($key === '' || ! Storage::disk($disk)->exists($key)) {
            return [];
        }

        $stream = Storage::disk($disk)->readStream($key);
        if (! is_resource($stream)) {
            return [];
        }

        $rows = [];

        try {
            while (($line = fgets($stream)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $columns = str_getcsv($line);
                if ($columns === []) {
                    continue;
                }

                if ($this->isHeaderRow($columns)) {
                    continue;
                }

                $email = trim((string) ($columns[0] ?? ''));
                if ($email === '') {
                    continue;
                }

                $status = strtolower(trim((string) ($columns[1] ?? $fallbackStatus)));
                $reason = '';

                if (count($columns) >= 5 && in_array($status, ['valid', 'invalid', 'risky'], true)) {
                    $reason = trim((string) ($columns[4] ?? ''));
                } else {
                    $reason = trim((string) ($columns[1] ?? ''));
                    $status = $fallbackStatus;
                }

                $rows[] = [
                    'email' => $email,
                    'reason' => $reason,
                    'status' => $status,
                ];
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $columns
     */
    private function isHeaderRow(array $columns): bool
    {
        $firstColumn = strtolower(trim((string) ($columns[0] ?? '')));

        return $firstColumn === 'email';
    }

    /**
     * @param  array<int, array{email: string, reason: string}>  $rows
     */
    private function writeRows(string $disk, string $key, array $rows): void
    {
        if ($key === '') {
            throw new RuntimeException('Screening output key is missing for probe handoff rewrite.');
        }

        $stream = tmpfile();
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open temporary stream for screening output rewrite.');
        }

        try {
            fwrite($stream, "email,reason\n");
            foreach ($rows as $row) {
                fputcsv($stream, [$row['email'], $row['reason']]);
            }

            $this->writeStream($disk, $key, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function writeLines(string $disk, string $key, array $lines): void
    {
        $stream = tmpfile();
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open temporary stream for probe candidate shard.');
        }

        try {
            foreach ($lines as $line) {
                fwrite($stream, $line.PHP_EOL);
            }

            $this->writeStream($disk, $key, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeStream(string $disk, string $key, $stream): void
    {
        rewind($stream);
        $stored = Storage::disk($disk)->writeStream($key, $stream);
        if ($stored === false) {
            rewind($stream);
            $contents = stream_get_contents($stream);
            $fallbackStored = is_string($contents)
                ? Storage::disk($disk)->put($key, $contents)
                : false;

            if ($fallbackStored === false) {
                throw new RuntimeException(sprintf(
                    'Unable to persist probe handoff payload to storage key [%s] on disk [%s].',
                    $key,
                    $disk
                ));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function hardInvalidReasons(): array
    {
        $reasons = config('engine.screening_hard_invalid_reasons', ['syntax', 'mx_missing']);
        if (! is_array($reasons)) {
            return ['syntax', 'mx_missing'];
        }

        $normalized = [];
        foreach ($reasons as $reason) {
            $reason = strtolower(trim((string) $reason));
            if ($reason !== '') {
                $normalized[] = $reason;
            }
        }

        return $normalized === [] ? ['syntax', 'mx_missing'] : array_values(array_unique($normalized));
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
