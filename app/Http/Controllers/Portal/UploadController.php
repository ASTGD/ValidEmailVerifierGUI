<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VerificationJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UploadVerificationJobRequest;
use App\Jobs\PrepareVerificationJob;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(UploadVerificationJobRequest $request, JobStorage $storage)
    {
        $user = $request->user();

        $rateKey = 'portal-upload|'.$user->id;
        $maxAttempts = (int) config('verifier.portal_upload_max_attempts', 10);
        $decaySeconds = (int) config('verifier.portal_upload_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()->withErrors([
                'file' => __('Too many upload attempts. Try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        RateLimiter::hit($rateKey, $decaySeconds);

        if (
            config('verifier.require_active_subscription')
            && method_exists($user, 'subscribed')
            && ! $user->subscribed('default')
        ) {
            return back()->withErrors([
                'file' => __('An active subscription is required to upload lists.'),
            ]);
        }

        try {
            $this->authorize('create', VerificationJob::class);
        } catch (AuthorizationException) {
            return back()->withErrors([
                'file' => __('You are not allowed to upload lists.'),
            ]);
        }

        $file = $request->file('file');

        $job = new VerificationJob([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => $file->getClientOriginalName(),
        ]);

        $job->id = (string) Str::uuid();
        $job->input_disk = $storage->disk();
        $job->input_key = $storage->inputKey($job);

        $storage->storeInput($file, $job, $job->input_disk, $job->input_key);

        $job->save();
        $job->addLog('created', 'Job created via customer portal upload.', [
            'original_filename' => $job->original_filename,
        ], $user->id);

        PrepareVerificationJob::dispatch($job->id);

        return redirect()
            ->route('portal.jobs.show', ['job' => $job->id])
            ->with('status', __('Upload received. Verification job created.'));
    }
}
