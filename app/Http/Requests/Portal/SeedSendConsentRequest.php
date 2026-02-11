<?php

namespace App\Http\Requests\Portal;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeedSendConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var VerificationJob|null $job */
        $job = $this->route('job');
        $user = $this->user();

        if (! $job || ! $user) {
            return false;
        }

        if ((string) $job->user_id !== (string) $user->id) {
            return false;
        }

        return $job->status === VerificationJobStatus::Completed;
    }

    public function rules(): array
    {
        return [
            'scope' => [
                'nullable',
                'string',
                Rule::in((array) config('seed_send.target_scope.allowed', ['full_list'])),
            ],
        ];
    }
}
