<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Jobs\PrepareVerificationJob;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;

#[Layout('layouts.portal')]
class Upload extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public $file;
    public string $verification_mode = VerificationMode::Standard->value;

    protected function maxUploadKilobytes(): int
    {
        $maxMb = (int) config('verifier.checkout_upload_max_mb', 10);

        return max(1, $maxMb * 1024);
    }

    protected function rules(): array
    {
        $modes = array_map(static fn (VerificationMode $mode) => $mode->value, VerificationMode::cases());

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.$this->maxUploadKilobytes()],
            'verification_mode' => ['required', 'string', Rule::in($modes)],
        ];
    }

    public function save(JobStorage $storage)
    {
        $this->validate();
        $enhancedEnabled = (bool) config('engine.enhanced_mode_enabled', false);

        if (! $enhancedEnabled && $this->verification_mode === VerificationMode::Enhanced->value) {
            $this->addError('verification_mode', __('Enhanced mode is coming soon.'));

            return;
        }

        $user = Auth::user();

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

        try {
            $this->authorize('create', VerificationJob::class);
        } catch (AuthorizationException) {
            $this->addError('file', __('You are not allowed to upload lists.'));

            return;
        }

        $job = new VerificationJob([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'verification_mode' => $this->verification_mode,
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
        $job->addLog('verification_mode_set', 'Verification mode set at job creation.', [
            'from' => null,
            'to' => $this->verification_mode,
            'actor_id' => $user->id,
        ], $user->id);

        PrepareVerificationJob::dispatch($job->id);

        $this->reset('file');

        return $this->redirectRoute('portal.jobs.show', ['job' => $job->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.portal.upload');
    }
}
