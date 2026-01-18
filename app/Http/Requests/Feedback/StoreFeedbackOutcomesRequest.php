<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackOutcomesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['nullable', 'string', 'max:255'],
            'observed_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.email' => ['required', 'string', 'email'],
            'items.*.outcome' => ['required', 'string', 'in:valid,invalid,risky'],
            'items.*.reason_code' => ['nullable', 'string', 'max:255'],
            'items.*.observed_at' => ['nullable', 'date'],
            'items.*.source' => ['nullable', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'array'],
        ];
    }
}
