<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EngineServerUpsertRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'drain_mode' => ['required', 'boolean'],
            'max_concurrency' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'helo_name' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email:rfc', 'max:255'],
            'verifier_domain_id' => [
                'nullable',
                'integer',
                Rule::exists('verifier_domains', 'id'),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'drain_mode' => $this->boolean('drain_mode'),
            'max_concurrency' => $this->filled('max_concurrency') ? $this->input('max_concurrency') : null,
            'verifier_domain_id' => $this->filled('verifier_domain_id') ? $this->input('verifier_domain_id') : null,
        ]);
    }
}
