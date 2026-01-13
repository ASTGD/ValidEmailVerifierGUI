<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrderStorage
{
    public function disk(): string
    {
        $disk = config('verifier.storage_disk');

        return $disk ?: (string) config('filesystems.default');
    }

    public function inputKey(VerificationOrder $order, ?string $extension = null): string
    {
        $extension = strtolower($extension ?: 'csv');

        return sprintf('orders/%s/%s/input.%s', $order->user_id, $order->id, $extension);
    }

    public function storeInput(UploadedFile $file, VerificationOrder $order, ?string $disk = null, ?string $key = null): array
    {
        $disk = $disk ?: $this->disk();
        $extension = $file->getClientOriginalExtension() ?: 'csv';
        $key = $key ?: $this->inputKey($order, $extension);

        Storage::disk($disk)->putFileAs(
            dirname($key),
            $file,
            basename($key)
        );

        return [$disk, $key];
    }

    public function moveToJob(VerificationOrder $order, VerificationJob $job, JobStorage $jobStorage): void
    {
        $disk = $order->input_disk;
        $source = $order->input_key;

        if (! $disk || ! $source) {
            return;
        }

        $destination = $jobStorage->inputKey($job);

        if ($disk !== $jobStorage->disk()) {
            Storage::disk($disk)->copy($source, $destination);
            Storage::disk($disk)->delete($source);
            return;
        }

        Storage::disk($disk)->move($source, $destination);
    }
}
