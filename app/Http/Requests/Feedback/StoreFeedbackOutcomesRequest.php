<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFeedbackOutcomesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('engine.feedback_api_enabled', false);
    }

    public function rules(): array
    {
        $maxItems = (int) config('engine.feedback_max_items_per_request', 500);

        $itemsRule = ['required', 'array', 'min:1'];
        if ($maxItems > 0) {
            $itemsRule[] = 'max:'.$maxItems;
        }

        return [
            'source' => ['nullable', 'string', 'max:255'],
            'observed_at' => ['nullable', 'date'],
            'items' => $itemsRule,
            'items.*.email' => ['required', 'string', 'email'],
            'items.*.outcome' => ['required', 'string', 'in:valid,invalid,risky'],
            'items.*.reason_code' => ['nullable', 'string', 'max:255'],
            'items.*.observed_at' => ['nullable', 'date'],
            'items.*.source' => ['nullable', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $maxPayloadKb = (int) config('engine.feedback_max_payload_kb', 512);
        if ($maxPayloadKb <= 0) {
            return;
        }

        $maxBytes = $maxPayloadKb * 1024;
        $contentLength = (int) $this->server('CONTENT_LENGTH', 0);
        if ($contentLength > $maxBytes) {
            throw new HttpResponseException(response()->json([
                'message' => 'Feedback payload exceeds the maximum size.',
            ], 413));
        }

        $rawContent = $this->getContent();
        if ($rawContent !== '' && strlen($rawContent) > $maxBytes) {
            throw new HttpResponseException(response()->json([
                'message' => 'Feedback payload exceeds the maximum size.',
            ], 413));
        }
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Feedback API is disabled.',
        ], 403));
    }
}
