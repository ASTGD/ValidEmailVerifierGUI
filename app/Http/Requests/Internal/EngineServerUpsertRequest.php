<?php

namespace App\Http\Requests\Internal;

use App\Models\EngineWorkerPool;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
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
        $engineServerId = $this->route('engineServer')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('engine_servers', 'name')->ignore($engineServerId)],
            'ip_address' => ['required', 'string', 'max:255', Rule::unique('engine_servers', 'ip_address')->ignore($engineServerId)],
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
            'worker_pool_id' => [
                'required',
                'integer',
                Rule::exists('engine_worker_pools', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string'],
            'process_control_mode' => ['nullable', 'string', Rule::in(['control_plane_only', 'agent_systemd'])],
            'agent_enabled' => ['nullable', 'boolean'],
            'agent_base_url' => ['nullable', 'url:http,https', 'max:255'],
            'agent_timeout_seconds' => ['nullable', 'integer', 'min:2', 'max:30'],
            'agent_verify_tls' => ['nullable', 'boolean'],
            'agent_service_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $processControlMode = $this->filled('process_control_mode')
            ? (string) $this->input('process_control_mode')
            : 'control_plane_only';
        $agentBaseUrl = $this->filled('agent_base_url') ? (string) $this->input('agent_base_url') : null;

        if ($processControlMode === 'agent_systemd' && $agentBaseUrl === null) {
            $agentBaseUrl = $this->defaultAgentBaseUrl();
        }

        $workerPoolID = $this->filled('worker_pool_id')
            ? $this->input('worker_pool_id')
            : EngineWorkerPool::resolveDefaultId();

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'ip_address' => trim((string) $this->input('ip_address', '')),
            'is_active' => $this->boolean('is_active'),
            'drain_mode' => $this->boolean('drain_mode'),
            'max_concurrency' => $this->filled('max_concurrency') ? $this->input('max_concurrency') : null,
            'verifier_domain_id' => $this->filled('verifier_domain_id') ? $this->input('verifier_domain_id') : null,
            'worker_pool_id' => $workerPoolID,
            'process_control_mode' => $processControlMode,
            'agent_enabled' => $this->boolean('agent_enabled'),
            'agent_base_url' => $agentBaseUrl,
            'agent_timeout_seconds' => $this->filled('agent_timeout_seconds')
                ? $this->input('agent_timeout_seconds')
                : (int) config('engine_servers.process_control.default_timeout_seconds', 8),
            'agent_verify_tls' => $this->has('agent_verify_tls')
                ? $this->boolean('agent_verify_tls')
                : true,
            'agent_service_name' => $this->filled('agent_service_name')
                ? $this->input('agent_service_name')
                : 'vev-worker.service',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A server with this name already exists. Choose a different name or edit the existing server.',
            'ip_address.unique' => 'A server with this IP address already exists. Use Select Server to continue with that server.',
        ];
    }

    private function defaultAgentBaseUrl(): ?string
    {
        $ipAddress = trim((string) $this->input('ip_address', ''));
        if ($ipAddress === '') {
            return null;
        }

        $port = max(1, min(65535, (int) config('engine.worker_agent_port', 9713)));

        return sprintf('http://%s:%d', $ipAddress, $port);
    }

    protected function failedValidation(Validator $validator): void
    {
        $requestId = $this->requestId();
        $firstError = trim((string) $validator->errors()->first());
        $message = $firstError !== '' ? $firstError : 'Validation failed.';

        throw new HttpResponseException(response()->json([
            'error_code' => 'validation_failed',
            'message' => $message,
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
