<?php

namespace App\Http\Requests\Internal;

use App\Models\EngineWorkerPool;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EnginePoolUpsertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('engine_worker_pools', 'slug')->ignore($this->route('enginePool')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'provider_profiles' => ['required', 'array'],
            'provider_profiles.generic' => ['required', 'string', Rule::in(EngineWorkerPool::PROFILES)],
            'provider_profiles.gmail' => ['required', 'string', Rule::in(EngineWorkerPool::PROFILES)],
            'provider_profiles.microsoft' => ['required', 'string', Rule::in(EngineWorkerPool::PROFILES)],
            'provider_profiles.yahoo' => ['required', 'string', Rule::in(EngineWorkerPool::PROFILES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $providerProfiles = is_array($this->input('provider_profiles'))
            ? $this->input('provider_profiles')
            : [];

        foreach (EngineWorkerPool::PROVIDERS as $provider) {
            $value = strtolower(trim((string) ($providerProfiles[$provider] ?? '')));
            if (! in_array($value, EngineWorkerPool::PROFILES, true)) {
                $value = 'standard';
            }
            $providerProfiles[$provider] = $value;
        }

        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description') ? (string) $this->input('description') : null,
            'is_active' => $this->boolean('is_active', true),
            'provider_profiles' => $providerProfiles,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $requestId = $this->requestId();

        throw new HttpResponseException(response()->json([
            'error_code' => 'validation_failed',
            'message' => 'Validation failed.',
            'request_id' => $requestId,
            'errors' => $validator->errors()->toArray(),
        ], 422, [
            'X-Request-Id' => $requestId,
        ]));
    }

    private function requestId(): string
    {
        $existing = trim((string) $this->header('X-Request-Id', ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }
}
