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

type RoutingMetrics struct {
	RetryClaimsTotal              int64 `json:"retry_claims_total,omitempty"`
	RetryAntiAffinitySuccessTotal int64 `json:"retry_anti_affinity_success_total,omitempty"`
	SameWorkerAvoidTotal          int64 `json:"same_worker_avoid_total,omitempty"`
	SamePoolAvoidTotal            int64 `json:"same_pool_avoid_total,omitempty"`
	ProviderAffinityHitTotal      int64 `json:"provider_affinity_hit_total,omitempty"`
	FallbackClaimTotal            int64 `json:"fallback_claim_total,omitempty"`
}

type SessionMetrics struct {
	ConnectionReuseRate       float64 `json:"connection_reuse_rate,omitempty"`
	SessionRetrySameConnTotal int64   `json:"session_retry_same_conn_total,omitempty"`
	SessionRetryNewConnTotal  int64   `json:"session_retry_new_conn_total,omitempty"`
	ThrottleAppliedTotal      int64   `json:"throttle_applied_total,omitempty"`
}

type AttemptRouteMetrics struct {
	AttemptsTotal           int64 `json:"attempts_total,omitempty"`
	RetryAttemptsTotal      int64 `json:"retry_attempts_total,omitempty"`
	MXFallbackAttemptsTotal int64 `json:"mx_fallback_attempts_total,omitempty"`
}

type HeartbeatRequest struct {
	WorkerID              string               `json:"worker_id"`
	Host                  string               `json:"host,omitempty"`
	IPAddress             string               `json:"ip_address,omitempty"`
	Version               string               `json:"version,omitempty"`
	Pool                  string               `json:"pool,omitempty"`
	Tags                  []string             `json:"tags,omitempty"`
	Status                string               `json:"status"`
	CurrentJobID          string               `json:"current_job_id,omitempty"`
	CurrentChunkID        string               `json:"current_chunk_id,omitempty"`
	CorrelationID         string               `json:"correlation_id,omitempty"`
	Metrics               *WorkerMetrics       `json:"metrics,omitempty"`
	StageMetrics          *StageMetrics        `json:"stage_metrics,omitempty"`
	SMTPMetrics           *SMTPMetrics         `json:"smtp_metrics,omitempty"`
	ProviderMetrics       []ProviderMetric     `json:"provider_metrics,omitempty"`
	RoutingMetrics        *RoutingMetrics      `json:"routing_metrics,omitempty"`
	SessionMetrics        *SessionMetrics      `json:"session_metrics,omitempty"`
	AttemptRouteMetrics   *AttemptRouteMetrics `json:"attempt_route_metrics,omitempty"`
	RetryAntiAffinityHits int64                `json:"retry_anti_affinity_hits,omitempty"`
	UnknownReasonTags     map[string]int64     `json:"unknown_reason_tags,omitempty"`
	SessionStrategyID     string               `json:"session_strategy_id,omitempty"`
	ReasonTagCounts       map[string]int64     `json:"reason_tag_counters,omitempty"`
	PoolHealthHint        *float64             `json:"pool_health_hint,omitempty"`
}

type HeartbeatResponse struct {
	DesiredState string   `json:"desired_state"`
	Commands     []string `json:"commands"`
}

type WorkerSummary struct {
	WorkerID              string               `json:"worker_id"`
	Host                  string               `json:"host,omitempty"`
	IPAddress             string               `json:"ip_address,omitempty"`
	Version               string               `json:"version,omitempty"`
	Pool                  string               `json:"pool,omitempty"`
	Tags                  []string             `json:"tags,omitempty"`
	Status                string               `json:"status"`
	DesiredState          string               `json:"desired_state"`
	Quarantined           bool                 `json:"quarantined"`
	LastHeartbeat         string               `json:"last_heartbeat_at"`
	CurrentJobID          string               `json:"current_job_id,omitempty"`
	CurrentChunkID        string               `json:"current_chunk_id,omitempty"`
	CorrelationID         string               `json:"correlation_id,omitempty"`
	StageMetrics          *StageMetrics        `json:"stage_metrics,omitempty"`
	SMTPMetrics           *SMTPMetrics         `json:"smtp_metrics,omitempty"`
	ProviderMetrics       []ProviderMetric     `json:"provider_metrics,omitempty"`
	RoutingMetrics        *RoutingMetrics      `json:"routing_metrics,omitempty"`
	SessionMetrics        *SessionMetrics      `json:"session_metrics,omitempty"`
	AttemptRouteMetrics   *AttemptRouteMetrics `json:"attempt_route_metrics,omitempty"`
	RetryAntiAffinityHits int64                `json:"retry_anti_affinity_hits,omitempty"`
	UnknownReasonTags     map[string]int64     `json:"unknown_reason_tags,omitempty"`
	SessionStrategyID     string               `json:"session_strategy_id,omitempty"`
	ReasonTagCounts       map[string]int64     `json:"reason_tag_counters,omitempty"`
	PoolHealthHint        float64              `json:"pool_health_hint,omitempty"`
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
	Provider          string  `json:"provider"`
	Mode              string  `json:"mode"`
	Status            string  `json:"status"`
	TempfailRate      float64 `json:"tempfail_rate"`
	RejectRate        float64 `json:"reject_rate"`
	UnknownRate       float64 `json:"unknown_rate"`
	PolicyBlockedRate float64 `json:"policy_blocked_rate"`
	AvgRetryAfter     float64 `json:"avg_retry_after"`
	Workers           int     `json:"workers"`
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
	PolicyEngineEnabled  bool                             `json:"policy_engine_enabled"`
	AdaptiveRetryEnabled bool                             `json:"adaptive_retry_enabled"`
	AutoProtectEnabled   bool                             `json:"auto_protect_enabled"`
	AutopilotEnabled     bool                             `json:"autopilot_enabled,omitempty"`
	ActiveVersion        string                           `json:"active_version,omitempty"`
	LastReloadAt         string                           `json:"last_reload_at,omitempty"`
	ReloadCount          int                              `json:"reload_count"`
	Modes                []ProviderModeState              `json:"modes"`
	ModeSemantics        map[string]ProviderModeSemantics `json:"mode_semantics,omitempty"`
}

