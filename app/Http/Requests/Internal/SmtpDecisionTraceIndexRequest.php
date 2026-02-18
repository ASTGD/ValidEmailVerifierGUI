<?php

namespace App\Http\Requests\Internal;

use Illuminate\Foundation\Http\FormRequest;

class SmtpDecisionTraceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'in:gmail,microsoft,yahoo,generic'],
            'decision_class' => ['nullable', 'string', 'in:deliverable,undeliverable,retryable,policy_blocked,unknown'],
            'reason_tag' => ['nullable', 'string', 'max:64'],
            'policy_version' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
