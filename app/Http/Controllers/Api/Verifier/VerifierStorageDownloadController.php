<?php

namespace App\Http\Controllers\Api\Verifier;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerifierStorageDownloadController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = (string) $request->query('disk');
        $key = (string) $request->query('key');

        if ($disk === '' || $key === '') {
            abort(404);
        }

        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            abort(404);
        }

        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($key)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($filesystem, $key) {
            $stream = $filesystem->readStream($key);
            if (! is_resource($stream)) {
                return;
            }

            while (! feof($stream)) {
                echo fread($stream, 1024 * 1024);
            }

            fclose($stream);
        }, basename($key));
    }
}
