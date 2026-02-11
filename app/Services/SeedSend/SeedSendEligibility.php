<?php

namespace App\Services\SeedSend;

use App\Enums\VerificationJobStatus;
use App\Models\SeedSendConsent;
use App\Models\VerificationJob;
use App\Services\SeedSend\Providers\SeedSendProviderManager;

class SeedSendEligibility
{
    public function __construct(private SeedSendProviderManager $providerManager) {}

    /**
     * @return array{eligible: bool, reason: string|null, provider: string}
     */
    public function evaluate(VerificationJob $job): array
    {
        $provider = $this->providerManager->defaultProvider();

        if (! (bool) config('seed_send.enabled', false)) {
            return ['eligible' => false, 'reason' => 'seed_send_disabled', 'provider' => $provider];
        }

        if ($job->status !== VerificationJobStatus::Completed) {
            return ['eligible' => false, 'reason' => 'job_not_completed', 'provider' => $provider];
        }

        if ((bool) config('seed_send.webhooks.required', true)) {
            $secret = $this->providerManager->webhookSecretForProvider($provider);
            if ($secret === '') {
                return ['eligible' => false, 'reason' => 'webhook_secret_missing', 'provider' => $provider];
            }
        }

        if (! $this->providerManager->isEnabled($provider)) {
            return ['eligible' => false, 'reason' => 'provider_disabled', 'provider' => $provider];
        }

        if ((bool) config('seed_send.consent.required', true)) {
            $approvedConsentExists = $job->seedSendConsents()
                ->where('status', SeedSendConsent::STATUS_APPROVED)
                ->exists();

            if (! $approvedConsentExists) {
                return ['eligible' => false, 'reason' => 'consent_not_approved', 'provider' => $provider];
            }
        }

        return ['eligible' => true, 'reason' => null, 'provider' => $provider];
    }
}
