<?php

namespace App\Services;

use App\Models\CheckoutIntent;
use App\Models\VerificationJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CheckoutStorage
{
    public function disk(): string
    {
        $disk = config('verifier.storage_disk');

        return $disk ?: (string) config('filesystems.default');
    }

    public function tempKey(CheckoutIntent $intent, string $extension): string
    {
        $extension = strtolower($extension) ?: 'csv';

        return sprintf('temp/%s/input.%s', $intent->id, $extension);
    }

    public function storeTemp(UploadedFile $file, CheckoutIntent $intent): array
    {
        $disk = $this->disk();
        $extension = $file->getClientOriginalExtension() ?: 'csv';
        $key = $this->tempKey($intent, $extension);

        Storage::disk($disk)->putFileAs(
            dirname($key),
            $file,
            basename($key)
        );

        return [$disk, $key];
    }

    public function moveToJob(CheckoutIntent $intent, VerificationJob $job, JobStorage $jobStorage): void
    {
        $disk = $intent->temp_disk;
        $source = $intent->temp_key;
        $destination = $jobStorage->inputKey($job);

        if ($disk !== $jobStorage->disk()) {
            Storage::disk($disk)->copy($source, $destination);
            Storage::disk($disk)->delete($source);
            return;
        }

        Storage::disk($disk)->move($source, $destination);
    }
}
