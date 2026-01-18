<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ChunkCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'output_disk' => ['nullable', 'string', 'max:64'],
            'valid_key' => ['required', 'string', 'max:1024'],
            'invalid_key' => ['required', 'string', 'max:1024'],
            'risky_key' => ['required', 'string', 'max:1024'],
            'email_count' => ['nullable', 'integer', 'min:0'],
            'valid_count' => ['nullable', 'integer', 'min:0'],
            'invalid_count' => ['nullable', 'integer', 'min:0'],
            'risky_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
