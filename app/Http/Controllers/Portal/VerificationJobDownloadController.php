<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VerificationJobDownloadController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, VerificationJob $job, JobStorage $storage)
    {
        $this->authorize('download', $job);

        if ($job->status !== VerificationJobStatus::Completed) {
            abort(403);
        }

        $type = strtolower((string) $request->query('type', ''));
        $key = $this->resolveOutputKey($job, $type);

        if (! $key) {
            abort(404);
        }

        $disk = $job->output_disk ?: $storage->disk();

        if (! Storage::disk($disk)->exists($key)) {
            abort(404);
        }

        $filename = $this->downloadFilename($job, $key, $type);

        return Storage::disk($disk)->download($key, $filename);
    }

    private function resolveOutputKey(VerificationJob $job, string $type): ?string
    {
        return match ($type) {
            'valid' => $job->valid_key ?: $job->output_key,
            'invalid' => $job->invalid_key,
            'risky' => $job->risky_key,
            default => $job->output_key ?: $job->valid_key,
        };
    }

    private function downloadFilename(VerificationJob $job, string $key, string $type): string
    {
        if ($type !== '') {
            return sprintf('%s-%s.csv', $job->id, $type);
        }

        return basename($key);
    }
}
