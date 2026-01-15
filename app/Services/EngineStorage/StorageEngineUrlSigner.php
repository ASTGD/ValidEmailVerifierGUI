<?php

namespace App\Services\EngineStorage;

use App\Contracts\EngineStorageUrlSigner;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StorageEngineUrlSigner implements EngineStorageUrlSigner
{
    public function temporaryDownloadUrl(string $disk, string $key, int $expirySeconds): string
    {
        $filesystem = Storage::disk($disk);

        if (method_exists($filesystem, 'temporaryUrl')) {
            return $filesystem->temporaryUrl($key, now()->addSeconds($expirySeconds));
        }

        throw new RuntimeException('Temporary download URLs are not supported for this disk.');
    }

    public function temporaryUploadUrl(string $disk, string $key, int $expirySeconds, ?string $contentType = null): string
    {
        $filesystem = Storage::disk($disk);

        if (method_exists($filesystem, 'temporaryUploadUrl')) {
            $options = [];

            if ($contentType) {
                $options['ContentType'] = $contentType;
            }

            $url = $filesystem->temporaryUploadUrl($key, now()->addSeconds($expirySeconds), $options);

            if (is_array($url) && isset($url['url'])) {
                return $url['url'];
            }

            return (string) $url;
        }

        throw new RuntimeException('Temporary upload URLs are not supported for this disk.');
    }
}
