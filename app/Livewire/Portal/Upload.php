<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
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

        if (config('verifier.require_active_subscription') && ! $user->subscribed()) {
            $this->addError('file', __('An active subscription is required to upload lists.'));

            return;
        }

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => $this->file->getClientOriginalName(),
        ]);

        [$disk, $key] = $storage->storeInput($this->file, $job);

        $job->update([
            'input_disk' => $disk,
            'input_key' => $key,
        ]);

        $this->reset('file');

        return redirect()->route('portal.jobs.show', $job);
    }

    public function render()
    {
        return view('livewire.portal.upload');
    }
}
