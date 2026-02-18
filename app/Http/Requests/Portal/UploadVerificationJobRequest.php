<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

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

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.$maxKb],
        ];
    }
}
