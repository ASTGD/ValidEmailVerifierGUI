<?php

namespace App\Console\Commands;

use App\Models\SmtpConfidenceCalibration;
use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicySuggestion;
use App\Models\SmtpProbeQualityRollup;
use App\Models\SmtpUnknownCluster;
use Illuminate\Console\Command;

class GenerateSmtpPolicySuggestionsCommand extends Command
{
    protected $signature = 'ops:smtp-policy-suggestions-generate
        {--provider= : Limit to one provider}
        {--window-days=7 : Lookback window in days}
        {--dry-run : Print suggestion payloads without persisting}';

    protected $description = 'Generate draft SMTP policy suggestions from telemetry metadata (offline, assistive only).';

    public function handle(): int
    {
        if (! (bool) config('engine.smtp_ai_suggestion_enabled', false) && ! $this->option('dry-run')) {
            $this->warn('SMTP AI suggestion generator is disabled. Set SMTP_AI_SUGGESTION_ENABLED=true to persist drafts.');

            return self::SUCCESS;
        }

        $windowDays = max(1, (int) $this->option('window-days'));
        $providerFilter = strtolower(trim((string) $this->option('provider')));
        $minSamples = max(1, (int) config('engine.smtp_ai_min_samples', 500));
        $unknownThreshold = max(0.01, (float) config('engine.smtp_ai_unknown_rate_threshold', 0.20));
        $minTruthSamples = max(1, (int) config('engine.smtp_ai_min_truth_samples', 50));
        $precisionFloor = max(0.01, min(1.0, (float) config('engine.smtp_ai_precision_floor', 0.85)));

        $query = SmtpProbeQualityRollup::query()
            ->whereDate('rollup_date', '>=', now()->subDays($windowDays)->toDateString());

        if ($providerFilter !== '') {
            $query->where('provider', $providerFilter);
        }

        $rows = $query
            ->selectRaw('provider')
            ->selectRaw('COALESCE(SUM(sample_count), 0) as sample_count')
            ->selectRaw('COALESCE(SUM(unknown_count), 0) as unknown_count')
            ->selectRaw('COALESCE(SUM(tempfail_count), 0) as tempfail_count')
            ->selectRaw('COALESCE(SUM(policy_blocked_count), 0) as policy_blocked_count')
            ->groupBy('provider')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No probe quality telemetry found in selected window.');

            return self::SUCCESS;
        }

