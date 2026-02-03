<?php

namespace App\Console\Commands;

use App\Models\EngineServerProvisioningBundle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

class PruneWorkerProvisioningBundles extends Command
{
    protected $signature = 'prune:worker-provisioning-bundles';

    protected $description = 'Delete expired worker provisioning bundles and revoke tokens.';

    public function handle(): int
    {
        $disk = (string) config('engine.worker_provisioning_disk', 'local');
        $expiredBundles = EngineServerProvisioningBundle::query()
            ->where('expires_at', '<=', now())
            ->get();

        $deleted = 0;

        foreach ($expiredBundles as $bundle) {
            if ($bundle->token_id) {
                PersonalAccessToken::query()->where('id', $bundle->token_id)->delete();
            }

            Storage::disk($disk)->delete([$bundle->env_key, $bundle->script_key]);
            $bundle->delete();
            $deleted++;
        }

        $this->info("Pruned {$deleted} provisioning bundles.");

        return self::SUCCESS;
    }
}
