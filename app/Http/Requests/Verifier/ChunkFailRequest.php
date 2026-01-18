<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ChunkFailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'error_message' => ['nullable', 'string', 'max:2000'],
            'retryable' => ['required', 'boolean'],
        ];
    }
}
