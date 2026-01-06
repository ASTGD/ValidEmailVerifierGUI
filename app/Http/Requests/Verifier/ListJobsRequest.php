<?php

namespace App\Http\Requests\Verifier;

use App\Enums\VerificationJobStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(
            static fn (VerificationJobStatus $status) => $status->value,
            VerificationJobStatus::cases()
        );

        return [
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