type ProviderPoliciesResponse struct {
	Data ProviderPoliciesData `json:"data"`
}

type ProviderQualitySummary struct {
	Provider            string  `json:"provider"`
	Mode                string  `json:"mode"`
	Status              string  `json:"status"`
	TempfailRate        float64 `json:"tempfail_rate"`
	RejectRate          float64 `json:"reject_rate"`
	UnknownRate         float64 `json:"unknown_rate"`
	PolicyBlockedRate   float64 `json:"policy_blocked_rate"`
	TempfailRecoveryPct float64 `json:"tempfail_recovery_pct"`
	RetryWastePct       float64 `json:"retry_waste_pct"`
	Workers             int     `json:"workers"`
}

type ProviderQualityResponse struct {
	Data []ProviderQualitySummary `json:"data"`
}

type ProviderQualityDriftSummary struct {
	Provider            string  `json:"provider"`
	Status              string  `json:"status"`
	UnknownRate         float64 `json:"unknown_rate"`
	UnknownBaseline     float64 `json:"unknown_baseline"`
	UnknownDelta        float64 `json:"unknown_delta"`
	TempfailRate        float64 `json:"tempfail_rate"`
	TempfailBaseline    float64 `json:"tempfail_baseline"`
	TempfailDelta       float64 `json:"tempfail_delta"`
	PolicyBlockedRate   float64 `json:"policy_blocked_rate"`
	Mode                string  `json:"mode"`
	Workers             int     `json:"workers"`
	DriftRecommendation string  `json:"drift_recommendation"`
}

type ProviderQualityDriftResponse struct {
	Data []ProviderQualityDriftSummary `json:"data"`
}

type ProviderRetryEffectivenessSummary struct {
	Provider            string  `json:"provider"`
	Mode                string  `json:"mode"`
	Status              string  `json:"status"`
	TempfailRate        float64 `json:"tempfail_rate"`
	TempfailRecoveryPct float64 `json:"tempfail_recovery_pct"`
	RetryWastePct       float64 `json:"retry_waste_pct"`
	AvgRetryAfter       float64 `json:"avg_retry_after"`
	Workers             int     `json:"workers"`
}

type ProviderRetryEffectivenessResponse struct {
	Data []ProviderRetryEffectivenessSummary `json:"data"`
}

type ProviderAccuracyCalibrationSummary struct {
	Provider              string  `json:"provider"`
	Workers               int     `json:"workers"`
	UnknownRate           float64 `json:"unknown_rate"`
	TempfailRecoveryPct   float64 `json:"tempfail_recovery_pct"`
	PolicyBlockedRate     float64 `json:"policy_blocked_rate"`
	CalibrationConfidence string  `json:"calibration_confidence"`
}

type ProviderAccuracyCalibrationResponse struct {
	Data []ProviderAccuracyCalibrationSummary `json:"data"`
}

type ProviderUnknownClusterSummary struct {
	Provider      string `json:"provider"`
	Tag           string `json:"tag"`
	Count         int64  `json:"count"`
	SampleWorkers int    `json:"sample_workers"`
}

type ProviderUnknownClusterResponse struct {
	Data []ProviderUnknownClusterSummary `json:"data"`
}

type PolicyShadowEvaluateRequest struct {
	CandidateVersion string   `json:"candidate_version"`
	Providers        []string `json:"providers,omitempty"`
	TriggeredBy      string   `json:"triggered_by,omitempty"`
	Notes            string   `json:"notes,omitempty"`
}

