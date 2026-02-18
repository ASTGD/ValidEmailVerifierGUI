<?php

namespace App\Services\SmtpDecisionTracing;

use App\Models\SmtpDecisionTrace;
use App\Models\VerificationJobChunk;
use App\Support\EmailHashing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SmtpDecisionTraceRecorder
{
    private const UPSERT_BATCH_SIZE = 500;

    public function recordFromChunk(VerificationJobChunk $chunk): int
    {
        if (strtolower((string) $chunk->processing_stage) !== 'smtp_probe') {
            return 0;
        }

        if ($chunk->status !== 'completed') {
            return 0;
        }

        $outputDisk = (string) ($chunk->output_disk ?: $chunk->input_disk);
        if ($outputDisk === '') {
            return 0;
        }

        $rows = [];
        $rows = array_merge($rows, $this->rowsFromOutput($chunk, $outputDisk, (string) $chunk->invalid_key, 'invalid'));
        $rows = array_merge($rows, $this->rowsFromOutput($chunk, $outputDisk, (string) $chunk->risky_key, 'risky'));

        if ($rows === []) {
            return 0;
        }

        $chunks = array_chunk($rows, self::UPSERT_BATCH_SIZE);
        foreach ($chunks as $batch) {
            SmtpDecisionTrace::query()->upsert(
                $batch,
                ['verification_job_chunk_id', 'email_hash', 'decision_class'],
                [
                    'verification_job_id',
                    'provider',
                    'policy_version',
                    'matched_rule_id',
                    'smtp_code',
                    'enhanced_code',
                    'retry_strategy',
                    'reason_tag',
                    'confidence_hint',
                    'session_strategy_id',
                    'attempt_route',
                    'trace_payload',
                    'observed_at',
                    'updated_at',
                ]
            );
        }

        return count($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFromOutput(VerificationJobChunk $chunk, string $disk, string $key, string $bucket): array
    {
        $key = trim($key);
        if ($key === '') {
            return [];
        }

        $stream = Storage::disk($disk)->readStream($key);
        if (! is_resource($stream)) {
            return [];
        }

        $rows = [];
        $now = now();
        $defaultProvider = $this->normalizeProvider((string) ($chunk->routing_provider ?? ''));
        $lastWorkerIds = is_array($chunk->last_worker_ids) ? $chunk->last_worker_ids : [];
        $lastWorkerId = trim((string) end($lastWorkerIds));

        while (($columns = fgetcsv($stream)) !== false) {
            $email = trim((string) ($columns[0] ?? ''));
            $reason = trim((string) ($columns[1] ?? ''));

            if ($this->isHeaderRow($email, $reason)) {
                continue;
            }

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalizedEmail = EmailHashing::normalizeEmail($email);
            $emailHash = EmailHashing::hashEmail($normalizedEmail);
            $parsed = $this->parseReasonEvidence($reason);
            $decisionClass = $this->resolveDecisionClass(
                (string) ($parsed['decision'] ?? ''),
                $bucket
            );
            $confidence = $this->normalizeConfidence(
                (string) ($parsed['confidence'] ?? ($parsed['evidence'] ?? ''))
            );
            $reasonTag = $this->normalizeReasonTag((string) ($parsed['tag'] ?? ''), (string) ($parsed['base_reason'] ?? ''));
            $provider = $this->normalizeProvider((string) ($parsed['provider'] ?? $defaultProvider));
            $attemptChain = $this->normalizeAttemptChain(
                $this->decodeAttemptChain($parsed['attempt_chain'] ?? null),
                $provider,
                $lastWorkerId !== '' ? $lastWorkerId : null,
                $chunk->preferred_pool
            );

            $attemptNumber = (int) ($parsed['attempt'] ?? 0);
            if ($attemptNumber <= 0 && $attemptChain !== []) {
                $attemptNumber = (int) Arr::get($attemptChain, count($attemptChain) - 1 .'.attempt_number', 0);
            }
            $attemptRouteValue = $parsed['route'] ?? null;
            if (($attemptRouteValue === null || $attemptRouteValue === '') && $attemptChain !== []) {
                $attemptRouteValue = Arr::get($attemptChain, count($attemptChain) - 1 .'.attempt_route');
            }
            $mxHostValue = $parsed['mx'] ?? null;
            if (($mxHostValue === null || $mxHostValue === '') && $attemptChain !== []) {
                $mxHostValue = Arr::get($attemptChain, count($attemptChain) - 1 .'.mx_host');
            }
            $attemptRoute = [
                'route' => $attemptRouteValue,
                'mx_host' => $mxHostValue,
                'attempt_number' => $attemptNumber > 0 ? $attemptNumber : null,
                'worker_id' => $lastWorkerId !== '' ? $lastWorkerId : null,
                'pool' => $chunk->preferred_pool,
                'preferred_pool' => $chunk->preferred_pool,
                'provider' => $provider,
                'routing_provider' => $provider,
            ];

            $rows[] = [
                'verification_job_id' => (string) $chunk->verification_job_id,
                'verification_job_chunk_id' => (string) $chunk->id,
                'email_hash' => $emailHash,
                'provider' => $provider,
                'policy_version' => $parsed['policy'] ?? null,
                'matched_rule_id' => $parsed['rule'] ?? null,
                'decision_class' => $decisionClass,
                'smtp_code' => $parsed['smtp'] ?? null,
                'enhanced_code' => $parsed['enhanced'] ?? null,
                'retry_strategy' => $parsed['strategy'] ?? null,
                'reason_tag' => $reasonTag,
                'confidence_hint' => $confidence,
                'session_strategy_id' => $parsed['session_strategy_id'] ?? null,
                'attempt_route' => json_encode($attemptRoute, JSON_UNESCAPED_SLASHES),
                'trace_payload' => json_encode([
                    'bucket' => $bucket,
                    'reason_raw' => $reason,
                    'base_reason' => $parsed['base_reason'] ?? null,
                    'attempt_chain' => $attemptChain,
                    'metadata' => $parsed,
                ], JSON_UNESCAPED_SLASHES),
                'observed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($stream);

        return $rows;
    }

    private function isHeaderRow(string $email, string $reason): bool
    {
        return strtolower(trim($email)) === 'email' && strtolower(trim($reason)) === 'reason';
    }

    /**
     * @return array<string, string>
     */
    private function parseReasonEvidence(string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return [];
        }

        $baseReason = $reason;
        $metadata = [];
        if (str_contains($reason, ':')) {
            [$baseReason, $metadataRaw] = explode(':', $reason, 2);
            foreach (explode(';', (string) $metadataRaw) as $segment) {
                if (! str_contains($segment, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $segment, 2);
                $normalizedKey = strtolower(trim((string) $key));
                $normalizedValue = trim((string) $value);
                if ($normalizedKey === '' || $normalizedValue === '') {
                    continue;
                }

                $metadata[$normalizedKey] = $normalizedValue;
            }
        }

        $metadata['base_reason'] = strtolower(trim($baseReason));

        return $metadata;
    }

    private function resolveDecisionClass(string $decision, string $bucket): string
    {
        $decision = strtolower(trim($decision));
        if ($decision === 'deliverable') {
            return 'deliverable';
        }

        if (in_array($decision, ['undeliverable', 'invalid'], true)) {
            return 'undeliverable';
        }

        if ($bucket === 'invalid') {
            return 'undeliverable';
        }

        return 'unknown';
    }

    private function normalizeConfidence(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['low', 'medium', 'high'], true) ? $value : 'medium';
    }

    private function normalizeReasonTag(string $value, string $baseReason): ?string
    {
        $value = strtolower(trim($value));
        if ($value !== '') {
            return $value;
        }

        if (str_contains($baseReason, 'greylist')) {
            return 'greylist';
        }
        if (str_contains($baseReason, 'policy')) {
            return 'policy_blocked';
        }
        if (str_contains($baseReason, 'rate_limit')) {
            return 'rate_limit';
        }
        if (str_contains($baseReason, 'mailbox_full')) {
            return 'mailbox_full';
        }

        return null;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return in_array($provider, ['gmail', 'microsoft', 'yahoo', 'generic'], true)
            ? $provider
            : 'generic';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeAttemptChain(mixed $encoded): array
    {
        $encoded = trim((string) $encoded);
        if ($encoded === '') {
            return [];
        }

        $normalized = strtr($encoded, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return [];
        }

        $parsed = json_decode($decoded, true);
        if (! is_array($parsed)) {
            return [];
        }

        return array_values(array_filter($parsed, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $attemptChain
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAttemptChain(array $attemptChain, string $provider, ?string $workerId, ?string $pool): array
    {
        if ($attemptChain === []) {
            return [];
        }

        $normalized = [];
        foreach ($attemptChain as $entry) {
            $attemptNumber = (int) ($entry['attempt_number'] ?? 0);
            $smtpCode = (int) ($entry['smtp_code'] ?? 0);
            $normalizedEntry = [
                'attempt_number' => $attemptNumber > 0 ? $attemptNumber : null,
                'mx_host' => $this->nullableString($entry['mx_host'] ?? null),
                'attempt_route' => $this->nullableString($entry['attempt_route'] ?? null),
                'decision_class' => $this->nullableString($entry['decision_class'] ?? null),
                'reason_code' => $this->nullableString($entry['reason_code'] ?? null),
                'reason_tag' => $this->nullableString($entry['reason_tag'] ?? null),
                'retry_strategy' => $this->nullableString($entry['retry_strategy'] ?? null),
                'smtp_code' => $smtpCode > 0 ? $smtpCode : null,
                'enhanced_code' => $this->nullableString($entry['enhanced_code'] ?? null),
                'provider_profile' => $this->normalizeProvider((string) ($entry['provider_profile'] ?? $provider)),
                'confidence_hint' => $this->normalizeConfidence((string) ($entry['confidence_hint'] ?? '')),
                'evidence_strength' => $this->normalizeConfidence((string) ($entry['evidence_strength'] ?? '')),
                'worker_id' => $workerId,
                'pool' => $pool,
                'provider' => $provider,
            ];
            $normalized[] = $normalizedEntry;
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
