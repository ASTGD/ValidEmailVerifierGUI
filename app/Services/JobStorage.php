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

    public function finalResultKey(VerificationJob $job, string $type, string $extension = 'csv'): string
    {
        $type = strtolower($type);
        $extension = strtolower($extension ?: 'csv');
        $prefix = trim((string) config('engine.result_prefix', 'results/jobs'), '/');

        return sprintf('%s/%s/%s.%s', $prefix, $job->id, $type, $extension);
    }

    public function cachedResultKey(VerificationJob $job, string $type, string $extension = 'csv'): string
    {
        $type = strtolower($type);
        $extension = strtolower($extension ?: 'csv');
        $prefix = trim((string) config('engine.result_prefix', 'results/jobs'), '/');

        return sprintf('%s/%s/cached-%s.%s', $prefix, $job->id, $type, $extension);
    }

    public function cacheMissKey(VerificationJob $job): string
    {
        $prefix = trim((string) config('engine.result_prefix', 'results/jobs'), '/');

        return sprintf('%s/%s/cache-miss/emails.txt', $prefix, $job->id);
    }

    public function reportKey(VerificationJob $job): string
    {
        return sprintf('results/%s/%s/report.json', $job->user_id, $job->id);
    }

    public function chunkInputKey(VerificationJob $job, int $chunkNo, string $extension = 'txt'): string
    {
        $extension = strtolower($extension ?: 'txt');
        $prefix = trim((string) config('engine.chunk_inputs_prefix', 'chunks'), '/');

        return sprintf('%s/%s/%s/input.%s', $prefix, $job->id, $chunkNo, $extension);
    }

    public function chunkOutputKey(VerificationJob $job, int $chunkNo, string $type, string $extension = 'csv'): string
    {
        $extension = strtolower($extension ?: 'csv');
        $type = strtolower($type);
        $prefix = trim((string) config('engine.chunk_outputs_prefix', 'results/chunks'), '/');

        return sprintf('%s/%s/%s/%s.%s', $prefix, $job->id, $chunkNo, $type, $extension);
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

    public function storeSingleInput(string $email, VerificationJob $job, ?string $disk = null, ?string $key = null): array
    {
        $disk = $disk ?: $this->disk();
        $key = $key ?: $this->inputKey($job);
        $payload = trim($email);

        Storage::disk($disk)->put($key, $payload === '' ? '' : $payload."\n");

        return [$disk, $key];
    }
}
