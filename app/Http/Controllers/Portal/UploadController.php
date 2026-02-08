<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UploadVerificationJobRequest;
use App\Jobs\PrepareVerificationJob;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use App\Services\QueueHealth\QueueBackpressureGate;
use App\Support\EnhancedModeGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class UploadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        UploadVerificationJobRequest $request,
        JobStorage $storage,
        QueueBackpressureGate $backpressureGate
    ) {
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

        $backpressure = $backpressureGate->assessHeavySubmission();
        if ($backpressure['blocked']) {
            return back()->withErrors([
                'file' => __('Queue is under pressure. Please try again soon. :reason', [
                    'reason' => $backpressure['reason'],
                ]),
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
        $mode = data_get($request->validated(), 'verification_mode', VerificationMode::Standard->value);

        if ($mode === VerificationMode::Enhanced->value && ! EnhancedModeGate::canUse($user)) {
            return back()->withErrors([
                'verification_mode' => EnhancedModeGate::message($user),
            ]);
        }

        $job = new VerificationJob([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'verification_mode' => $mode,
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
        $job->addLog('verification_mode_set', 'Verification mode set at job creation.', [
            'from' => null,
            'to' => $mode,
            'actor_id' => $user->id,
        ], $user->id);

        try {
            if ((string) config('queue.default', 'sync') === 'sync') {
                PrepareVerificationJob::dispatchSync($job->id);
            } else {
                PrepareVerificationJob::dispatch($job->id);
            }
        } catch (Throwable $exception) {
            $job->update([
                'status' => VerificationJobStatus::Failed,
                'error_message' => 'Failed to enqueue verification job.',
                'failure_source' => VerificationJob::FAILURE_SOURCE_ENGINE,
                'failure_code' => 'enqueue_failed',
                'finished_at' => now(),
            ]);

            $job->addLog('enqueue_failed', 'Failed to dispatch prepare job.', [
                'error' => $exception->getMessage(),
            ], $user->id);

            return back()->withErrors([
                'file' => __('Failed to queue verification. Please try again shortly.'),
            ]);
        }

        return redirect()
            ->route('portal.jobs.show', ['job' => $job->id])
            ->with('status', __('Upload received. Verification job created.'));
    }
}
