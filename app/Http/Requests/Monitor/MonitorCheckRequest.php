<?php

namespace App\Http\Requests\Monitor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonitorCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_id' => ['nullable', 'integer', 'exists:engine_servers,id'],
            'server_ip' => ['nullable', 'ip'],
            'checked_at' => ['required', 'date'],
            'results' => ['required', 'array', 'min:1'],
            'results.*.rbl' => ['required', 'string'],
            'results.*.listed' => ['required', 'boolean'],
            'results.*.response' => ['nullable', 'string'],
            'results.*.error_message' => ['nullable', 'string'],
            'results.*.status' => ['nullable', Rule::in(['listed', 'clear', 'error'])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $serverId = $this->input('server_id');
            $serverIp = $this->input('server_ip');

            if (! $serverId && ! $serverIp) {
                $validator->errors()->add('server_id', 'Either server_id or server_ip is required.');
            }
        });
    }
}
