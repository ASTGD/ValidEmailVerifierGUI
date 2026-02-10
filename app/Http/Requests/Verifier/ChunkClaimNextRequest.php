<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ChunkClaimNextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'engine_server' => ['required', 'array'],
            'engine_server.name' => ['required', 'string', 'max:255'],
            'engine_server.ip_address' => ['required', 'string', 'max:64', 'ip'],
            'engine_server.environment' => ['nullable', 'string', 'max:64'],
            'engine_server.region' => ['nullable', 'string', 'max:64'],
            'engine_server.meta' => ['nullable', 'array'],
            'engine_server.meta.pool' => ['nullable', 'string', 'max:64'],
            'engine_server.meta.provider_affinity' => ['nullable', 'string', 'in:gmail,microsoft,yahoo,generic'],
            'engine_server.meta.trust_tier' => ['nullable', 'string', 'max:32'],
            'worker_id' => ['required', 'string', 'max:255'],
            'worker_capability' => ['nullable', 'string', 'in:screening,smtp_probe,all'],
            'lease_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }
}
