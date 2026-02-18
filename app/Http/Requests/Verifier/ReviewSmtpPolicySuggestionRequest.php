<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSmtpPolicySuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suggestion_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:approved,rejected,needs_changes'],
            'review_notes' => ['nullable', 'array'],
            'review_notes.summary' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
