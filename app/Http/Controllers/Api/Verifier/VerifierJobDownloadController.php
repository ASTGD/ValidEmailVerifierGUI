<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Support\Facades\Storage;

class VerifierJobDownloadController
{
    public function __invoke(VerificationJob $job, JobStorage $storage)
    {
        if (! $job->input_key) {
            abort(404);
        }

        $disk = $job->input_disk ?: $storage->disk();

        if (! Storage::disk($disk)->exists($job->input_key)) {
            abort(404);
        }

        $filename = $job->original_filename ?: basename($job->input_key);

        return Storage::disk($disk)->download($job->input_key, $filename);
    }
}
