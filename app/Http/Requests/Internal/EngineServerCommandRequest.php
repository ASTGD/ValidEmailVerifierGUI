<?php

namespace App\Http\Requests\Internal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class EngineServerCommandRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:start,stop,restart,status'],
            'reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
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
