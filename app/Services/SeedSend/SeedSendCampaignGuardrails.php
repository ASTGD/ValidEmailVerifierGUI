<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Support\AdminAuditLogger;

class SeedSendCampaignGuardrails
{
    public function evaluateAndApply(SeedSendCampaign $campaign): ?string
    {
        if (! (bool) config('seed_send.guardrails.auto_pause_enabled', true)) {
            return null;
        }

        if (! in_array($campaign->status, [SeedSendCampaign::STATUS_RUNNING, SeedSendCampaign::STATUS_QUEUED], true)) {
            return null;
        }

        $sentCount = max(0, (int) $campaign->sent_count);
        $minimumSampleSize = max(1, (int) config('seed_send.guardrails.min_sample_size', 25));

        if ($sentCount < $minimumSampleSize) {
            return null;
        }

        $bounceRateThreshold = max(1, (float) config('seed_send.guardrails.bounce_rate_pause_percent', 20));
        $deferRateThreshold = max(1, (float) config('seed_send.guardrails.defer_rate_pause_percent', 40));

        $bounceRate = $sentCount > 0 ? ((int) $campaign->bounced_count / $sentCount) * 100 : 0;
        $deferRate = $sentCount > 0 ? ((int) $campaign->deferred_count / $sentCount) * 100 : 0;

        $reasons = [];
        if ($bounceRate >= $bounceRateThreshold) {
            $reasons[] = sprintf('bounce %.1f%% >= %.1f%%', $bounceRate, $bounceRateThreshold);
        }

        if ($deferRate >= $deferRateThreshold) {
            $reasons[] = sprintf('defer %.1f%% >= %.1f%%', $deferRate, $deferRateThreshold);
        }

        if ($reasons === []) {
            return null;
        }

        $reason = 'auto_pause_guardrail: '.implode(', ', $reasons);

        $campaign->update([
            'status' => SeedSendCampaign::STATUS_PAUSED,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);

        $campaign->job?->addLog('seed_send_campaign_auto_paused', 'SG6 campaign auto-paused by guardrails.', [
            'seed_send_campaign_id' => $campaign->id,
            'reason' => $reason,
            'sent_count' => $sentCount,
            'delivered_count' => (int) $campaign->delivered_count,
            'bounced_count' => (int) $campaign->bounced_count,
            'deferred_count' => (int) $campaign->deferred_count,
        ]);

        AdminAuditLogger::log('seed_send_campaign_auto_paused', $campaign, [
            'reason' => $reason,
            'sent_count' => $sentCount,
        ]);

        return $reason;
    }
}
