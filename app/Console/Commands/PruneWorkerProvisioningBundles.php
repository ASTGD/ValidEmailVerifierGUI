<?php

namespace App\Console\Commands;

use App\Models\EngineServerProvisioningBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneWorkerProvisioningBundles extends Command
{
    protected $signature = 'prune:worker-provisioning-bundles';

    protected $description = 'Delete expired worker provisioning bundle files and records.';

    public function handle(): int
    {
        $disk = (string) config('engine.worker_provisioning_disk', 'local');
        $expiredBundles = EngineServerProvisioningBundle::query()
            ->where('expires_at', '<=', now())
            ->get();

        $deleted = 0;

        foreach ($expiredBundles as $bundle) {
            Storage::disk($disk)->delete([$bundle->env_key, $bundle->script_key]);
            $bundle->delete();
            $deleted++;
        }

        $this->info("Pruned {$deleted} provisioning bundles.");

        return self::SUCCESS;
    }
}
