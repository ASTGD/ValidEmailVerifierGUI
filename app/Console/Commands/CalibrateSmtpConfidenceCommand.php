<?php

namespace App\Console\Commands;

use App\Models\EmailVerificationOutcome;
use App\Models\SmtpConfidenceCalibration;
use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpTruthLabel;
use Illuminate\Console\Command;

class CalibrateSmtpConfidenceCommand extends Command
{
    protected $signature = 'ops:smtp-confidence-calibrate
        {--window-days=14 : Include truth labels observed within this window}
        {--dry-run : Print calibration aggregates without persisting}';

    protected $description = 'Build SMTP decision-confidence calibration rollups from truth labels + verification outcomes.';

    public function handle(): int
    {
        $windowDays = max(1, (int) $this->option('window-days'));
        $dryRun = (bool) $this->option('dry-run');

        $labels = SmtpTruthLabel::query()
            ->where('observed_at', '>=', now()->subDays($windowDays))
            ->orderBy('id')
            ->get();

        if ($labels->isEmpty()) {
            $this->info('No truth labels available for calibration window.');

            return self::SUCCESS;
        }

        $aggregates = [];
        foreach ($labels as $label) {
            $prediction = $this->predictedFromOutcome($label);
            if ($prediction === null) {
                continue;
            }

            $key = implode('|', [
                $label->provider ?: 'generic',
                $prediction['decision_class'],
                $prediction['confidence_hint'],
            ]);

            $aggregate = $aggregates[$key] ?? [
                'provider' => $label->provider ?: 'generic',
                'decision_class' => $prediction['decision_class'],
                'confidence_hint' => $prediction['confidence_hint'],
                'sample_count' => 0,
                'match_count' => 0,
                'unknown_count' => 0,
            ];

            $aggregate['sample_count']++;
            if ($label->truth_label === 'unknown') {
                $aggregate['unknown_count']++;
            }
            if ($prediction['decision_class'] === $label->truth_label) {
                $aggregate['match_count']++;
            }

            $aggregates[$key] = $aggregate;
        }

        if ($aggregates === []) {
            $this->info('No calibration candidates found (missing matching verification outcomes).');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line(json_encode(array_values($aggregates), JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rollupDate = now()->toDateString();
        foreach ($aggregates as $aggregate) {
            $sampleCount = (int) $aggregate['sample_count'];
            $matchCount = (int) $aggregate['match_count'];
            $unknownCount = (int) $aggregate['unknown_count'];
            $precision = $sampleCount > 0 ? $matchCount / $sampleCount : 0.0;

            SmtpConfidenceCalibration::query()->updateOrCreate(
                [
                    'rollup_date' => $rollupDate,
                    'provider' => $aggregate['provider'],
                    'decision_class' => $aggregate['decision_class'],
                    'confidence_hint' => $aggregate['confidence_hint'],
                ],
                [
                    'sample_count' => $sampleCount,
                    'match_count' => $matchCount,
                    'unknown_count' => $unknownCount,
                    'precision_rate' => round($precision, 5),
                    'supporting_metrics' => [
                        'window_days' => $windowDays,
                        'match_rate' => round($precision, 5),
                    ],
                ]
            );
        }

        SmtpPolicyActionAudit::query()->create([
            'action' => 'confidence_calibration',
            'policy_version' => null,
            'provider' => 'generic',
            'source' => 'automation',
            'actor' => 'ops:smtp-confidence-calibrate',
            'result' => 'success',
            'context' => [
                'window_days' => $windowDays,
                'groups' => count($aggregates),
                'rollup_date' => $rollupDate,
            ],
            'created_at' => now(),
        ]);

        $this->info(sprintf('SMTP confidence calibration updated for %d groups.', count($aggregates)));

        return self::SUCCESS;
    }

    /**
     * @return array{decision_class:string,confidence_hint:string}|null
     */
    private function predictedFromOutcome(SmtpTruthLabel $label): ?array
    {
        /** @var EmailVerificationOutcome|null $outcome */
        $outcome = EmailVerificationOutcome::query()
            ->where('email_hash', $label->email_hash)
            ->where('observed_at', '<=', $label->observed_at ?? now())
            ->latest('observed_at')
            ->first();

        if (! $outcome) {
            return null;
        }

        $details = is_array($outcome->details) ? $outcome->details : [];
        $outcomeValue = strtolower(trim((string) $outcome->outcome));
        $decisionClass = match ($outcomeValue) {
            'valid' => 'deliverable',
            'invalid' => 'undeliverable',
            default => 'unknown',
        };

        $confidence = strtolower(trim((string) ($details['decision_confidence'] ?? '')));
        if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
            $confidence = $decisionClass === 'unknown' ? 'low' : 'high';
        }

        return [
            'decision_class' => $decisionClass,
            'confidence_hint' => $confidence,
        ];
    }
}
