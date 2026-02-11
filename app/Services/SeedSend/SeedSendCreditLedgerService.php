<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendCreditLedger;
use App\Models\SeedSendRecipient;

class SeedSendCreditLedgerService
{
    public const ENTRY_RESERVE = 'reserve';

    public const ENTRY_CONSUME = 'consume';

    public const ENTRY_RELEASE = 'release';

    public function reserveForCampaign(SeedSendCampaign $campaign): void
    {
        $credits = max(0, (int) $campaign->credits_reserved);
        if ($credits === 0) {
            return;
        }

        $this->upsertEntry($campaign, self::ENTRY_RESERVE, $credits, 'reserve', [
            'campaign_status' => $campaign->status,
        ]);
    }

    public function settleCampaign(SeedSendCampaign $campaign, int $usedCredits): void
    {
        $reservedCredits = max(0, (int) $campaign->credits_reserved);
        $usedCredits = max(0, min($usedCredits, $reservedCredits));
        $releaseCredits = max(0, $reservedCredits - $usedCredits);

        $this->upsertEntry($campaign, self::ENTRY_CONSUME, $usedCredits, 'consume', [
            'campaign_status' => $campaign->status,
        ]);

        $this->upsertEntry($campaign, self::ENTRY_RELEASE, $releaseCredits, 'release', [
            'campaign_status' => $campaign->status,
        ]);
    }

    public function settleCancellation(SeedSendCampaign $campaign): void
    {
        $reservedCredits = max(0, (int) $campaign->credits_reserved);
        $creditsPerRecipient = max(1, (int) config('seed_send.credits.per_recipient', 1));
        $recipientCount = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->count();

        $derivedAttemptedCount = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where(function ($query): void {
                $query
                    ->where('attempt_count', '>', 0)
                    ->orWhereIn('status', [
                        SeedSendRecipient::STATUS_DISPATCHED,
                        SeedSendRecipient::STATUS_DELIVERED,
                        SeedSendRecipient::STATUS_BOUNCED,
                        SeedSendRecipient::STATUS_DEFERRED,
                    ]);
            })
            ->count();

        $sentCountBasedCredits = max(0, (int) $campaign->sent_count) * $creditsPerRecipient;
        $derivedCredits = max(0, (int) $derivedAttemptedCount) * $creditsPerRecipient;

        $usedCredits = $recipientCount > 0
            ? $derivedCredits
            : $sentCountBasedCredits;
        $usedCredits = max(0, min($usedCredits, $reservedCredits));
        $releaseCredits = max(0, $reservedCredits - $usedCredits);

        $this->upsertEntry($campaign, self::ENTRY_CONSUME, $usedCredits, 'consume', [
            'campaign_status' => $campaign->status,
            'settlement_mode' => 'cancellation',
            'derived_attempted_count' => $derivedAttemptedCount,
            'sent_count' => (int) $campaign->sent_count,
        ]);

        $this->upsertEntry($campaign, self::ENTRY_RELEASE, $releaseCredits, 'release', [
            'campaign_status' => $campaign->status,
            'settlement_mode' => 'cancellation',
            'derived_attempted_count' => $derivedAttemptedCount,
            'sent_count' => (int) $campaign->sent_count,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function upsertEntry(
        SeedSendCampaign $campaign,
        string $entryType,
        int $credits,
        string $referenceSuffix,
        array $metadata = []
    ): void {
        $referenceKey = sprintf('seed-send:%s:%s', $campaign->id, $referenceSuffix);

        SeedSendCreditLedger::query()->updateOrCreate(
            ['reference_key' => $referenceKey],
            [
                'campaign_id' => $campaign->id,
                'verification_job_id' => $campaign->verification_job_id,
                'user_id' => $campaign->user_id,
                'entry_type' => $entryType,
                'credits' => max(0, $credits),
                'metadata' => $metadata,
            ]
        );
    }
}