type PolicyShadowEvaluateResult struct {
	Provider            string  `json:"provider"`
	UnknownRate         float64 `json:"unknown_rate"`
	TempfailRecoveryPct float64 `json:"tempfail_recovery_pct"`
	PolicyBlockedRate   float64 `json:"policy_blocked_rate"`
	Recommendation      string  `json:"recommendation"`
}

type PolicyShadowEvaluateResponse struct {
	Data struct {
		CandidateVersion string                       `json:"candidate_version"`
		ActiveVersion    string                       `json:"active_version"`
		TriggeredBy      string                       `json:"triggered_by"`
		Notes            string                       `json:"notes,omitempty"`
		EvaluatedAt      string                       `json:"evaluated_at"`
		Results          []PolicyShadowEvaluateResult `json:"results"`
	} `json:"data"`
}

type PolicyShadowRunSummary struct {
	ProviderCount             int     `json:"provider_count"`
	UnknownRateAvg            float64 `json:"unknown_rate_avg"`
	TempfailRecoveryPctAvg    float64 `json:"tempfail_recovery_pct_avg"`
	PolicyBlockedRateAvg      float64 `json:"policy_blocked_rate_avg"`
	HighestRiskRecommendation string  `json:"highest_risk_recommendation"`
}

type PolicyShadowRunRecord struct {
	RunUUID          string                       `json:"run_uuid"`
	CandidateVersion string                       `json:"candidate_version"`
	ActiveVersion    string                       `json:"active_version"`
	TriggeredBy      string                       `json:"triggered_by"`
	Notes            string                       `json:"notes,omitempty"`
	EvaluatedAt      string                       `json:"evaluated_at"`
	Providers        []string                     `json:"providers"`
	Results          []PolicyShadowEvaluateResult `json:"results"`
	Summary          PolicyShadowRunSummary       `json:"summary"`
}

type PolicyShadowRunListResponse struct {
	Data []PolicyShadowRunRecord `json:"data"`
}

type SMTPPolicyVersionRecord struct {
	Version            string `json:"version"`
	Status             string `json:"status"`
	Active             bool   `json:"active"`
	CanaryPercent      int    `json:"canary_percent"`
	ValidationStatus   string `json:"validation_status,omitempty"`
	ValidationError    string `json:"validation_error,omitempty"`
	PayloadChecksum    string `json:"payload_checksum,omitempty"`
	PayloadValidatedAt string `json:"payload_validated_at,omitempty"`
	UpdatedAt          string `json:"updated_at,omitempty"`
	PromotedAt         string `json:"promoted_at,omitempty"`
	RolledBackAt       string `json:"rolled_back_at,omitempty"`
	UpdatedBy          string `json:"updated_by,omitempty"`
}

type SMTPPolicyVersionsResponse struct {
	Data          []SMTPPolicyVersionRecord `json:"data"`
	ActiveVersion string                    `json:"active_version,omitempty"`
}

type SMTPPolicyRolloutRecord struct {
	Action             string `json:"action"`
	Version            string `json:"version"`
	CanaryPercent      int    `json:"canary_percent"`
	TriggeredBy        string `json:"triggered_by,omitempty"`
	Notes              string `json:"notes,omitempty"`
	ValidationStatus   string `json:"validation_status,omitempty"`
	PayloadChecksum    string `json:"payload_checksum,omitempty"`
	PayloadValidatedAt string `json:"payload_validated_at,omitempty"`
	CreatedAt          string `json:"created_at"`
}

type RoutingQualitySummary struct {
	RetryClaimsTotal              int64   `json:"retry_claims_total"`
	RetryAntiAffinitySuccessTotal int64   `json:"retry_anti_affinity_success_total"`
	SameWorkerAvoidTotal          int64   `json:"same_worker_avoid_total"`
	SamePoolAvoidTotal            int64   `json:"same_pool_avoid_total"`
	ProviderAffinityHitTotal      int64   `json:"provider_affinity_hit_total"`
	FallbackClaimTotal            int64   `json:"fallback_claim_total"`
	AntiAffinitySuccessRate       float64 `json:"anti_affinity_success_rate"`
	ProviderAffinityHitRate       float64 `json:"provider_affinity_hit_rate"`
	RetryFallbackRate             float64 `json:"retry_fallback_rate"`
	TopPoolShare                  float64 `json:"top_pool_share"`
}

type ProviderModeSemantics struct {
	ProbeEnabled                bool    `json:"probe_enabled"`
	MaxConcurrencyMultiplier    float64 `json:"max_concurrency_multiplier,omitempty"`
	ConnectsPerMinuteMultiplier float64 `json:"connects_per_minute_multiplier,omitempty"`
}
