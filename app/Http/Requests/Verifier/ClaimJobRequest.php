<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ClaimJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'engine_server_id' => ['nullable', 'integer', 'exists:engine_servers,id'],
            'lease_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
        ];
    }
}
