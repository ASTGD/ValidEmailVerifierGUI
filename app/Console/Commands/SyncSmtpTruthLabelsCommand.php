<?php

namespace App\Console\Commands;

use App\Models\SeedSendRecipient;
use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpTruthLabel;
use Illuminate\Console\Command;

class SyncSmtpTruthLabelsCommand extends Command
{
    protected $signature = 'ops:smtp-truth-labels-sync
        {--campaign= : Limit sync to one SG6 campaign UUID}
        {--since-hours=168 : Include recipient updates within the last N hours}
        {--dry-run : Print counts without persisting changes}';

    protected $description = 'Sync trusted SMTP truth labels from SG6 seed-send recipient evidence.';

    public function handle(): int
    {
        $campaignId = trim((string) $this->option('campaign'));
        $sinceHours = max(1, (int) $this->option('since-hours'));
        $dryRun = (bool) $this->option('dry-run');

        $query = SeedSendRecipient::query()
            ->with('campaign:id,provider')
            ->whereIn('status', [
                SeedSendRecipient::STATUS_DELIVERED,
                SeedSendRecipient::STATUS_BOUNCED,
                SeedSendRecipient::STATUS_DEFERRED,
                SeedSendRecipient::STATUS_FAILED,
            ])
            ->where('updated_at', '>=', now()->subHours($sinceHours));

        if ($campaignId !== '') {
            $query->where('campaign_id', $campaignId);
        }

        $total = 0;
        $inserted = 0;
        $updated = 0;

        $query->orderBy('id')->chunkById(500, function ($recipients) use ($dryRun, &$total, &$inserted, &$updated): void {
            foreach ($recipients as $recipient) {
                $total++;
                $truth = $this->truthFromRecipientStatus((string) $recipient->status);
                if ($truth === null) {
                    continue;
                }

                $payload = [
                    'email_hash' => (string) $recipient->email_hash,
                    'provider' => $this->normalizeProvider((string) ($recipient->campaign?->provider ?? 'generic')),
                    'truth_label' => $truth['label'],
                    'confidence_hint' => $truth['confidence'],
                    'source' => 'sg6_seed_send',
                    'source_campaign_id' => $recipient->campaign_id,
                    'decision_class' => $truth['decision_class'],
                    'reason_tag' => $truth['reason_tag'],
                    'observed_at' => $recipient->last_event_at ?? $recipient->updated_at ?? now(),
                    'evidence_payload' => [
                        'recipient_status' => $recipient->status,
                        'attempt_count' => (int) $recipient->attempt_count,
                        'provider_message_id' => $recipient->provider_message_id,
                    ],
                ];

                if ($dryRun) {
                    continue;
                }

                $label = SmtpTruthLabel::query()->where('source_recipient_id', $recipient->id)->first();
                if ($label) {
                    $label->fill($payload)->save();
                    $updated++;
                } else {
                    SmtpTruthLabel::query()->create(array_merge($payload, [
                        'source_recipient_id' => $recipient->id,
                    ]));
                    $inserted++;
                }
            }
        });

        if ($dryRun) {
            $this->info(sprintf('Dry-run complete. Eligible SG6 recipients: %d', $total));

            return self::SUCCESS;
        }

        SmtpPolicyActionAudit::query()->create([
            'action' => 'truth_label_sync',
            'policy_version' => null,
            'provider' => 'generic',
            'source' => 'automation',
            'actor' => 'ops:smtp-truth-labels-sync',
            'result' => 'success',
            'context' => [
                'campaign_id' => $campaignId !== '' ? $campaignId : null,
                'since_hours' => $sinceHours,
                'eligible' => $total,
                'inserted' => $inserted,
                'updated' => $updated,
            ],
            'created_at' => now(),
        ]);

        $this->info(sprintf(
            'SMTP truth-label sync complete. eligible=%d inserted=%d updated=%d',
            $total,
            $inserted,
            $updated
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{label:string,confidence:string,decision_class:string,reason_tag:string}|null
     */
    private function truthFromRecipientStatus(string $status): ?array
    {
        return match (strtolower(trim($status))) {
            SeedSendRecipient::STATUS_DELIVERED => [
                'label' => 'deliverable',
                'confidence' => 'high',
                'decision_class' => 'deliverable',
                'reason_tag' => 'mailbox_exists',
            ],
            SeedSendRecipient::STATUS_BOUNCED => [
                'label' => 'undeliverable',
                'confidence' => 'high',
                'decision_class' => 'undeliverable',
                'reason_tag' => 'mailbox_not_found',
            ],
            SeedSendRecipient::STATUS_DEFERRED => [
                'label' => 'unknown',
                'confidence' => 'medium',
                'decision_class' => 'unknown',
                'reason_tag' => 'unknown_transient',
            ],
            SeedSendRecipient::STATUS_FAILED => [
                'label' => 'unknown',
                'confidence' => 'low',
                'decision_class' => 'unknown',
                'reason_tag' => 'unknown_transient',
            ],
            default => null,
        };
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return in_array($provider, ['gmail', 'microsoft', 'yahoo', 'generic'], true)
            ? $provider
            : 'generic';
    }
}
