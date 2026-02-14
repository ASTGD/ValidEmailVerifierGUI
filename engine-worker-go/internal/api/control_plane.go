package api

import (
	"context"
	"encoding/json"
	"net/http"
	"strings"
	"time"
)

type ControlPlaneClient struct {
	baseURL    string
	token      string
	httpClient *http.Client
}

type ControlPlaneWorkerMetrics struct {
	EmailsPerSec float64 `json:"emails_per_sec,omitempty"`
	ErrorsPerMin float64 `json:"errors_per_min,omitempty"`
	CacheHitRate float64 `json:"cache_hit_rate,omitempty"`
	AvgLatencyMS float64 `json:"avg_latency_ms,omitempty"`
	BounceRate   float64 `json:"bounce_rate,omitempty"`
	UnknownRate  float64 `json:"unknown_rate,omitempty"`
}

type ControlPlaneStageMetric struct {
	Processed int64 `json:"processed,omitempty"`
	Errors    int64 `json:"errors,omitempty"`
}

type ControlPlaneStageMetrics struct {
	Screening *ControlPlaneStageMetric `json:"screening,omitempty"`
	SMTPProbe *ControlPlaneStageMetric `json:"smtp_probe,omitempty"`
}

type ControlPlaneSMTPMetrics struct {
	TempfailRate float64 `json:"tempfail_rate,omitempty"`
	RejectRate   float64 `json:"reject_rate,omitempty"`
	CatchAllRate float64 `json:"catch_all_rate,omitempty"`
	UnknownRate  float64 `json:"unknown_rate,omitempty"`
}

type ControlPlaneProviderMetric struct {
	Provider        string  `json:"provider"`
	TempfailRate    float64 `json:"tempfail_rate,omitempty"`
	RejectRate      float64 `json:"reject_rate,omitempty"`
	UnknownRate     float64 `json:"unknown_rate,omitempty"`
	AvgRetryAfter   float64 `json:"avg_retry_after,omitempty"`
	PolicyBlockRate float64 `json:"policy_block_rate,omitempty"`
}

type ControlPlaneRoutingMetrics struct {
	RetryClaimsTotal              int64 `json:"retry_claims_total,omitempty"`
	RetryAntiAffinitySuccessTotal int64 `json:"retry_anti_affinity_success_total,omitempty"`
	SameWorkerAvoidTotal          int64 `json:"same_worker_avoid_total,omitempty"`
	SamePoolAvoidTotal            int64 `json:"same_pool_avoid_total,omitempty"`
	ProviderAffinityHitTotal      int64 `json:"provider_affinity_hit_total,omitempty"`
	FallbackClaimTotal            int64 `json:"fallback_claim_total,omitempty"`
}

type ControlPlaneHeartbeatRequest struct {
	WorkerID        string                       `json:"worker_id"`
	Host            string                       `json:"host,omitempty"`
	IPAddress       string                       `json:"ip_address,omitempty"`
	Version         string                       `json:"version,omitempty"`
	Pool            string                       `json:"pool,omitempty"`
	Tags            []string                     `json:"tags,omitempty"`
	Status          string                       `json:"status"`
	CurrentJobID    string                       `json:"current_job_id,omitempty"`
	CurrentChunkID  string                       `json:"current_chunk_id,omitempty"`
	CorrelationID   string                       `json:"correlation_id,omitempty"`
	Metrics         *ControlPlaneWorkerMetrics   `json:"metrics,omitempty"`
	StageMetrics    *ControlPlaneStageMetrics    `json:"stage_metrics,omitempty"`
	SMTPMetrics     *ControlPlaneSMTPMetrics     `json:"smtp_metrics,omitempty"`
	ProviderMetrics []ControlPlaneProviderMetric `json:"provider_metrics,omitempty"`
	RoutingMetrics  *ControlPlaneRoutingMetrics  `json:"routing_metrics,omitempty"`
	PoolHealthHint  *float64                     `json:"pool_health_hint,omitempty"`
}

type ControlPlaneHeartbeatResponse struct {
	DesiredState string   `json:"desired_state"`
	Commands     []string `json:"commands"`
}

type ControlPlaneProviderPoliciesResponse struct {
	Data struct {
		PolicyEngineEnabled  bool   `json:"policy_engine_enabled"`
		AdaptiveRetryEnabled bool   `json:"adaptive_retry_enabled"`
		AutoProtectEnabled   bool   `json:"auto_protect_enabled"`
		ActiveVersion        string `json:"active_version"`
	} `json:"data"`
}

func NewControlPlaneClient(baseURL, token string) *ControlPlaneClient {
	return &ControlPlaneClient{
		baseURL:    strings.TrimRight(baseURL, "/"),
		token:      token,
		httpClient: &http.Client{Timeout: 10 * time.Second},
	}
}

func (c *ControlPlaneClient) Heartbeat(
	ctx context.Context,
	payload ControlPlaneHeartbeatRequest,
) (*ControlPlaneHeartbeatResponse, error) {
	status, body, err := doJSON(ctx, c.httpClient, c.baseURL, c.token, http.MethodPost, "/api/workers/heartbeat", payload)
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}

	var resp ControlPlaneHeartbeatResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}

func (c *ControlPlaneClient) ProviderPolicies(ctx context.Context) (*ControlPlaneProviderPoliciesResponse, error) {
	status, body, err := doJSON(ctx, c.httpClient, c.baseURL, c.token, http.MethodGet, "/api/providers/policies", nil)
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}

	var resp ControlPlaneProviderPoliciesResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}
