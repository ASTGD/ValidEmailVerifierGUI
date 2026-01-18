<?php

namespace App\Contracts;

interface EngineStorageUrlSigner
{
    public function temporaryDownloadUrl(string $disk, string $key, int $expirySeconds): string;

    public function temporaryUploadUrl(string $disk, string $key, int $expirySeconds, ?string $contentType = null): string;
}
