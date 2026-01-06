<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['processing', 'failed'])],
            'error_message' => ['nullable', 'string', 'max:2000', 'required_if:status,failed'],
        ];
    }
}
