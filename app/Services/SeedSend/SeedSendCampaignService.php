<?php

namespace App\Services\SeedSend;

use App\Enums\VerificationJobStatus;
use App\Jobs\DispatchSeedSendCampaignJob;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\SeedSendRecipient;
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
        private SeedSendEligibility $eligibility,
        private SeedSendCreditLedgerService $creditLedgerService
    ) {}

    public function requestConsent(VerificationJob $job, User $user, ?string $scope = null): SeedSendConsent
    {
        $scope = $this->resolveScope($scope);

        $existing = SeedSendConsent::query()
            ->where('verification_job_id', $job->id)
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->whereIn('status', [SeedSendConsent::STATUS_REQUESTED, SeedSendConsent::STATUS_APPROVED])
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
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
            'consent_text_snapshot' => (string) config('seed_send.consent.text', ''),
            'consented_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('seed_send.consent.expiry_days', 30))),
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

        if ($this->isConsentExpired($consent)) {
            throw new RuntimeException('SG6 consent has expired and must be requested again.');
        }

        if ($consent->status === SeedSendConsent::STATUS_REVOKED || $consent->revoked_at !== null) {
            throw new RuntimeException('Revoked SG6 consent cannot be approved.');
        }

        $consent->update([
            'status' => SeedSendConsent::STATUS_APPROVED,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'revoked_at' => null,
            'revoked_by_admin_id' => null,
            'revocation_reason' => null,
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

        if ($this->isConsentExpired($consent)) {
            throw new RuntimeException('SG6 consent has expired and must be renewed.');
        }

        if ($consent->status === SeedSendConsent::STATUS_REVOKED || $consent->revoked_at !== null) {
            throw new RuntimeException('SG6 consent is revoked and cannot be used.');
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
                    'status' => SeedSendRecipient::STATUS_PENDING,
                    'attempt_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                SeedSendRecipient::query()->insert($chunk);
            }

            $this->creditLedgerService->reserveForCampaign($campaign);

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

    public function cancelCampaign(SeedSendCampaign $campaign, User $admin, ?string $reason = null): SeedSendCampaign
    {
        if (in_array($campaign->status, [
            SeedSendCampaign::STATUS_COMPLETED,
            SeedSendCampaign::STATUS_FAILED,
            SeedSendCampaign::STATUS_CANCELLED,
        ], true)) {
            throw new RuntimeException('Campaign is already in a terminal state.');
        }

        $this->transitionCampaignToCancelledState($campaign, 'campaign_cancelled', $reason);

        $campaign->refresh();

        $campaign->job?->addLog('seed_send_campaign_cancelled', 'SG6 campaign cancelled by admin.', [
            'seed_send_campaign_id' => $campaign->id,
            'reason' => $reason,
        ], $admin->id);

        AdminAuditLogger::log('seed_send_campaign_cancelled', $campaign, [
            'reason' => $reason,
        ]);

        return $campaign;
    }

    public function retryDeferredOrFailedRecipients(SeedSendCampaign $campaign, User $admin, int $maxRecipients = 500): int
    {
        if (in_array($campaign->status, [SeedSendCampaign::STATUS_CANCELLED, SeedSendCampaign::STATUS_FAILED], true)) {
            throw new RuntimeException('Cannot retry recipients for cancelled or failed campaigns.');
        }

        $maxRecipients = max(1, $maxRecipients);

        $recipientIds = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [SeedSendRecipient::STATUS_DEFERRED, SeedSendRecipient::STATUS_FAILED])
            ->orderBy('id')
            ->limit($maxRecipients)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($recipientIds === []) {
            return 0;
        }

        SeedSendRecipient::query()
            ->whereIn('id', $recipientIds)
            ->update([
                'status' => SeedSendRecipient::STATUS_PENDING,
                'provider_message_id' => null,
                'provider_payload' => null,
                'evidence_payload' => null,
                'updated_at' => now(),
            ]);

        $campaign->update([
            'status' => SeedSendCampaign::STATUS_QUEUED,
            'finished_at' => null,
            'paused_at' => null,
            'pause_reason' => null,
            'failure_reason' => null,
        ]);

        DispatchSeedSendCampaignJob::dispatch($campaign->id);

        $campaign->job?->addLog('seed_send_campaign_retry_subset', 'SG6 deferred/failed recipients requeued by admin.', [
            'seed_send_campaign_id' => $campaign->id,
            'recipient_count' => count($recipientIds),
        ], $admin->id);

        AdminAuditLogger::log('seed_send_campaign_retry_subset', $campaign, [
            'recipient_count' => count($recipientIds),
        ]);

        return count($recipientIds);
    }

    public function revokeConsent(SeedSendConsent $consent, User $admin, ?string $reason = null): SeedSendConsent
    {
        $activeCampaignIds = [];

        DB::transaction(function () use ($consent, $admin, $reason, &$activeCampaignIds): void {
            $consent->refresh();
            $consent->update([
                'status' => SeedSendConsent::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by_admin_id' => $admin->id,
                'revocation_reason' => $reason,
            ]);

            $activeCampaigns = SeedSendCampaign::query()
                ->where('seed_send_consent_id', $consent->id)
                ->whereIn('status', [
                    SeedSendCampaign::STATUS_QUEUED,
                    SeedSendCampaign::STATUS_RUNNING,
                    SeedSendCampaign::STATUS_PAUSED,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($activeCampaigns as $campaign) {
                $this->transitionCampaignToCancelledState(
                    $campaign,
                    'consent_revoked',
                    $reason ?: 'consent_revoked'
                );
                $activeCampaignIds[] = (string) $campaign->id;
            }
        });

        $consent = $consent->refresh();

        $consent->job?->addLog('seed_send_consent_revoked', 'SG6 consent revoked by admin.', [
            'seed_send_consent_id' => $consent->id,
            'reason' => $reason,
            'stopped_campaign_ids' => $activeCampaignIds,
        ], $admin->id);

        foreach ($activeCampaignIds as $campaignId) {
            $consent->job?->addLog('seed_send_campaign_cancelled', 'SG6 campaign stopped because consent was revoked.', [
                'seed_send_campaign_id' => $campaignId,
                'reason' => 'consent_revoked',
            ], $admin->id);

            AdminAuditLogger::log('seed_send_campaign_cancelled', null, [
                'seed_send_campaign_id' => $campaignId,
                'seed_send_consent_id' => $consent->id,
                'verification_job_id' => $consent->verification_job_id,
                'reason' => 'consent_revoked',
            ]);
        }

        AdminAuditLogger::log('seed_send_consent_revoked', $consent, [
            'verification_job_id' => $consent->verification_job_id,
            'reason' => $reason,
            'stopped_campaign_ids' => $activeCampaignIds,
        ]);

        return $consent;
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

    private function isConsentExpired(SeedSendConsent $consent): bool
    {
        return $consent->expires_at !== null && $consent->expires_at->lte(now());
    }

    public function cancelCampaignForSafety(SeedSendCampaign $campaign, string $reason): SeedSendCampaign
    {
        if (in_array($campaign->status, [
            SeedSendCampaign::STATUS_COMPLETED,
            SeedSendCampaign::STATUS_FAILED,
            SeedSendCampaign::STATUS_CANCELLED,
        ], true)) {
            return $campaign;
        }

        $this->transitionCampaignToCancelledState($campaign, $reason, $reason);
        $campaign->refresh();

        $campaign->job?->addLog('seed_send_campaign_cancelled', 'SG6 campaign stopped by dispatch safety guard.', [
            'seed_send_campaign_id' => $campaign->id,
            'reason' => $reason,
        ]);

        AdminAuditLogger::log('seed_send_campaign_cancelled', $campaign, [
            'reason' => $reason,
            'source' => 'dispatch_guard',
        ]);

        return $campaign;
    }

    private function transitionCampaignToCancelledState(SeedSendCampaign $campaign, string $reasonCode, ?string $note = null): void
    {
        DB::transaction(function () use ($campaign, $reasonCode, $note): void {
            SeedSendRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', SeedSendRecipient::STATUS_PENDING)
                ->update([
                    'status' => SeedSendRecipient::STATUS_FAILED,
                    'last_event_at' => now(),
                    'updated_at' => now(),
                    'evidence_payload' => [
                        'reason' => $reasonCode,
                        'note' => $note,
                    ],
                ]);

            SeedSendRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', [
                    SeedSendRecipient::STATUS_DISPATCHING,
                    SeedSendRecipient::STATUS_DISPATCHED,
                ])
                ->update([
                    'status' => SeedSendRecipient::STATUS_DEFERRED,
                    'last_event_at' => now(),
                    'updated_at' => now(),
                    'evidence_payload' => [
                        'reason' => $reasonCode,
                        'note' => $note,
                    ],
                ]);

            $totals = SeedSendRecipient::query()
                ->selectRaw('status, count(*) as count')
                ->where('campaign_id', $campaign->id)
                ->groupBy('status')
                ->pluck('count', 'status');

            $delivered = (int) ($totals[SeedSendRecipient::STATUS_DELIVERED] ?? 0);
            $bounced = (int) ($totals[SeedSendRecipient::STATUS_BOUNCED] ?? 0);
            $deferred = (int) ($totals[SeedSendRecipient::STATUS_DEFERRED] ?? 0);
            $dispatched = (int) ($totals[SeedSendRecipient::STATUS_DISPATCHED] ?? 0);
            $failed = (int) ($totals[SeedSendRecipient::STATUS_FAILED] ?? 0);
            $sentCount = $dispatched + $delivered + $bounced + $deferred + $failed;

            $campaign->refresh();
            $campaign->update([
                'status' => SeedSendCampaign::STATUS_CANCELLED,
                'finished_at' => now(),
                'paused_at' => now(),
                'pause_reason' => $note,
                'failure_reason' => $reasonCode,
                'sent_count' => $sentCount,
                'delivered_count' => $delivered,
                'bounced_count' => $bounced,
                'deferred_count' => $deferred,
            ]);

            $this->creditLedgerService->settleCancellation($campaign->refresh());
        });
    }
}
