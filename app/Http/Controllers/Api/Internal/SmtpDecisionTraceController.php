<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\SmtpDecisionTraceIndexRequest;
use App\Models\SmtpDecisionTrace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SmtpDecisionTraceController extends Controller
{
    public function index(SmtpDecisionTraceIndexRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 25)));

        $query = SmtpDecisionTrace::query()
            ->orderByDesc('id');

        if ($provider = trim((string) ($payload['provider'] ?? ''))) {
            $query->where('provider', $provider);
        }

        if ($decisionClass = trim((string) ($payload['decision_class'] ?? ''))) {
            $query->where('decision_class', $decisionClass);
        }

        if ($reasonTag = trim((string) ($payload['reason_tag'] ?? ''))) {
            $query->where('reason_tag', $reasonTag);
        }

        if ($policyVersion = trim((string) ($payload['policy_version'] ?? ''))) {
            $query->where('policy_version', $policyVersion);
        }

        $beforeId = (int) ($payload['before_id'] ?? 0);
        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $traces = $query->limit($limit)->get();

        $requestId = $this->requestId($request);

        return response()->json([
            'data' => $traces->map(function (SmtpDecisionTrace $trace): array {
                $tracePayload = is_array($trace->trace_payload) ? $trace->trace_payload : [];

                return [
                    'id' => $trace->id,
                    'verification_job_id' => $trace->verification_job_id,
                    'verification_job_chunk_id' => $trace->verification_job_chunk_id,
                    'email_hash' => $trace->email_hash,
                    'provider' => $trace->provider,
                    'policy_version' => $trace->policy_version,
                    'matched_rule_id' => $trace->matched_rule_id,
                    'decision_class' => $trace->decision_class,
                    'smtp_code' => $trace->smtp_code,
                    'enhanced_code' => $trace->enhanced_code,
                    'retry_strategy' => $trace->retry_strategy,
                    'reason_tag' => $trace->reason_tag,
                    'confidence_hint' => $trace->confidence_hint,
                    'session_strategy_id' => $trace->session_strategy_id,
                    'attempt_route' => $trace->attempt_route,
                    'attempt_chain' => Arr::get($tracePayload, 'attempt_chain', []),
                    'observed_at' => $trace->observed_at?->toISOString(),
                    'created_at' => $trace->created_at?->toISOString(),
                ];
            })->values(),
            'meta' => [
                'limit' => $limit,
                'next_before_id' => $traces->isNotEmpty() ? (int) $traces->last()->id : null,
            ],
        ], 200, [
            'X-Request-Id' => $requestId,
        ]);
    }

    private function requestId(Request $request): string
    {
        $existing = trim((string) $request->header('X-Request-Id', ''));
        if ($existing !== '') {
            return $existing;
        }

        return (string) Str::uuid();
    }
}
