<?php

namespace App\Services\SeedSend;

use App\Enums\VerificationJobStatus;
use App\Jobs\DispatchSeedSendCampaignJob;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\AdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SeedSendCampaignService
{
    public function __construct(
        private SeedSendRecipientResolver $recipientResolver,
        private SeedSendEligibility $eligibility
    ) {}

    public function requestConsent(VerificationJob $job, User $user, ?string $scope = null): SeedSendConsent
    {
        $scope = $this->resolveScope($scope);

        $existing = SeedSendConsent::query()
            ->where('verification_job_id', $job->id)
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->whereIn('status', [SeedSendConsent::STATUS_REQUESTED, SeedSendConsent::STATUS_APPROVED])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $consent = SeedSendConsent::query()->create([
            'verification_job_id' => $job->id,
            'user_id' => $user->id,
            'scope' => $scope,
            'consent_text_version' => (string) config('seed_send.consent.text_version', 'v1'),
            'consented_at' => now(),
            'consented_by_user_id' => $user->id,
            'status' => SeedSendConsent::STATUS_REQUESTED,
        ]);

        $job->addLog('seed_send_consent_requested', 'SG6 consent requested by customer.', [
            'seed_send_consent_id' => $consent->id,
            'scope' => $scope,
        ], $user->id);

        return $consent;
    }

    public function approveConsent(SeedSendConsent $consent, User $admin): SeedSendConsent
    {
        if ($consent->status === SeedSendConsent::STATUS_APPROVED) {
            return $consent;
        }

        $consent->update([
            'status' => SeedSendConsent::STATUS_APPROVED,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $job = $consent->job;
        if ($job) {
            $job->addLog('seed_send_consent_approved', 'SG6 consent approved by admin.', [
                'seed_send_consent_id' => $consent->id,
            ], $admin->id);
        }

        AdminAuditLogger::log('seed_send_consent_approved', $consent, [
            'verification_job_id' => $consent->verification_job_id,
        ]);

        return $consent->refresh();
    }

    public function startCampaign(VerificationJob $job, SeedSendConsent $consent, User $admin): SeedSendCampaign
    {
        if ($job->status !== VerificationJobStatus::Completed) {
            throw new RuntimeException('Only completed jobs are eligible for SG6 campaigns.');
        }

        if ($consent->status !== SeedSendConsent::STATUS_APPROVED) {
            throw new RuntimeException('SG6 consent must be approved before campaign start.');
        }

        $eligibility = $this->eligibility->evaluate($job);
        if (! $eligibility['eligible']) {
            throw new RuntimeException('SG6 campaign is blocked: '.$eligibility['reason']);
        }

        $activeCampaignExists = $job->seedSendCampaigns()
            ->whereIn('status', [
                SeedSendCampaign::STATUS_PENDING,
                SeedSendCampaign::STATUS_QUEUED,
                SeedSendCampaign::STATUS_RUNNING,
                SeedSendCampaign::STATUS_PAUSED,
            ])
            ->exists();
        if ($activeCampaignExists) {
            throw new RuntimeException('An SG6 campaign is already active for this job.');
        }

        $emails = $this->recipientResolver->resolve($job);
        if ($emails === []) {
            throw new RuntimeException('No recipients available for SG6 campaign.');
        }

        $creditsPerRecipient = max(1, (int) config('seed_send.credits.per_recipient', 1));
        $creditsReserved = $creditsPerRecipient * count($emails);

        if ((bool) config('seed_send.credits.enforce', false)) {
            $balance = (int) data_get($job->user, 'seed_send_credit_balance', 0);
            if ($balance < $creditsReserved) {
                throw new RuntimeException('Insufficient SG6 add-on credits for this campaign.');
            }
        }

        return DB::transaction(function () use ($job, $consent, $admin, $emails, $creditsReserved, $eligibility): SeedSendCampaign {
            $campaign = SeedSendCampaign::query()->create([
                'id' => (string) Str::uuid(),
                'verification_job_id' => $job->id,
                'user_id' => $job->user_id,
                'seed_send_consent_id' => $consent->id,
                'approved_by_admin_id' => $admin->id,
                'status' => SeedSendCampaign::STATUS_QUEUED,
                'target_scope' => $consent->scope,
                'provider' => (string) ($eligibility['provider'] ?? config('seed_send.provider.default', 'log')),
                'target_count' => count($emails),
                'credits_reserved' => $creditsReserved,
            ]);

            $now = now();
            $rows = [];
            foreach ($emails as $email) {
                $rows[] = [
                    'campaign_id' => $campaign->id,
                    'email' => $email,
                    'email_hash' => hash('sha256', $email),
                    'status' => \App\Models\SeedSendRecipient::STATUS_PENDING,
                    'attempt_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                \App\Models\SeedSendRecipient::query()->insert($chunk);
            }

            $job->addLog('seed_send_campaign_queued', 'SG6 campaign queued by admin.', [
                'seed_send_campaign_id' => $campaign->id,
                'target_count' => $campaign->target_count,
                'provider' => $campaign->provider,
                'credits_reserved' => $campaign->credits_reserved,
            ], $admin->id);

            AdminAuditLogger::log('seed_send_campaign_started', $campaign, [
                'verification_job_id' => $job->id,
                'target_count' => $campaign->target_count,
            ]);

            DB::afterCommit(static fn () => DispatchSeedSendCampaignJob::dispatch($campaign->id));

            return $campaign;
        });
    }

    public function pauseCampaign(SeedSendCampaign $campaign, User $admin, ?string $reason = null): SeedSendCampaign
    {
        if (! in_array($campaign->status, [SeedSendCampaign::STATUS_QUEUED, SeedSendCampaign::STATUS_RUNNING], true)) {
            throw new RuntimeException('Campaign is not in a pausable state.');
        }

        $campaign->update([
            'status' => SeedSendCampaign::STATUS_PAUSED,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);

        $campaign->job?->addLog('seed_send_campaign_paused', 'SG6 campaign paused by admin.', [
            'seed_send_campaign_id' => $campaign->id,
            'reason' => $reason,
        ], $admin->id);

        AdminAuditLogger::log('seed_send_campaign_paused', $campaign, [
            'reason' => $reason,
        ]);

        return $campaign->refresh();
    }

    public function resumeCampaign(SeedSendCampaign $campaign, User $admin): SeedSendCampaign
    {
        if ($campaign->status !== SeedSendCampaign::STATUS_PAUSED) {
            throw new RuntimeException('Only paused campaigns can be resumed.');
        }

        $campaign->update([
            'status' => SeedSendCampaign::STATUS_QUEUED,
            'paused_at' => null,
            'pause_reason' => null,
        ]);

        $campaign->job?->addLog('seed_send_campaign_resumed', 'SG6 campaign resumed by admin.', [
            'seed_send_campaign_id' => $campaign->id,
        ], $admin->id);

        AdminAuditLogger::log('seed_send_campaign_resumed', $campaign);

        DispatchSeedSendCampaignJob::dispatch($campaign->id);

        return $campaign->refresh();
    }

    private function resolveScope(?string $scope): string
    {
        $scope = strtolower(trim((string) ($scope ?: config('seed_send.target_scope.default', 'full_list'))));
        $allowed = config('seed_send.target_scope.allowed', ['full_list']);
        if (! is_array($allowed) || ! in_array($scope, $allowed, true)) {
            return 'full_list';
        }

        return $scope;
    }
}
