<?php

namespace App\Console\Commands;

use App\Models\QueueIncident;
use App\Models\QueueRecoveryAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class QueueRecoverCommand extends Command
{
    protected $signature = 'ops:queue-recover
        {--lane= : Queue lane name (default, prepare, parse, smtp_probe, finalize, imports, cache_writeback)}
        {--strategy=requeue_failed : Recovery strategy}
        {--job= : Filter by failed job displayName/class}
        {--hours= : Replay failed jobs from this many recent hours}
        {--max= : Max failed jobs to replay in one run}
        {--reason= : Reason for the recovery action}
        {--dry-run : Preview only, do not replay}';

    protected $description = 'Perform safe queue recovery with lane/job filters and audit logging.';

    public function handle(): int
    {
        if (! (bool) config('queue_recovery.enabled', true)) {
            $this->error('Queue recovery is disabled by configuration.');

            return self::FAILURE;
        }

        $strategy = trim((string) $this->option('strategy'));
        $allowedStrategies = (array) config('queue_recovery.allow_strategies', ['requeue_failed']);

        if (! in_array($strategy, $allowedStrategies, true)) {
            $this->error(sprintf('Unsupported strategy "%s". Allowed: %s', $strategy, implode(', ', $allowedStrategies)));

            return self::FAILURE;
        }

        if ($strategy !== 'requeue_failed') {
            $this->error('Only "requeue_failed" is implemented in this phase.');

            return self::FAILURE;
        }

        $lane = $this->cleanNullableString($this->option('lane'));
        $allowedLanes = (array) config('queue_recovery.allow_lanes', []);
        if ($lane !== null && ! in_array($lane, $allowedLanes, true)) {
            $this->error(sprintf('Lane "%s" is not allowed. Allowed lanes: %s', $lane, implode(', ', $allowedLanes)));

            return self::FAILURE;
        }
        $laneQueue = null;
        if ($lane !== null) {
            $laneQueue = $this->resolveLaneQueue($lane);
            if ($laneQueue === null) {
                $this->error(sprintf(
                    'Lane "%s" has no queue mapping. Configure queue_health.lanes.%s.queue.',
                    $lane,
                    $lane
                ));

                return self::FAILURE;
            }
        }

        $jobClass = $this->cleanNullableString($this->option('job'));
        $hours = max(1, (int) ($this->option('hours') ?: config('queue_recovery.default_window_hours', 24)));
        $max = max(1, min(
            (int) ($this->option('max') ?: config('queue_recovery.max_replay_per_run', 100)),
            (int) config('queue_recovery.max_replay_per_run', 100)
        ));
        $dryRun = (bool) $this->option('dry-run');
        $reason = $this->cleanNullableString($this->option('reason'));

        $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');
        $query = DB::table($failedJobsTable)
            ->where('failed_at', '>=', now()->subHours($hours))
            ->orderBy('failed_at')
            ->limit($max * 2);

        if ($laneQueue !== null) {
            $query->where('queue', $laneQueue);
        }

        $rows = collect($query->get([
            'id',
            'uuid',
            'connection',
            'queue',
            'payload',
            'failed_at',
        ]));

        if ($jobClass !== null) {
            $rows = $rows->filter(function ($row) use ($jobClass): bool {
                $payload = json_decode((string) $row->payload, true);
                $displayName = strtolower(trim((string) ($payload['displayName'] ?? '')));
                $commandName = strtolower(trim((string) data_get($payload, 'data.commandName', '')));
                $needle = strtolower($jobClass);

                return str_contains($displayName, $needle) || str_contains($commandName, $needle);
            })->values();
        }

        $rows = $rows->take($max)->values();
        $targetCount = $rows->count();

        $processed = 0;
        $failed = 0;
        $errors = [];

        if (! $dryRun) {
            foreach ($rows as $row) {
                try {
                    Queue::connection((string) $row->connection)->pushRaw((string) $row->payload, (string) $row->queue);
                    DB::table($failedJobsTable)->where('id', $row->id)->delete();
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $errors[] = [
                        'id' => $row->id,
                        'queue' => $row->queue,
                        'error' => $exception->getMessage(),
                    ];
                }
            }
        }

        $status = $dryRun
            ? 'dry_run'
            : ($failed > 0 ? ($processed > 0 ? 'partial' : 'failed') : 'success');

        QueueRecoveryAction::create([
            'action_type' => 'queue_recovery',
            'strategy' => $strategy,
            'status' => $status,
            'lane' => $lane,
            'job_class' => $jobClass,
            'target_count' => $targetCount,
            'processed_count' => $dryRun ? 0 : $processed,
            'failed_count' => $dryRun ? 0 : $failed,
            'dry_run' => $dryRun,
            'reason' => $reason,
            'meta' => [
                'hours' => $hours,
                'max' => $max,
                'lane_queue' => $laneQueue,
                'candidate_ids' => $rows->pluck('id')->take(20)->values()->all(),
                'errors' => array_slice($errors, 0, 10),
            ],
            'executed_at' => now(),
        ]);

        if (! $dryRun && $processed > 0) {
            QueueIncident::query()
                ->whereNull('resolved_at')
                ->when($lane !== null, fn ($query) => $query->where('lane', $lane))
                ->update([
                    'status' => 'mitigated',
                    'mitigated_at' => now(),
                ]);
        }

        $this->line(sprintf(
            'Recovery %s: strategy=%s lane=%s job=%s target=%d processed=%d failed=%d',
            strtoupper($status),
            $strategy,
            $lane ?: 'any',
            $jobClass ?: 'any',
            $targetCount,
            $processed,
            $failed
        ));

        return ($failed > 0 && ! $dryRun) ? self::FAILURE : self::SUCCESS;
    }

    private function cleanNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function resolveLaneQueue(string $lane): ?string
    {
        $queue = trim((string) config("queue_health.lanes.{$lane}.queue", ''));

        return $queue === '' ? null : $queue;
    }
}
