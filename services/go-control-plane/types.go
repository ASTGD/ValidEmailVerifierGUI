package main

type WorkerMetrics struct {
	EmailsPerSec float64 `json:"emails_per_sec,omitempty"`
	ErrorsPerMin float64 `json:"errors_per_min,omitempty"`
	CacheHitRate float64 `json:"cache_hit_rate,omitempty"`
	AvgLatencyMS float64 `json:"avg_latency_ms,omitempty"`
	BounceRate   float64 `json:"bounce_rate,omitempty"`
	UnknownRate  float64 `json:"unknown_rate,omitempty"`
}

type StageMetric struct {
	Processed int64 `json:"processed,omitempty"`
	Errors    int64 `json:"errors,omitempty"`
}

type StageMetrics struct {
	Screening *StageMetric `json:"screening,omitempty"`
	SMTPProbe *StageMetric `json:"smtp_probe,omitempty"`
}

type SMTPMetrics struct {
	TempfailRate float64 `json:"tempfail_rate,omitempty"`
	RejectRate   float64 `json:"reject_rate,omitempty"`
	CatchAllRate float64 `json:"catch_all_rate,omitempty"`
	UnknownRate  float64 `json:"unknown_rate,omitempty"`
}

type ProviderMetric struct {
	Provider        string  `json:"provider"`
	TempfailRate    float64 `json:"tempfail_rate,omitempty"`
	RejectRate      float64 `json:"reject_rate,omitempty"`
	UnknownRate     float64 `json:"unknown_rate,omitempty"`
	AvgRetryAfter   float64 `json:"avg_retry_after,omitempty"`
	PolicyBlockRate float64 `json:"policy_block_rate,omitempty"`
}

type HeartbeatRequest struct {
	WorkerID        string           `json:"worker_id"`
	Host            string           `json:"host,omitempty"`
	IPAddress       string           `json:"ip_address,omitempty"`
	Version         string           `json:"version,omitempty"`
	Pool            string           `json:"pool,omitempty"`
	Tags            []string         `json:"tags,omitempty"`
	Status          string           `json:"status"`
	CurrentJobID    string           `json:"current_job_id,omitempty"`
	CurrentChunkID  string           `json:"current_chunk_id,omitempty"`
	CorrelationID   string           `json:"correlation_id,omitempty"`
	Metrics         *WorkerMetrics   `json:"metrics,omitempty"`
	StageMetrics    *StageMetrics    `json:"stage_metrics,omitempty"`
	SMTPMetrics     *SMTPMetrics     `json:"smtp_metrics,omitempty"`
	ProviderMetrics []ProviderMetric `json:"provider_metrics,omitempty"`
	PoolHealthHint  *float64         `json:"pool_health_hint,omitempty"`
}

type HeartbeatResponse struct {
	DesiredState string   `json:"desired_state"`
	Commands     []string `json:"commands"`
}

type WorkerSummary struct {
	WorkerID        string           `json:"worker_id"`
	Host            string           `json:"host,omitempty"`
	IPAddress       string           `json:"ip_address,omitempty"`
	Version         string           `json:"version,omitempty"`
	Pool            string           `json:"pool,omitempty"`
	Status          string           `json:"status"`
	DesiredState    string           `json:"desired_state"`
	Quarantined     bool             `json:"quarantined"`
	LastHeartbeat   string           `json:"last_heartbeat_at"`
	CurrentJobID    string           `json:"current_job_id,omitempty"`
	CurrentChunkID  string           `json:"current_chunk_id,omitempty"`
	CorrelationID   string           `json:"correlation_id,omitempty"`
	StageMetrics    *StageMetrics    `json:"stage_metrics,omitempty"`
	SMTPMetrics     *SMTPMetrics     `json:"smtp_metrics,omitempty"`
	ProviderMetrics []ProviderMetric `json:"provider_metrics,omitempty"`
	PoolHealthHint  float64          `json:"pool_health_hint,omitempty"`
}

type WorkersResponse struct {
	Data []WorkerSummary `json:"data"`
}

type PoolSummary struct {
	Pool        string  `json:"pool"`
	Online      int     `json:"online"`
	Desired     int     `json:"desired"`
	HealthScore float64 `json:"health_score,omitempty"`
}

type PoolsResponse struct {
	Data []PoolSummary `json:"data"`
}

type ScalePoolRequest struct {
	Desired int `json:"desired"`
}

type ScalePoolResponse struct {
	Pool    string `json:"pool"`
	Desired int    `json:"desired"`
}

type workerMeta struct {
	WorkerID       string   `json:"worker_id"`
	Host           string   `json:"host,omitempty"`
	IPAddress      string   `json:"ip_address,omitempty"`
	Version        string   `json:"version,omitempty"`
	Pool           string   `json:"pool,omitempty"`
	Tags           []string `json:"tags,omitempty"`
	CurrentJobID   string   `json:"current_job_id,omitempty"`
	CurrentChunkID string   `json:"current_chunk_id,omitempty"`
	CorrelationID  string   `json:"correlation_id,omitempty"`
}

type IncidentRecord struct {
	Key        string                 `json:"key"`
	Type       string                 `json:"type"`
	Severity   string                 `json:"severity"`
	Status     string                 `json:"status"`
	Message    string                 `json:"message"`
	Context    map[string]interface{} `json:"context,omitempty"`
	OpenedAt   string                 `json:"opened_at"`
	UpdatedAt  string                 `json:"updated_at"`
	ResolvedAt string                 `json:"resolved_at,omitempty"`
}

type IncidentsResponse struct {
	Data []IncidentRecord `json:"data"`
}

type ProviderModeState struct {
	Provider  string `json:"provider"`
	Mode      string `json:"mode"`
	Source    string `json:"source,omitempty"`
	UpdatedAt string `json:"updated_at,omitempty"`
}

type ProviderHealthSummary struct {
	Provider      string  `json:"provider"`
	Mode          string  `json:"mode"`
	Status        string  `json:"status"`
	TempfailRate  float64 `json:"tempfail_rate"`
	RejectRate    float64 `json:"reject_rate"`
	UnknownRate   float64 `json:"unknown_rate"`
	AvgRetryAfter float64 `json:"avg_retry_after"`
	Workers       int     `json:"workers"`
}

type ProviderHealthResponse struct {
	Data []ProviderHealthSummary `json:"data"`
}

type ProviderPolicyState struct {
	LastReloadAt string `json:"last_reload_at,omitempty"`
	ReloadCount  int    `json:"reload_count"`
	UpdatedAt    string `json:"updated_at,omitempty"`
}

type ProviderPoliciesData struct {
	PolicyEngineEnabled  bool                `json:"policy_engine_enabled"`
	AdaptiveRetryEnabled bool                `json:"adaptive_retry_enabled"`
	AutoProtectEnabled   bool                `json:"auto_protect_enabled"`
	LastReloadAt         string              `json:"last_reload_at,omitempty"`
	ReloadCount          int                 `json:"reload_count"`
	Modes                []ProviderModeState `json:"modes"`
}

type ProviderPoliciesResponse struct {
	Data ProviderPoliciesData `json:"data"`
}
