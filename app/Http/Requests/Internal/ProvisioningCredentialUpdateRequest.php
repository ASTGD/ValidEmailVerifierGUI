<?php

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ProvisioningCredentialUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ghcr_username' => ['required', 'string', 'max:255'],
            'ghcr_token' => ['nullable', 'string', 'max:4096'],
            'clear_ghcr_token' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = trim((string) $this->input('ghcr_username', ''));
        $token = trim((string) $this->input('ghcr_token', ''));

        $this->merge([
            'ghcr_username' => $username,
            'ghcr_token' => $token !== '' ? $token : null,
            'clear_ghcr_token' => $this->boolean('clear_ghcr_token'),
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
