<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class EngineHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server' => ['required', 'array'],
            'server.name' => ['required', 'string', 'max:255'],
            'server.ip_address' => ['required', 'string', 'max:64', 'ip'],
            'server.environment' => ['nullable', 'string', 'max:64'],
            'server.region' => ['nullable', 'string', 'max:64'],
            'server.meta' => ['nullable', 'array'],
        ];
    }
}
