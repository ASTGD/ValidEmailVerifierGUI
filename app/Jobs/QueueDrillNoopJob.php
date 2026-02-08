<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueDrillNoopJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public string $lane, public string $label)
    {
        $connection = $this->connectionForLane($lane);

        $this->connection = $connection;
        $this->queue = (string) config("queue.connections.{$connection}.queue", $lane);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:'.$this->lane,
            'queue_drill:'.$this->label,
        ];
    }

    public function handle(): void
    {
        // Intentionally empty. This job exists only for queue drill batches.
    }

    private function connectionForLane(string $lane): string
    {
        return match ($lane) {
            'prepare' => 'redis_prepare',
            'parse' => 'redis_parse',
            'finalize' => 'redis_finalize',
            'imports' => 'redis_import',
            'cache_writeback' => 'redis_cache_writeback',
            default => 'redis',
        };
    }
}
