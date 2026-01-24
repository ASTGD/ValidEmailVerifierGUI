<?php

namespace App\Http\Requests\Portal;

use App\Enums\VerificationMode;
use App\Support\EnhancedModeGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadVerificationJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMb = (int) config('verifier.checkout_upload_max_mb', 10);
        $maxKb = max(1, $maxMb * 1024);
        $modes = array_map(static fn (VerificationMode $mode) => $mode->value, VerificationMode::cases());

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.$maxKb],
            'verification_mode' => ['nullable', 'string', Rule::in($modes)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mode = $this->input('verification_mode');

            if ($mode === VerificationMode::Enhanced->value && ! EnhancedModeGate::canUse($this->user())) {
                $validator->errors()->add('verification_mode', EnhancedModeGate::message($this->user()));
            }
        });
    }
}
