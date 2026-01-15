<?php

namespace App\Services;

use App\Models\VerificationJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class JobStorage
{
    public function disk(): string
    {
        $disk = config('verifier.storage_disk');

        return $disk ?: (string) config('filesystems.default');
    }

    public function inputKey(VerificationJob $job): string
    {
        return sprintf('uploads/%s/%s/input.csv', $job->user_id, $job->id);
    }

    public function outputKey(VerificationJob $job): string
    {
        return sprintf('results/%s/%s/cleaned.csv', $job->user_id, $job->id);
    }

    public function reportKey(VerificationJob $job): string
    {
        return sprintf('results/%s/%s/report.json', $job->user_id, $job->id);
    }

    public function chunkInputKey(VerificationJob $job, int $chunkNo, string $extension = 'txt'): string
    {
        $extension = strtolower($extension ?: 'txt');

        return sprintf('chunks/%s/%s/input.%s', $job->id, $chunkNo, $extension);
    }

    public function storeInput(UploadedFile $file, VerificationJob $job, ?string $disk = null, ?string $key = null): array
    {
        $disk = $disk ?: $this->disk();
        $key = $key ?: $this->inputKey($job);

        Storage::disk($disk)->putFileAs(
            dirname($key),
            $file,
            basename($key)
        );

        return [$disk, $key];
    }
}
