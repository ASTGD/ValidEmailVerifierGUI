<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendCreditLedger;

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

    public function releaseOnCancellation(SeedSendCampaign $campaign): void
    {
        $reservedCredits = max(0, (int) $campaign->credits_reserved);
        if ($reservedCredits === 0) {
            return;
        }

        $consumedCredits = (int) SeedSendCreditLedger::query()
            ->where('campaign_id', $campaign->id)
            ->where('entry_type', self::ENTRY_CONSUME)
            ->value('credits');

        $releaseCredits = max(0, $reservedCredits - $consumedCredits);

        $this->upsertEntry($campaign, self::ENTRY_RELEASE, $releaseCredits, 'release-cancelled', [
            'campaign_status' => $campaign->status,
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
