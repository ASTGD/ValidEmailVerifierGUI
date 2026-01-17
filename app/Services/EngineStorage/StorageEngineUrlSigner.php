<?php

namespace App\Services\EngineStorage;

use App\Contracts\EngineStorageUrlSigner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class StorageEngineUrlSigner implements EngineStorageUrlSigner
{
    public function temporaryDownloadUrl(string $disk, string $key, int $expirySeconds): string
    {
        $filesystem = Storage::disk($disk);

        if (method_exists($filesystem, 'temporaryUrl')) {
            try {
                return $filesystem->temporaryUrl($key, now()->addSeconds($expirySeconds));
            } catch (RuntimeException) {
                // Fall back to a signed app URL for local/testing disks.
            }
        }

        return URL::temporarySignedRoute(
            'api.verifier.storage.download',
            now()->addSeconds($expirySeconds),
            [
                'disk' => $disk,
                'key' => $key,
            ]
        );
    }

    public function temporaryUploadUrl(string $disk, string $key, int $expirySeconds, ?string $contentType = null): string
    {
        $filesystem = Storage::disk($disk);

        if (method_exists($filesystem, 'temporaryUploadUrl')) {
            $options = [];

            if ($contentType) {
                $options['ContentType'] = $contentType;
            }

            try {
                $url = $filesystem->temporaryUploadUrl($key, now()->addSeconds($expirySeconds), $options);
            } catch (RuntimeException) {
                $url = null;
            }

            if (is_array($url) && isset($url['url'])) {
                return $url['url'];
            }

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return URL::temporarySignedRoute(
            'api.verifier.storage.upload',
            now()->addSeconds($expirySeconds),
            [
                'disk' => $disk,
                'key' => $key,
            ]
        );
    }
}
