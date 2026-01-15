<?php

namespace App\Http\Requests\Verifier;

use Illuminate\Foundation\Http\FormRequest;

class ChunkLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['required', 'string', 'in:debug,info,warning,error'],
            'event' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'context' => ['nullable', 'array'],
        ];
    }
}
