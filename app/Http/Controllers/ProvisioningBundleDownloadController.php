<?php

namespace App\Http\Controllers;

use App\Models\EngineServerProvisioningBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProvisioningBundleDownloadController
{
    public function __invoke(Request $request, string $bundle, string $file): Response
    {
        $bundleRecord = EngineServerProvisioningBundle::query()
            ->where('bundle_uuid', $bundle)
            ->firstOrFail();

        if ($bundleRecord->isExpired()) {
            abort(410, 'Provisioning bundle expired.');
        }

        if (! in_array($file, ['install.sh', 'worker.env'], true)) {
            abort(404);
        }

        $disk = (string) config('engine.worker_provisioning_disk', 'local');
        $key = $file === 'install.sh' ? $bundleRecord->script_key : $bundleRecord->env_key;

        if (! Storage::disk($disk)->exists($key)) {
            abort(404);
        }

        return Storage::disk($disk)->download($key, $file, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
