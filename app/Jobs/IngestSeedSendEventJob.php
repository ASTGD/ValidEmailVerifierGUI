<?php

namespace App\Jobs;

use App\Services\SeedSend\SeedSendEventIngestor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestSeedSendEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(public string $provider, public array $payload)
    {
        $this->connection = 'redis_seed_send_events';
        $this->queue = (string) config('queue.connections.redis_seed_send_events.queue', 'seed_send_events');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $campaignId = trim((string) ($this->payload['campaign_id'] ?? 'unknown'));

        return [
            'lane:seed_send_events',
            'seed_send_campaign:'.$campaignId,
        ];
    }

    public function handle(SeedSendEventIngestor $ingestor): void
    {
        $ingestor->ingest($this->provider, $this->payload);
    }
}
