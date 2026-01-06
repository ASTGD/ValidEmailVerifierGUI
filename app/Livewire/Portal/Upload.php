<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

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

        $this->reset('file');

        return redirect()->route('portal.jobs.show', $job);
    }

    public function render()
    {
        return view('livewire.portal.upload');
    }
}
