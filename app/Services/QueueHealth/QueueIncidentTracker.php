<?php

namespace App\Services\QueueHealth;

use App\Models\QueueIncident;

class QueueIncidentTracker
{
    /**
     * @param  array{issues?: array<int, array{key?: string, severity?: string, title?: string, detail?: string, lane?: string|null}>}  $report
     */
    public function sync(array $report): void
    {
        $now = now();

        $issues = collect((array) ($report['issues'] ?? []))
            ->filter(fn ($issue): bool => is_array($issue) && filled($issue['key'] ?? null))
            ->map(fn (array $issue): array => [
                'key' => (string) $issue['key'],
                'severity' => strtolower((string) ($issue['severity'] ?? 'warning')),
                'title' => (string) ($issue['title'] ?? 'Queue incident'),
                'detail' => (string) ($issue['detail'] ?? ''),
                'lane' => $issue['lane'] ?? null,
            ]);

        $activeByKey = QueueIncident::query()
            ->whereNull('resolved_at')
            ->get()
            ->keyBy('issue_key');

        $seenKeys = [];

        /** @var array{key: string, severity: string, title: string, detail: string, lane: string|null} $issue */
        foreach ($issues as $issue) {
            $seenKeys[] = $issue['key'];
            $existing = $activeByKey->get($issue['key']);

            if ($existing) {
                $existing->update([
                    'severity' => $issue['severity'],
                    'title' => $issue['title'],
                    'detail' => $issue['detail'],
                    'lane' => $issue['lane'],
                    'last_detected_at' => $now,
                    'meta' => $this->mergeMeta($existing->meta, ['updated_by' => 'ops:queue-health']),
                ]);

                continue;
            }

            QueueIncident::create([
                'issue_key' => $issue['key'],
                'severity' => $issue['severity'],
                'status' => 'detected',
                'lane' => $issue['lane'],
                'title' => $issue['title'],
                'detail' => $issue['detail'],
                'first_detected_at' => $now,
                'last_detected_at' => $now,
                'meta' => [
                    'source' => 'ops:queue-health',
                ],
            ]);
        }

        if ($activeByKey->isEmpty()) {
            return;
        }

        QueueIncident::query()
            ->whereNull('resolved_at')
            ->when($seenKeys !== [], fn ($query) => $query->whereNotIn('issue_key', $seenKeys))
            ->update([
                'status' => 'resolved',
                'resolved_at' => $now,
                'last_detected_at' => $now,
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function mergeMeta(?array $existing, array $extra): array
    {
        $meta = is_array($existing) ? $existing : [];

        return array_merge($meta, $extra);
    }
}
