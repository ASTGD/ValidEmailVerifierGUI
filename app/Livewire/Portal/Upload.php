<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
class Upload extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public $file;

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function save(JobStorage $storage)
    {
        $this->validate();

        $user = Auth::user();

        $this->authorize('create', VerificationJob::class);

        $rateKey = 'portal-upload|'.$user->id;
        $maxAttempts = (int) config('verifier.portal_upload_max_attempts', 10);
        $decaySeconds = (int) config('verifier.portal_upload_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->addError('file', __('Too many upload attempts. Try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return;
        }

        RateLimiter::hit($rateKey, $decaySeconds);

        if (
            config('verifier.require_active_subscription')
            && method_exists($user, 'subscribed')
            && ! $user->subscribed('default')
        ) {
            $this->addError('file', __('An active subscription is required to upload lists.'));

            return;
        }

        $job = new VerificationJob([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => $this->file->getClientOriginalName(),
        ]);

        $job->id = (string) Str::uuid();
        $job->input_disk = $storage->disk();
        $job->input_key = $storage->inputKey($job);

        $storage->storeInput($this->file, $job, $job->input_disk, $job->input_key);

        $job->save();
        $job->addLog('created', 'Job created via customer portal upload.', [
            'original_filename' => $job->original_filename,
        ], $user->id);

        $this->reset('file');

        return redirect()->route('portal.jobs.show', $job);
    }

    public function render()
    {
        return view('livewire.portal.upload');
    }
}
