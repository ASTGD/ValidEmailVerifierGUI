<?php

namespace App\Services\SeedSend\Providers;

use App\Contracts\SeedSendProvider;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendRecipient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use RuntimeException;

class SendGridSeedSendProvider implements SeedSendProvider
{
    public function __construct(private HttpFactory $http) {}

    public function dispatch(SeedSendCampaign $campaign, SeedSendRecipient $recipient): array
    {
        $apiKey = trim((string) config('seed_send.provider.providers.sendgrid.api_key', ''));
        $endpoint = trim((string) config('seed_send.provider.providers.sendgrid.api_endpoint', 'https://api.sendgrid.com/v3/mail/send'));
        $fromEmail = trim((string) config('seed_send.provider.providers.sendgrid.from_email', ''));
        $fromName = trim((string) config('seed_send.provider.providers.sendgrid.from_name', 'Verification Team'));
        $subject = trim((string) config('seed_send.provider.providers.sendgrid.subject', 'Mailbox verification seed message'));
        $body = trim((string) config('seed_send.provider.providers.sendgrid.text_body', 'Mailbox verification test message. No action required.'));

        if ($apiKey === '' || $fromEmail === '') {
            throw new RuntimeException('SendGrid provider is not configured.');
        }

        $timeoutSeconds = max(1, (int) config('seed_send.provider.providers.sendgrid.timeout_seconds', 20));
        $retryTimes = max(0, (int) config('seed_send.provider.providers.sendgrid.retry_times', 2));
        $sandboxMode = (bool) config('seed_send.provider.providers.sendgrid.sandbox_mode', false);

        $payload = [
            'from' => [
                'email' => $fromEmail,
                'name' => $fromName,
            ],
            'personalizations' => [[
                'to' => [[
                    'email' => $recipient->email,
                ]],
                'custom_args' => [
                    'campaign_id' => (string) $campaign->id,
                    'recipient_id' => (string) $recipient->id,
                    'verification_job_id' => (string) $campaign->verification_job_id,
                ],
            ]],
            'subject' => $subject,
            'content' => [[
                'type' => 'text/plain',
                'value' => $body,
            ]],
            'mail_settings' => [
                'sandbox_mode' => [
                    'enable' => $sandboxMode,
                ],
            ],
        ];

        $response = $this->http
            ->retry($retryTimes, 250)
            ->timeout($timeoutSeconds)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'SendGrid dispatch failed (%d): %s',
                $response->status(),
                mb_strimwidth($response->body(), 0, 280, '...')
            ));
        }

        $messageId = trim((string) $response->header('X-Message-Id'));
        if ($messageId === '') {
            $messageId = sprintf('sendgrid-%s', (string) Str::uuid());
        }

        return [
            'provider_message_id' => $messageId,
            'payload' => [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'provider' => 'sendgrid',
                'message_id' => $messageId,
                'status' => $response->status(),
            ],
        ];
    }

    public function normalizeWebhookEvents(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $events = array_is_list($payload) ? $payload : [$payload];
        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $normalized[] = [
                'campaign_id' => trim((string) data_get($event, 'custom_args.campaign_id', data_get($event, 'unique_args.campaign_id', data_get($event, 'campaign_id', '')))),
                'email' => strtolower(trim((string) ($event['email'] ?? ''))),
                'provider_message_id' => $this->normalizeMessageId($event),
                'event_type' => strtolower(trim((string) ($event['event'] ?? $event['event_type'] ?? ''))),
                'event_time' => $event['timestamp'] ?? $event['event_time'] ?? null,
                'smtp_code' => $this->extractSmtpCode($event),
                'enhanced_code' => $this->extractEnhancedCode($event),
                'event_id' => trim((string) ($event['sg_event_id'] ?? $event['event_id'] ?? '')),
                'raw_payload' => $event,
            ];
        }

        return $normalized;
    }

    public function healthMetadata(): array
    {
        return [
            'provider' => 'sendgrid',
            'sandbox_mode' => (bool) config('seed_send.provider.providers.sendgrid.sandbox_mode', false),
            'endpoint' => (string) config('seed_send.provider.providers.sendgrid.api_endpoint', 'https://api.sendgrid.com/v3/mail/send'),
            'from_email' => (string) config('seed_send.provider.providers.sendgrid.from_email', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function normalizeMessageId(array $event): string
    {
        $candidate = trim((string) ($event['provider_message_id'] ?? $event['sg_message_id'] ?? $event['smtp-id'] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }

        $response = (string) ($event['response'] ?? '');
        if (preg_match('/queued as\\s+([\\w\\-]+)/i', $response, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function extractSmtpCode(array $event): string
    {
        $directCode = trim((string) ($event['smtp_code'] ?? $event['status'] ?? ''));
        if ($directCode !== '' && preg_match('/^\\d{3}$/', $directCode) === 1) {
            return $directCode;
        }

        $source = trim((string) ($event['response'] ?? $event['reason'] ?? ''));
        if ($source !== '' && preg_match('/\\b(\\d{3})\\b/', $source, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function extractEnhancedCode(array $event): string
    {
        $source = trim((string) ($event['enhanced_code'] ?? $event['response'] ?? $event['reason'] ?? ''));
        if ($source !== '' && preg_match('/\\b([245]\\.\\d+\\.\\d+)\\b/', $source, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }
}
