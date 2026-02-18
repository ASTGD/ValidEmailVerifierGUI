<?php

namespace App\Services;

use App\Support\EngineSettings;

class VerificationOutputMapper
{
    public function __construct(private DeliverabilityScoreCalculator $calculator) {}

    /**
     * @param  array<string, mixed>|null  $cacheOutcome
     * @return array{email: string, status: string, sub_status: string, score: int, reason: string}
     */
    public function map(string $email, string $sourceStatus, string $reason, ?array $cacheOutcome = null): array
    {
        $normalizedReason = $this->normalizeReason($reason);
        $status = $this->normalizeStatus($sourceStatus);
        $subStatus = $this->mapSubStatus($normalizedReason);
        $score = $this->calculator->calculate($status, $subStatus, $normalizedReason, $cacheOutcome);

        if (str_starts_with($normalizedReason, 'catch_all')) {
            $status = $this->applyCatchAllPolicy($score);
        }

        $reasonOutput = $reason !== '' ? $reason : 'unknown';

        return [
            'email' => $email,
            'status' => $status,
            'sub_status' => $subStatus,
            'score' => $score,
            'reason' => $reasonOutput,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, ['valid', 'invalid', 'risky'], true) ? $normalized : 'risky';
    }

    private function normalizeReason(string $reason): string
    {
        $reason = strtolower(trim($reason));

        if ($reason === '') {
            return 'unknown';
        }

        $parts = explode(':', $reason, 2);

        return $parts[0] !== '' ? $parts[0] : 'unknown';
    }

    private function mapSubStatus(string $reason): string
    {
        return match ($reason) {
            'catch_all', 'catch_all_high_confidence', 'catch_all_medium_confidence', 'catch_all_low_confidence' => 'catch_all',
            'smtp_connect_ok', 'rcpt_ok' => 'smtp_connect_ok',
            'mx_missing' => 'mx_missing',
            'syntax' => 'syntax',
            'disposable_domain' => 'disposable_domain',
            'role_account' => 'role_account',
            'domain_typo_suspected' => 'domain_typo_suspected',
            'smtp_timeout', 'smtp_connect_timeout', 'dns_timeout' => 'timeout',
            'smtp_tempfail', 'dns_servfail' => 'tempfail',
            'rcpt_rejected', 'smtp_unavailable' => 'mailbox_not_found',
            default => 'unknown',
        };
    }

    private function applyCatchAllPolicy(int $score): string
    {
        $policy = EngineSettings::catchAllPolicy();

        if ($policy === 'promote_if_score_gte') {
            $threshold = EngineSettings::catchAllPromoteThreshold();

            if ($threshold !== null && $score >= $threshold) {
                return 'valid';
            }
        }

        return 'risky';
    }
}
