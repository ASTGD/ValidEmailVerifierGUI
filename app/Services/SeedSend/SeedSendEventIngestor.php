<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendEvent;
use App\Models\SeedSendRecipient;
use Illuminate\Support\Carbon;
use RuntimeException;

class SeedSendEventIngestor
{
    public function __construct(private SeedSendCampaignGuardrails $guardrails) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(string $provider, array $payload): SeedSendEvent
    {
        $provider = strtolower(trim($provider));
        $providerMessageId = trim((string) ($payload['provider_message_id'] ?? $payload['message_id'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $campaignId = trim((string) ($payload['campaign_id'] ?? ''));
        $emailHash = $this->validEmailHash($email);
        $hasProviderMessageId = $providerMessageId !== '';
        $hasCampaignEmailKey = $campaignId !== '' && $emailHash !== null;

        if (! $hasProviderMessageId && ! $hasCampaignEmailKey) {
            throw new RuntimeException('Invalid seed-send webhook mapping key.');
        }

        $recipient = null;

        if ($hasProviderMessageId) {
            $recipient = SeedSendRecipient::query()
                ->where('provider_message_id', $providerMessageId)
                ->latest('id')
                ->first();
        }

        if (! $recipient && $hasCampaignEmailKey) {
            $recipient = SeedSendRecipient::query()
                ->where('campaign_id', $campaignId)
                ->where('email_hash', $emailHash)
                ->latest('id')
                ->first();
        }

        if (! $recipient) {
            throw new RuntimeException('Unable to map seed-send event to a campaign recipient.');
        }

        $campaign = $recipient->campaign;
        if (! $campaign) {
            throw new RuntimeException('Seed-send event campaign not found for recipient.');
        }

        if ($campaignId !== '' && (string) $campaign->id !== $campaignId) {
            throw new RuntimeException('Webhook campaign mismatch for mapped recipient.');
        }

        $eventType = $this->normalizeEventType((string) ($payload['event_type'] ?? $payload['event'] ?? ''));
        $eventTime = $this->resolveEventTime($payload);
        $smtpCode = $this->nullableString($payload['smtp_code'] ?? null);
        $enhancedCode = $this->nullableString($payload['enhanced_code'] ?? null);
        $dedupeKey = $this->buildDedupeKey(
            $provider,
            $campaign,
            $recipient,
            $eventType,
            $eventTime,
            $providerMessageId,
            $smtpCode,
            $enhancedCode,
            $payload
        );

        $event = SeedSendEvent::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'provider' => $provider,
                'event_type' => $eventType,
                'event_time' => $eventTime,
                'smtp_code' => $smtpCode,
                'enhanced_code' => $enhancedCode,
                'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
                'raw_payload' => $payload,
            ]
        );

        if (! $event->wasRecentlyCreated) {
            return $event;
        }

        $recipient->update([
            'status' => $this->recipientStatusForEvent($eventType),
            'last_event_at' => $eventTime ?: now(),
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : $recipient->provider_message_id,
            'evidence_payload' => $payload,
        ]);

        $this->refreshCampaignCounters($campaign->id);
        $this->guardrails->evaluateAndApply($campaign->fresh());

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

    private function validEmailHash(string $email): ?string
    {
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return hash('sha256', $email);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildDedupeKey(
        string $provider,
        SeedSendCampaign $campaign,
        SeedSendRecipient $recipient,
        string $eventType,
        ?Carbon $eventTime,
        string $providerMessageId,
        ?string $smtpCode,
        ?string $enhancedCode,
        array $payload
    ): string {
        $externalEventId = $this->nullableString($payload['event_id'] ?? $payload['sg_event_id'] ?? null);

        if ($externalEventId !== null) {
            return hash('sha256', implode('|', [
                $provider,
                (string) $campaign->id,
                $externalEventId,
            ]));
        }

        $encoded = json_encode([
            'provider' => $provider,
            'campaign_id' => (string) $campaign->id,
            'recipient_id' => $recipient->id,
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'event_type' => $eventType,
            'event_time' => $eventTime?->toIso8601String(),
            'smtp_code' => $smtpCode,
            'enhanced_code' => $enhancedCode,
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            $encoded = implode('|', [
                $provider,
                (string) $campaign->id,
                (string) $recipient->id,
                $providerMessageId,
                $eventType,
                $eventTime?->toIso8601String() ?? '',
                $smtpCode ?? '',
                $enhancedCode ?? '',
            ]);
        }

        return hash('sha256', $encoded);
    }
}