        $generated = 0;
        foreach ($rows as $row) {
            $provider = strtolower(trim((string) $row->provider));
            if ($provider === '') {
                continue;
            }

            $sampleCount = (int) $row->sample_count;
            if ($sampleCount < $minSamples) {
                continue;
            }

            $unknownCount = (int) $row->unknown_count;
            $tempfailCount = (int) $row->tempfail_count;
            $policyBlockedCount = (int) $row->policy_blocked_count;

            $unknownRate = $sampleCount > 0 ? $unknownCount / $sampleCount : 0.0;
            $tempfailRate = $sampleCount > 0 ? $tempfailCount / $sampleCount : 0.0;
            $policyBlockedRate = $sampleCount > 0 ? $policyBlockedCount / $sampleCount : 0.0;

            $truthSummary = $this->latestTruthCalibration($provider);
            $hasPrecisionRegression = $truthSummary['sample_count'] >= $minTruthSamples
                && $truthSummary['precision_rate'] > 0
                && $truthSummary['precision_rate'] < $precisionFloor;
            $hasUnknownRegression = $unknownRate >= $unknownThreshold;

            if (! $hasUnknownRegression && ! $hasPrecisionRegression) {
                continue;
            }

            $suggestionType = $hasUnknownRegression ? 'unknown_rate_regression' : 'confidence_regression';
            $suggestionPayload = [
                'provider' => $provider,
                'reason' => $suggestionType,
                'recommended_actions' => [
                    'set_provider_mode' => 'cautious',
                    'increase_tempfail_backoff_percent' => 15,
                    'review_rule_tag_candidates' => ['greylist', 'rate_limit', 'policy_blocked'],
                ],
                'target_thresholds' => [
                    'unknown_rate' => $unknownThreshold,
                    'min_samples' => $minSamples,
                    'precision_floor' => $precisionFloor,
                    'min_truth_samples' => $minTruthSamples,
                ],
            ];
            if ($hasPrecisionRegression) {
                $suggestionPayload['recommended_actions']['review_confidence_calibration'] = true;
                $suggestionPayload['recommended_actions']['tighten_deliverable_rules'] = true;
            }

            $supportingMetrics = [
                'sample_count' => $sampleCount,
                'unknown_count' => $unknownCount,
                'tempfail_count' => $tempfailCount,
                'policy_blocked_count' => $policyBlockedCount,
                'unknown_rate' => round($unknownRate, 6),
                'tempfail_rate' => round($tempfailRate, 6),
                'policy_blocked_rate' => round($policyBlockedRate, 6),
                'window_days' => $windowDays,
                'truth_sample_count' => $truthSummary['sample_count'],
                'truth_precision_rate' => $truthSummary['precision_rate'],
                'truth_match_count' => $truthSummary['match_count'],
                'truth_unknown_count' => $truthSummary['unknown_count'],
            ];

            if ($this->option('dry-run')) {
                $this->line(json_encode([
                    'provider' => $provider,
                    'suggestion_payload' => $suggestionPayload,
                    'supporting_metrics' => $supportingMetrics,
                ], JSON_UNESCAPED_SLASHES));
                $generated++;

                continue;
            }

            $suggestion = SmtpPolicySuggestion::query()->create([
                'provider' => $provider,
                'status' => 'draft',
                'suggestion_type' => $suggestionType,
                'source_window' => sprintf('%dd', $windowDays),
                'suggestion_payload' => $suggestionPayload,
                'supporting_metrics' => $supportingMetrics,
                'sample_size' => $sampleCount,
                'created_by' => 'ops:smtp-policy-suggestions-generate',
            ]);

            SmtpUnknownCluster::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'cluster_signature' => 'unknown-rate-regression',
                ],
                [
                    'sample_count' => $sampleCount,
                    'feature_tokens' => [
                        'reason_tag:greylist',
                        'reason_tag:rate_limit',
                        'decision:unknown',
                    ],
                    'example_messages' => [
                        'telemetry-derived cluster without raw mailbox content',
                    ],
                    'recommended_tags' => ['greylist', 'rate_limit'],
                    'status' => 'open',
                    'last_seen_at' => now(),
                ]
            );

            SmtpPolicyActionAudit::query()->create([
                'action' => 'ai_suggestion_generate',
                'policy_version' => null,
                'provider' => $provider,
                'source' => 'automation',
                'actor' => 'ops:smtp-policy-suggestions-generate',
                'result' => 'success',
                'context' => [
                    'suggestion_id' => $suggestion->id,
                    'window_days' => $windowDays,
                    'unknown_rate' => $supportingMetrics['unknown_rate'],
                    'sample_count' => $sampleCount,
                ],
                'created_at' => now(),
            ]);

            $generated++;
        }

        if ($generated === 0) {
            $this->info('No suggestions generated. Telemetry is within configured threshold or below minimum samples.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Generated %d SMTP policy suggestion draft(s).', $generated));

        return self::SUCCESS;
    }

    /**
     * @return array{sample_count:int,match_count:int,unknown_count:int,precision_rate:float}
     */
    private function latestTruthCalibration(string $provider): array
    {
        $row = SmtpConfidenceCalibration::query()
            ->where('provider', $provider)
            ->latest('rollup_date')
            ->latest('updated_at')
            ->first();

        if (! $row) {
            return [
                'sample_count' => 0,
                'match_count' => 0,
                'unknown_count' => 0,
                'precision_rate' => 0.0,
            ];
        }

        return [
            'sample_count' => (int) $row->sample_count,
            'match_count' => (int) $row->match_count,
            'unknown_count' => (int) $row->unknown_count,
            'precision_rate' => (float) $row->precision_rate,
        ];
    }
}
