<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendEvent;
use App\Models\SeedSendRecipient;
use Illuminate\Support\Carbon;
use RuntimeException;

class SeedSendEventIngestor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(string $provider, array $payload): SeedSendEvent
    {
        $providerMessageId = trim((string) ($payload['provider_message_id'] ?? $payload['message_id'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $campaignId = trim((string) ($payload['campaign_id'] ?? ''));

        $recipient = SeedSendRecipient::query()
            ->when($providerMessageId !== '', function ($query) use ($providerMessageId) {
                $query->where('provider_message_id', $providerMessageId);
            }, function ($query) use ($email, $campaignId) {
                if ($campaignId !== '') {
                    $query->where('campaign_id', $campaignId);
                }

                if ($email !== '') {
                    $query->where('email_hash', hash('sha256', $email));
                }
            })
            ->latest('id')
            ->first();

        if (! $recipient && $campaignId === '') {
            throw new RuntimeException('Unable to map seed-send event to a campaign/recipient.');
        }

        $campaign = $recipient?->campaign;
        if (! $campaign && $campaignId !== '') {
            $campaign = SeedSendCampaign::query()->find($campaignId);
        }

        if (! $campaign) {
            throw new RuntimeException('Seed-send event campaign not found.');
        }

        $eventType = $this->normalizeEventType((string) ($payload['event_type'] ?? $payload['event'] ?? ''));
        $eventTime = $this->resolveEventTime($payload);

        $event = SeedSendEvent::query()->create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient?->id,
            'provider' => strtolower(trim($provider)),
            'event_type' => $eventType,
            'event_time' => $eventTime,
            'smtp_code' => $this->nullableString($payload['smtp_code'] ?? null),
            'enhanced_code' => $this->nullableString($payload['enhanced_code'] ?? null),
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'raw_payload' => $payload,
        ]);

        if ($recipient) {
            $recipient->update([
                'status' => $this->recipientStatusForEvent($eventType),
                'last_event_at' => $eventTime ?: now(),
                'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : $recipient->provider_message_id,
                'evidence_payload' => $payload,
            ]);
        }

        $this->refreshCampaignCounters($campaign->id);

        return $event;
    }

    private function refreshCampaignCounters(string $campaignId): void
    {
        $totals = SeedSendRecipient::query()
            ->selectRaw('status, count(*) as count')
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->pluck('count', 'status');

        $delivered = (int) ($totals[SeedSendRecipient::STATUS_DELIVERED] ?? 0);
        $bounced = (int) ($totals[SeedSendRecipient::STATUS_BOUNCED] ?? 0);
        $deferred = (int) ($totals[SeedSendRecipient::STATUS_DEFERRED] ?? 0);
        $dispatched = (int) ($totals[SeedSendRecipient::STATUS_DISPATCHED] ?? 0);
        $failed = (int) ($totals[SeedSendRecipient::STATUS_FAILED] ?? 0);

        SeedSendCampaign::query()
            ->where('id', $campaignId)
            ->update([
                'delivered_count' => $delivered,
                'bounced_count' => $bounced,
                'deferred_count' => $deferred,
                'sent_count' => $dispatched + $delivered + $bounced + $deferred + $failed,
                'updated_at' => now(),
            ]);
    }

    private function normalizeEventType(string $eventType): string
    {
        $eventType = strtolower(trim($eventType));

        return match ($eventType) {
            'delivered', 'delivery' => 'delivered',
            'bounce', 'bounced' => 'bounced',
            'deferred', 'tempfail' => 'deferred',
            'failed', 'dropped', 'reject' => 'failed',
            default => 'deferred',
        };
    }

    private function recipientStatusForEvent(string $eventType): string
    {
        return match ($eventType) {
            'delivered' => SeedSendRecipient::STATUS_DELIVERED,
            'bounced' => SeedSendRecipient::STATUS_BOUNCED,
            'failed' => SeedSendRecipient::STATUS_FAILED,
            default => SeedSendRecipient::STATUS_DEFERRED,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventTime(array $payload): ?Carbon
    {
        $value = $payload['event_time'] ?? $payload['timestamp'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
