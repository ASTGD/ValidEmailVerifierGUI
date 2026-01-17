<?php

namespace App\Http\Controllers\Api\Verifier;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class VerifierStorageUploadController
{
    public function __invoke(Request $request): Response
    {
        $disk = (string) $request->query('disk');
        $key = (string) $request->query('key');

        if ($disk === '' || $key === '') {
            return response('', 404);
        }

        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            return response('', 404);
        }

        $content = $request->getContent(true);

        if (is_resource($content)) {
            Storage::disk($disk)->put($key, $content);
        } else {
            Storage::disk($disk)->put($key, $request->getContent());
        }

        return response()->noContent();
    }
}
