<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class CompleteJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'output_key' => ['required', 'string', 'max:1024'],
            'output_disk' => ['nullable', 'string', 'max:64'],
            'total_emails' => ['nullable', 'integer', 'min:0'],
            'valid_count' => ['nullable', 'integer', 'min:0'],
            'invalid_count' => ['nullable', 'integer', 'min:0'],
            'risky_count' => ['nullable', 'integer', 'min:0'],
            'unknown_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
