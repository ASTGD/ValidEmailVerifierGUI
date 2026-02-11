<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendRecipient;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SeedSendEvidenceReportService
{
    /**
     * @return array{disk: string, key: string}
     */
    public function storeCampaignReport(SeedSendCampaign $campaign): array
    {
        $disk = trim((string) config('seed_send.reports.disk', $campaign->report_disk ?: $campaign->job?->output_disk ?: config('filesystems.default')));
        $prefix = trim((string) config('seed_send.reports.key_prefix', 'results/seed-send'), '/');
        $key = sprintf('%s/%s/%s/%s/evidence.csv', $prefix, $campaign->user_id, $campaign->verification_job_id, $campaign->id);

        if ($disk === '') {
            throw new RuntimeException('SG6 report disk is not configured.');
        }

        $stream = fopen('php://temp', 'w+');
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to initialize SG6 report stream.');
        }

        fputcsv($stream, [
            'email',
            'status',
            'attempt_count',
            'last_attempt_at',
            'last_event_at',
            'provider_message_id',
            'smtp_code',
            'enhanced_code',
            'evidence_reason',
        ]);

        SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->chunkById(500, function ($recipients) use ($stream): void {
                foreach ($recipients as $recipient) {
                    $evidence = is_array($recipient->evidence_payload) ? $recipient->evidence_payload : [];

                    fputcsv($stream, [
                        $recipient->email,
                        $recipient->status,
                        (int) $recipient->attempt_count,
                        $recipient->last_attempt_at?->toIso8601String(),
                        $recipient->last_event_at?->toIso8601String(),
                        $recipient->provider_message_id,
                        (string) ($evidence['smtp_code'] ?? ''),
                        (string) ($evidence['enhanced_code'] ?? ''),
                        (string) ($evidence['reason'] ?? $evidence['event_type'] ?? ''),
                    ]);
                }
            });

        rewind($stream);
        $writeResult = Storage::disk($disk)->writeStream($key, $stream);
        fclose($stream);

        if ($writeResult === false) {
            throw new RuntimeException('Failed to write SG6 evidence report.');
        }

        return [
            'disk' => $disk,
            'key' => $key,
        ];
    }
}
