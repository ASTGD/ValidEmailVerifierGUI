<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class VerificationJobDownloadController
{
    use AuthorizesRequests;

    public function __invoke(VerificationJob $job, JobStorage $storage)
    {
        $this->authorize('download', $job);

        if ($job->status !== VerificationJobStatus::Completed) {
            abort(403);
        }

        if (! $job->output_key) {
            abort(404);
        }

        $disk = $job->output_disk ?: $storage->disk();

        if (! Storage::disk($disk)->exists($job->output_key)) {
            abort(404);
        }

        return Storage::disk($disk)->download($job->output_key, basename($job->output_key));
    }
}
