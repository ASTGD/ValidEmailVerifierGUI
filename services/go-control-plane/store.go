package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

type Store struct {
	rdb                           *redis.Client
	heartbeatTTL                  time.Duration
	policyPayloadValidator        PolicyPayloadValidator
	policyPayloadStrictValidation bool
}

const (
	runtimeSettingsKey      = "control_plane:runtime_settings"
	runtimeSettingsLastKey  = "control_plane:runtime_settings:last_snapshot"
	incidentsIndexKey       = "control_plane:incidents:index"
	incidentsActiveKey      = "control_plane:incidents:active"
	providerModesKey        = "control_plane:provider_modes"
	providerPolicyStateKey  = "control_plane:provider_policy_state"
	smtpPolicyVersionsKey   = "control_plane:smtp_policy_versions"
	smtpPolicyActiveKey     = "control_plane:smtp_policy_active"
	smtpPolicyHistoryKey    = "control_plane:smtp_policy_rollout_history"
	smtpPolicyShadowRunsKey = "control_plane:smtp_policy_shadow_runs"
)

type RuntimeSettings struct {
	AlertsEnabled                             bool    `json:"alerts_enabled"`
	AutoActionsEnabled                        bool    `json:"auto_actions_enabled"`
	ProviderPolicyEngineEnabled               bool    `json:"provider_policy_engine_enabled"`
	AdaptiveRetryEnabled                      bool    `json:"adaptive_retry_enabled"`
	ProviderAutoprotectEnabled                bool    `json:"provider_autoprotect_enabled"`
	AlertErrorRateThreshold                   float64 `json:"alert_error_rate_threshold"`
	AlertHeartbeatGraceSecond                 int     `json:"alert_heartbeat_grace_seconds"`
	AlertCooldownSecond                       int     `json:"alert_cooldown_seconds"`
	AlertCheckIntervalSecond                  int     `json:"alert_check_interval_seconds"`
	StaleWorkerTTLSecond                      int     `json:"stale_worker_ttl_seconds"`
	StuckDesiredGraceSecond                   int     `json:"stuck_desired_grace_seconds"`
	AutoscaleEnabled                          bool    `json:"autoscale_enabled"`
	AutoscaleIntervalSecond                   int     `json:"autoscale_interval_seconds"`
	AutoscaleCooldownSecond                   int     `json:"autoscale_cooldown_seconds"`
	AutoscaleMinDesired                       int     `json:"autoscale_min_desired"`
	AutoscaleMaxDesired                       int     `json:"autoscale_max_desired"`
	AutoscaleCanaryPercent                    int     `json:"autoscale_canary_percent"`
	QuarantineErrorRateThreshold              float64 `json:"quarantine_error_rate_threshold"`
	ProviderTempfailWarnRate                  float64 `json:"provider_tempfail_warn_rate"`
	ProviderTempfailCriticalRate              float64 `json:"provider_tempfail_critical_rate"`
	ProviderRejectWarnRate                    float64 `json:"provider_reject_warn_rate"`
	ProviderRejectCriticalRate                float64 `json:"provider_reject_critical_rate"`
	ProviderUnknownWarnRate                   float64 `json:"provider_unknown_warn_rate"`
	ProviderUnknownCriticalRate               float64 `json:"provider_unknown_critical_rate"`
	PolicyCanaryAutopilotEnabled              bool    `json:"policy_canary_autopilot_enabled"`
	PolicyCanaryWindowMinutes                 int     `json:"policy_canary_window_minutes"`
	PolicyCanaryRequiredHealthWindows         int     `json:"policy_canary_required_health_windows"`
	PolicyCanaryUnknownRegressionThreshold    float64 `json:"policy_canary_unknown_regression_threshold"`
	PolicyCanaryTempfailRecoveryDropThreshold float64 `json:"policy_canary_tempfail_recovery_drop_threshold"`
	PolicyCanaryPolicyBlockSpikeThreshold     float64 `json:"policy_canary_policy_block_spike_threshold"`
	PolicyCanaryMinProviderWorkers            int     `json:"policy_canary_min_provider_workers"`
	UIOverviewLiveIntervalSecond              int     `json:"ui_overview_live_interval_seconds"`
	UIWorkersRefreshSecond                    int     `json:"ui_workers_refresh_seconds"`
	UIPoolsRefreshSecond                      int     `json:"ui_pools_refresh_seconds"`
	UIAlertsRefreshSecond                     int     `json:"ui_alerts_refresh_seconds"`
}

func NewStore(rdb *redis.Client, ttl time.Duration) *Store {
	return &Store{
		rdb:          rdb,
		heartbeatTTL: ttl,
	}
}

func (s *Store) SetPolicyPayloadValidator(validator PolicyPayloadValidator, strict bool) {
	s.policyPayloadValidator = validator
	s.policyPayloadStrictValidation = strict
}

func (s *Store) Ping(ctx context.Context) error {
	if s == nil || s.rdb == nil {
		return errors.New("redis store is not configured")
	}

	return s.rdb.Ping(ctx).Err()
}

func (s *Store) HoldLeaderLease(ctx context.Context, leaseName, owner string, ttl time.Duration) (bool, error) {
	if leaseName == "" || owner == "" {
		return false, fmt.Errorf("leaseName and owner are required")
	}
	if ttl <= 0 {
		ttl = 45 * time.Second
	}

	key := fmt.Sprintf("control_plane:leader:%s", leaseName)
	ttlMs := ttl.Milliseconds()
	script := redis.NewScript(`
		local current = redis.call('GET', KEYS[1])
		if current == ARGV[1] then
			redis.call('PEXPIRE', KEYS[1], ARGV[2])
			return 1
		end
		if redis.call('SET', KEYS[1], ARGV[1], 'NX', 'PX', ARGV[2]) then
			return 1
		end
		return 0
	`)

	result, err := script.Run(ctx, s.rdb, []string{key}, owner, ttlMs).Int()
	if err != nil {
		return false, err
	}

	return result == 1, nil
}

func (s *Store) ReleaseLeaderLease(ctx context.Context, leaseName, owner string) error {
	if leaseName == "" || owner == "" {
		return nil
	}

	key := fmt.Sprintf("control_plane:leader:%s", leaseName)
	script := redis.NewScript(`
		if redis.call('GET', KEYS[1]) == ARGV[1] then
			return redis.call('DEL', KEYS[1])
		end
		return 0
	`)

	_, err := script.Run(ctx, s.rdb, []string{key}, owner).Result()
	return err
}

func (s *Store) UpsertHeartbeat(ctx context.Context, req HeartbeatRequest) (string, error) {
	if req.WorkerID == "" {
		return "", fmt.Errorf("worker_id is required")
	}
	status := normalizeStatus(req.Status)
	if status == "" {
		return "", fmt.Errorf("invalid status")
	}

	desiredKey := workerKey(req.WorkerID, "desired_state")
	desiredState, err := s.rdb.Get(ctx, desiredKey).Result()
	if err == redis.Nil || desiredState == "" {
		desiredState = "running"
	} else if err != nil {
		return "", err
	}

	isQuarantined, quarantineErr := s.isWorkerQuarantined(ctx, req.WorkerID)
	if quarantineErr != nil {
		return "", quarantineErr
	}
	if isQuarantined {
		desiredState = "draining"
	}

	meta := workerMeta{
		WorkerID:       req.WorkerID,
		Host:           req.Host,
		IPAddress:      req.IPAddress,
		Version:        req.Version,
		Pool:           req.Pool,
		Tags:           req.Tags,
		CurrentJobID:   req.CurrentJobID,
		CurrentChunkID: req.CurrentChunkID,
		CorrelationID:  req.CorrelationID,
	}
	metaJSON, err := json.Marshal(meta)
	if err != nil {
		return "", err
	}

	metricsJSON := []byte("{}")
	if req.Metrics != nil {
		payload, marshalErr := json.Marshal(req.Metrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		metricsJSON = payload
	}

	stageMetricsJSON := []byte("{}")
	if req.StageMetrics != nil {
		payload, marshalErr := json.Marshal(req.StageMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		stageMetricsJSON = payload
	}

	smtpMetricsJSON := []byte("{}")
	if req.SMTPMetrics != nil {
		payload, marshalErr := json.Marshal(req.SMTPMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		smtpMetricsJSON = payload
	}

	providerMetricsJSON := []byte("[]")
	if len(req.ProviderMetrics) > 0 {
		payload, marshalErr := json.Marshal(req.ProviderMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		providerMetricsJSON = payload
	}

	routingMetricsJSON := []byte("{}")
	if req.RoutingMetrics != nil {
		payload, marshalErr := json.Marshal(req.RoutingMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		routingMetricsJSON = payload
	}

	sessionMetricsJSON := []byte("{}")
	if req.SessionMetrics != nil {
		payload, marshalErr := json.Marshal(req.SessionMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		sessionMetricsJSON = payload
	}

	attemptRouteMetricsJSON := []byte("{}")
	if req.AttemptRouteMetrics != nil {
		payload, marshalErr := json.Marshal(req.AttemptRouteMetrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		attemptRouteMetricsJSON = payload
	}

	unknownReasonTagsJSON := []byte("{}")
	if len(req.UnknownReasonTags) > 0 {
		payload, marshalErr := json.Marshal(req.UnknownReasonTags)
		if marshalErr != nil {
			return "", marshalErr
		}
		unknownReasonTagsJSON = payload
	}

	reasonTagCountsJSON := []byte("{}")
	if len(req.ReasonTagCounts) > 0 {
		payload, marshalErr := json.Marshal(req.ReasonTagCounts)
		if marshalErr != nil {
			return "", marshalErr
		}
		reasonTagCountsJSON = payload
	}

	now := time.Now().UTC().Format(time.RFC3339)

	pipe := s.rdb.Pipeline()
	pipe.SAdd(ctx, "workers:active", req.WorkerID)
	pipe.SAdd(ctx, "workers:known", req.WorkerID)
	pipe.Set(ctx, workerKey(req.WorkerID, "status"), status, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "heartbeat"), now, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "last_seen"), now, 0)
	pipe.Set(ctx, workerKey(req.WorkerID, "meta"), metaJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "metrics"), metricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "stage_metrics"), stageMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "smtp_metrics"), smtpMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "provider_metrics"), providerMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "routing_metrics"), routingMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "session_metrics"), sessionMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "attempt_route_metrics"), attemptRouteMetricsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "retry_anti_affinity_hits"), req.RetryAntiAffinityHits, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "unknown_reason_tags"), unknownReasonTagsJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "session_strategy_id"), req.SessionStrategyID, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "reason_tag_counters"), reasonTagCountsJSON, s.heartbeatTTL)
	if req.PoolHealthHint != nil {
		pipe.Set(ctx, workerKey(req.WorkerID, "pool_health_hint"), *req.PoolHealthHint, s.heartbeatTTL)
	}
	if req.Pool != "" {
		pipe.Set(ctx, workerKey(req.WorkerID, "pool"), req.Pool, s.heartbeatTTL)
		pipe.SAdd(ctx, "pools:known", req.Pool)
	}
	if status == desiredState {
		pipe.Del(ctx, workerKey(req.WorkerID, "desired_state_updated"))
	} else {
		pipe.SetNX(ctx, workerKey(req.WorkerID, "desired_state_updated"), now, 0)
	}
	_, err = pipe.Exec(ctx)
	if err != nil {
		return "", err
	}

	if isQuarantined {
		_ = s.rdb.Set(ctx, desiredKey, "draining", 0).Err()
	}

	return desiredState, nil
}

func (s *Store) GetKnownWorkerIDs(ctx context.Context) ([]string, error) {
	return s.rdb.SMembers(ctx, "workers:known").Result()
}

func (s *Store) GetWorkerLastSeen(ctx context.Context, workerID string) (time.Time, bool, error) {
	value, err := s.rdb.Get(ctx, workerKey(workerID, "last_seen")).Result()
	if err == redis.Nil || value == "" {
		return time.Time{}, false, nil
	}
	if err != nil {
		return time.Time{}, false, err
	}
	parsed, err := time.Parse(time.RFC3339, value)
	if err != nil {
		return time.Time{}, false, err
	}
	return parsed, true, nil
}

func (s *Store) GetWorkerMetrics(ctx context.Context, workerID string) (*WorkerMetrics, error) {
	value, err := s.rdb.Get(ctx, workerKey(workerID, "metrics")).Result()
	if err == redis.Nil || value == "" {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}

	var metrics WorkerMetrics
	if err := json.Unmarshal([]byte(value), &metrics); err != nil {
		return nil, err
	}
	return &metrics, nil
}

func (s *Store) ShouldSendAlert(ctx context.Context, key string, ttl time.Duration) (bool, error) {
	return s.rdb.SetNX(ctx, key, "1", ttl).Result()
}

func defaultRuntimeSettings(cfg Config) RuntimeSettings {
	alertCheckInterval := int(cfg.AlertCheckInterval / time.Second)
	if alertCheckInterval <= 0 {
		alertCheckInterval = 30
	}

	grace := int(cfg.AlertHeartbeatGrace / time.Second)
	if grace <= 0 {
		grace = 120
	}

	cooldown := int(cfg.AlertCooldown / time.Second)
	if cooldown <= 0 {
		cooldown = 300
	}

	staleWorkerTTL := int(cfg.StaleWorkerTTL / time.Second)
	if staleWorkerTTL <= 0 {
		staleWorkerTTL = 86400
	}

	stuckDesiredGrace := int(cfg.StuckDesiredGrace / time.Second)
	if stuckDesiredGrace <= 0 {
		stuckDesiredGrace = 600
	}

	autoscaleInterval := int(cfg.AutoScaleInterval / time.Second)
	if autoscaleInterval <= 0 {
		autoscaleInterval = 30
	}

	autoscaleCooldown := int(cfg.AutoScaleCooldown / time.Second)
	if autoscaleCooldown <= 0 {
		autoscaleCooldown = 120
	}

	return RuntimeSettings{
		AlertsEnabled:                             cfg.AlertsEnabled,
		AutoActionsEnabled:                        cfg.AutoActionsEnabled,
		ProviderPolicyEngineEnabled:               cfg.ProviderPolicyEngineEnabled,
		AdaptiveRetryEnabled:                      cfg.AdaptiveRetryEnabled,
		ProviderAutoprotectEnabled:                cfg.ProviderAutoprotectEnabled,
		AlertErrorRateThreshold:                   cfg.AlertErrorRateThreshold,
		AlertHeartbeatGraceSecond:                 grace,
		AlertCooldownSecond:                       cooldown,
		AlertCheckIntervalSecond:                  alertCheckInterval,
		StaleWorkerTTLSecond:                      staleWorkerTTL,
		StuckDesiredGraceSecond:                   stuckDesiredGrace,
		AutoscaleEnabled:                          cfg.AutoScaleEnabled,
		AutoscaleIntervalSecond:                   autoscaleInterval,
		AutoscaleCooldownSecond:                   autoscaleCooldown,
		AutoscaleMinDesired:                       cfg.AutoScaleMinDesired,
		AutoscaleMaxDesired:                       cfg.AutoScaleMaxDesired,
		AutoscaleCanaryPercent:                    cfg.AutoScaleCanaryPercent,
		QuarantineErrorRateThreshold:              cfg.QuarantineErrorRate,
		ProviderTempfailWarnRate:                  cfg.ProviderTempfailWarnRate,
		ProviderTempfailCriticalRate:              cfg.ProviderTempfailCriticalRate,
		ProviderRejectWarnRate:                    cfg.ProviderRejectWarnRate,
		ProviderRejectCriticalRate:                cfg.ProviderRejectCriticalRate,
		ProviderUnknownWarnRate:                   cfg.ProviderUnknownWarnRate,
		ProviderUnknownCriticalRate:               cfg.ProviderUnknownCriticalRate,
		PolicyCanaryAutopilotEnabled:              cfg.PolicyCanaryAutopilotEnabled,
		PolicyCanaryWindowMinutes:                 cfg.PolicyCanaryWindowMinutes,
		PolicyCanaryRequiredHealthWindows:         cfg.PolicyCanaryRequiredHealthWindows,
		PolicyCanaryUnknownRegressionThreshold:    cfg.PolicyCanaryUnknownRegressionThreshold,
		PolicyCanaryTempfailRecoveryDropThreshold: cfg.PolicyCanaryTempfailRecoveryDropThreshold,
		PolicyCanaryPolicyBlockSpikeThreshold:     cfg.PolicyCanaryPolicyBlockSpikeThreshold,
		PolicyCanaryMinProviderWorkers:            cfg.PolicyCanaryMinProviderWorkers,
		UIOverviewLiveIntervalSecond:              5,
		UIWorkersRefreshSecond:                    10,
		UIPoolsRefreshSecond:                      10,
		UIAlertsRefreshSecond:                     30,
	}
}

func normalizeRuntimeSettings(in RuntimeSettings, defaults RuntimeSettings) RuntimeSettings {
	out := in

	if out.AlertHeartbeatGraceSecond <= 0 {
		out.AlertHeartbeatGraceSecond = defaults.AlertHeartbeatGraceSecond
	}

	if out.AlertCooldownSecond <= 0 {
		out.AlertCooldownSecond = defaults.AlertCooldownSecond
	}

	if out.AlertCheckIntervalSecond <= 0 {
		out.AlertCheckIntervalSecond = defaults.AlertCheckIntervalSecond
	}

	if out.StaleWorkerTTLSecond <= 0 {
		out.StaleWorkerTTLSecond = defaults.StaleWorkerTTLSecond
	}

	if out.StuckDesiredGraceSecond <= 0 {
		out.StuckDesiredGraceSecond = defaults.StuckDesiredGraceSecond
	}

	if out.AlertErrorRateThreshold < 0 {
		out.AlertErrorRateThreshold = defaults.AlertErrorRateThreshold
	}

	if out.AutoscaleIntervalSecond <= 0 {
		out.AutoscaleIntervalSecond = defaults.AutoscaleIntervalSecond
	}

	if out.AutoscaleCooldownSecond <= 0 {
		out.AutoscaleCooldownSecond = defaults.AutoscaleCooldownSecond
	}

	if out.AutoscaleMinDesired < 0 {
		out.AutoscaleMinDesired = defaults.AutoscaleMinDesired
	}

	if out.AutoscaleMaxDesired < out.AutoscaleMinDesired {
		out.AutoscaleMaxDesired = out.AutoscaleMinDesired
	}

	defaultCanary := defaults.AutoscaleCanaryPercent
	if defaultCanary <= 0 {
		defaultCanary = 100
	}

	if out.AutoscaleCanaryPercent <= 0 {
		out.AutoscaleCanaryPercent = defaultCanary
	}
	if out.AutoscaleCanaryPercent > 100 {
		out.AutoscaleCanaryPercent = 100
	}

	if out.QuarantineErrorRateThreshold < 0 {
		out.QuarantineErrorRateThreshold = defaults.QuarantineErrorRateThreshold
	}

	if out.ProviderTempfailWarnRate < 0 {
		out.ProviderTempfailWarnRate = defaults.ProviderTempfailWarnRate
	}

	if out.ProviderTempfailCriticalRate < 0 {
		out.ProviderTempfailCriticalRate = defaults.ProviderTempfailCriticalRate
	}

	if out.ProviderRejectWarnRate < 0 {
		out.ProviderRejectWarnRate = defaults.ProviderRejectWarnRate
	}

	if out.ProviderRejectCriticalRate < 0 {
		out.ProviderRejectCriticalRate = defaults.ProviderRejectCriticalRate
	}

	if out.ProviderUnknownWarnRate < 0 {
		out.ProviderUnknownWarnRate = defaults.ProviderUnknownWarnRate
	}

	if out.ProviderUnknownCriticalRate < 0 {
		out.ProviderUnknownCriticalRate = defaults.ProviderUnknownCriticalRate
	}

	if out.ProviderTempfailCriticalRate < out.ProviderTempfailWarnRate {
		out.ProviderTempfailCriticalRate = out.ProviderTempfailWarnRate
	}

	if out.ProviderRejectCriticalRate < out.ProviderRejectWarnRate {
		out.ProviderRejectCriticalRate = out.ProviderRejectWarnRate
	}

	if out.ProviderUnknownCriticalRate < out.ProviderUnknownWarnRate {
		out.ProviderUnknownCriticalRate = out.ProviderUnknownWarnRate
	}

	if out.PolicyCanaryWindowMinutes <= 0 {
		out.PolicyCanaryWindowMinutes = defaults.PolicyCanaryWindowMinutes
	}

	if out.PolicyCanaryRequiredHealthWindows <= 0 {
		out.PolicyCanaryRequiredHealthWindows = defaults.PolicyCanaryRequiredHealthWindows
	}

	if out.PolicyCanaryUnknownRegressionThreshold < 0 {
		out.PolicyCanaryUnknownRegressionThreshold = defaults.PolicyCanaryUnknownRegressionThreshold
	}

	if out.PolicyCanaryTempfailRecoveryDropThreshold < 0 {
		out.PolicyCanaryTempfailRecoveryDropThreshold = defaults.PolicyCanaryTempfailRecoveryDropThreshold
	}

	if out.PolicyCanaryPolicyBlockSpikeThreshold < 0 {
		out.PolicyCanaryPolicyBlockSpikeThreshold = defaults.PolicyCanaryPolicyBlockSpikeThreshold
	}

	if out.PolicyCanaryMinProviderWorkers <= 0 {
		out.PolicyCanaryMinProviderWorkers = defaults.PolicyCanaryMinProviderWorkers
	}

	if out.UIOverviewLiveIntervalSecond <= 0 {
		out.UIOverviewLiveIntervalSecond = defaults.UIOverviewLiveIntervalSecond
	}

	if out.UIWorkersRefreshSecond <= 0 {
		out.UIWorkersRefreshSecond = defaults.UIWorkersRefreshSecond
	}

	if out.UIPoolsRefreshSecond <= 0 {
		out.UIPoolsRefreshSecond = defaults.UIPoolsRefreshSecond
	}

	if out.UIAlertsRefreshSecond <= 0 {
		out.UIAlertsRefreshSecond = defaults.UIAlertsRefreshSecond
	}

	return out
}

func (s *Store) GetRuntimeSettings(ctx context.Context, defaults RuntimeSettings) (RuntimeSettings, error) {
	value, err := s.rdb.Get(ctx, runtimeSettingsKey).Result()
	if err == redis.Nil || value == "" {
		return defaults, nil
	}
	if err != nil {
		return defaults, err
	}

	var settings RuntimeSettings
	if err := json.Unmarshal([]byte(value), &settings); err != nil {
		return defaults, err
	}

	// Backward-compat: older runtime settings payloads may not include these fields yet.
	var raw map[string]json.RawMessage
	if err := json.Unmarshal([]byte(value), &raw); err == nil {
		if _, ok := raw["provider_policy_engine_enabled"]; !ok {
			settings.ProviderPolicyEngineEnabled = defaults.ProviderPolicyEngineEnabled
		}
		if _, ok := raw["adaptive_retry_enabled"]; !ok {
			settings.AdaptiveRetryEnabled = defaults.AdaptiveRetryEnabled
		}
		if _, ok := raw["provider_autoprotect_enabled"]; !ok {
			settings.ProviderAutoprotectEnabled = defaults.ProviderAutoprotectEnabled
		}
		if _, ok := raw["policy_canary_autopilot_enabled"]; !ok {
			settings.PolicyCanaryAutopilotEnabled = defaults.PolicyCanaryAutopilotEnabled
		}
		if _, ok := raw["alert_check_interval_seconds"]; !ok {
			settings.AlertCheckIntervalSecond = defaults.AlertCheckIntervalSecond
		}
		if _, ok := raw["stale_worker_ttl_seconds"]; !ok {
			settings.StaleWorkerTTLSecond = defaults.StaleWorkerTTLSecond
		}
		if _, ok := raw["stuck_desired_grace_seconds"]; !ok {
			settings.StuckDesiredGraceSecond = defaults.StuckDesiredGraceSecond
		}
		if _, ok := raw["autoscale_interval_seconds"]; !ok {
			settings.AutoscaleIntervalSecond = defaults.AutoscaleIntervalSecond
		}
		if _, ok := raw["autoscale_cooldown_seconds"]; !ok {
			settings.AutoscaleCooldownSecond = defaults.AutoscaleCooldownSecond
		}
		if _, ok := raw["autoscale_min_desired"]; !ok {
			settings.AutoscaleMinDesired = defaults.AutoscaleMinDesired
		}
		if _, ok := raw["autoscale_max_desired"]; !ok {
			settings.AutoscaleMaxDesired = defaults.AutoscaleMaxDesired
		}
		if _, ok := raw["provider_tempfail_warn_rate"]; !ok {
			settings.ProviderTempfailWarnRate = defaults.ProviderTempfailWarnRate
		}
		if _, ok := raw["provider_tempfail_critical_rate"]; !ok {
			settings.ProviderTempfailCriticalRate = defaults.ProviderTempfailCriticalRate
		}
		if _, ok := raw["provider_reject_warn_rate"]; !ok {
			settings.ProviderRejectWarnRate = defaults.ProviderRejectWarnRate
		}
		if _, ok := raw["provider_reject_critical_rate"]; !ok {
			settings.ProviderRejectCriticalRate = defaults.ProviderRejectCriticalRate
		}
		if _, ok := raw["provider_unknown_warn_rate"]; !ok {
			settings.ProviderUnknownWarnRate = defaults.ProviderUnknownWarnRate
		}
		if _, ok := raw["provider_unknown_critical_rate"]; !ok {
			settings.ProviderUnknownCriticalRate = defaults.ProviderUnknownCriticalRate
		}
		if _, ok := raw["policy_canary_window_minutes"]; !ok {
			settings.PolicyCanaryWindowMinutes = defaults.PolicyCanaryWindowMinutes
		}
		if _, ok := raw["policy_canary_required_health_windows"]; !ok {
			settings.PolicyCanaryRequiredHealthWindows = defaults.PolicyCanaryRequiredHealthWindows
		}
		if _, ok := raw["policy_canary_unknown_regression_threshold"]; !ok {
			settings.PolicyCanaryUnknownRegressionThreshold = defaults.PolicyCanaryUnknownRegressionThreshold
		}
		if _, ok := raw["policy_canary_tempfail_recovery_drop_threshold"]; !ok {
			settings.PolicyCanaryTempfailRecoveryDropThreshold = defaults.PolicyCanaryTempfailRecoveryDropThreshold
		}
		if _, ok := raw["policy_canary_policy_block_spike_threshold"]; !ok {
			settings.PolicyCanaryPolicyBlockSpikeThreshold = defaults.PolicyCanaryPolicyBlockSpikeThreshold
		}
		if _, ok := raw["policy_canary_min_provider_workers"]; !ok {
			settings.PolicyCanaryMinProviderWorkers = defaults.PolicyCanaryMinProviderWorkers
		}
	}

	return normalizeRuntimeSettings(settings, defaults), nil
}

func (s *Store) SaveRuntimeSettings(ctx context.Context, settings RuntimeSettings) error {
	payload, err := json.Marshal(settings)
	if err != nil {
		return err
	}

	previous, getErr := s.rdb.Get(ctx, runtimeSettingsKey).Result()
	if getErr != nil && getErr != redis.Nil {
		return getErr
	}

	pipe := s.rdb.TxPipeline()
	if previous != "" {
		pipe.Set(ctx, runtimeSettingsLastKey, previous, 0)
	}
	pipe.Set(ctx, runtimeSettingsKey, payload, 0)
	_, err = pipe.Exec(ctx)
	return err
}

func (s *Store) HasRuntimeSettingsSnapshot(ctx context.Context) (bool, error) {
	value, err := s.rdb.Get(ctx, runtimeSettingsLastKey).Result()
	if err == redis.Nil || value == "" {
		return false, nil
	}
	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Store) RollbackRuntimeSettings(ctx context.Context) error {
	snapshot, err := s.rdb.Get(ctx, runtimeSettingsLastKey).Result()
	if err == redis.Nil || snapshot == "" {
		return fmt.Errorf("no runtime settings snapshot available")
	}
	if err != nil {
		return err
	}

	current, currentErr := s.rdb.Get(ctx, runtimeSettingsKey).Result()
	if currentErr != nil && currentErr != redis.Nil {
		return currentErr
	}

	pipe := s.rdb.TxPipeline()
	pipe.Set(ctx, runtimeSettingsKey, snapshot, 0)
	if current != "" {
		pipe.Set(ctx, runtimeSettingsLastKey, current, 0)
	} else {
		pipe.Del(ctx, runtimeSettingsLastKey)
	}
	_, err = pipe.Exec(ctx)
	return err
}

func (s *Store) SetDesiredState(ctx context.Context, workerID string, state string) error {
	if workerID == "" {
		return fmt.Errorf("worker id is required")
	}
	normalized := normalizeDesiredState(state)
	if normalized == "" {
		return fmt.Errorf("invalid desired state")
	}

	now := time.Now().UTC().Format(time.RFC3339)
	pipe := s.rdb.Pipeline()
	pipe.Set(ctx, workerKey(workerID, "desired_state"), normalized, 0)
	pipe.Set(ctx, workerKey(workerID, "desired_state_updated"), now, 0)
	if normalized == "running" {
		pipe.Del(ctx, workerKey(workerID, "quarantined"))
	}
	_, err := pipe.Exec(ctx)
	return err
}

func (s *Store) SetWorkerQuarantined(ctx context.Context, workerID string, enabled bool, reason string) error {
	if workerID == "" {
		return fmt.Errorf("worker id is required")
	}

	if enabled {
		payload := map[string]string{
			"reason":     reason,
			"updated_at": time.Now().UTC().Format(time.RFC3339),
		}
		data, err := json.Marshal(payload)
		if err != nil {
			return err
		}

		pipe := s.rdb.Pipeline()
		pipe.Set(ctx, workerKey(workerID, "quarantined"), data, 0)
		pipe.Set(ctx, workerKey(workerID, "desired_state"), "draining", 0)
		pipe.Set(ctx, workerKey(workerID, "desired_state_updated"), time.Now().UTC().Format(time.RFC3339), 0)
		_, err = pipe.Exec(ctx)
		return err
	}

	now := time.Now().UTC().Format(time.RFC3339)
	pipe := s.rdb.Pipeline()
	pipe.Del(ctx, workerKey(workerID, "quarantined"))
	pipe.Set(ctx, workerKey(workerID, "desired_state"), "running", 0)
	pipe.Set(ctx, workerKey(workerID, "desired_state_updated"), now, 0)
	_, err := pipe.Exec(ctx)
	return err
}

func (s *Store) isWorkerQuarantined(ctx context.Context, workerID string) (bool, error) {
	value, err := s.rdb.Get(ctx, workerKey(workerID, "quarantined")).Result()
	if err == redis.Nil || value == "" {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

func (s *Store) GetDesiredStateUpdatedAt(ctx context.Context, workerID string) (time.Time, bool, error) {
	value, err := s.rdb.Get(ctx, workerKey(workerID, "desired_state_updated")).Result()
	if err == redis.Nil || value == "" {
		return time.Time{}, false, nil
	}
	if err != nil {
		return time.Time{}, false, err
	}
	parsed, err := time.Parse(time.RFC3339, value)
	if err != nil {
		return time.Time{}, false, err
	}
	return parsed, true, nil
}

func (s *Store) CleanupStaleKnownWorkers(ctx context.Context, cutoff time.Time) ([]string, error) {
	knownWorkers, err := s.GetKnownWorkerIDs(ctx)
	if err != nil {
		return nil, err
	}

	stale := make([]string, 0)
	for _, workerID := range knownWorkers {
		lastSeen, ok, seenErr := s.GetWorkerLastSeen(ctx, workerID)
		if seenErr != nil {
			continue
		}
		if !ok || lastSeen.IsZero() || lastSeen.After(cutoff) {
			continue
		}

		stale = append(stale, workerID)
	}

	if len(stale) == 0 {
		return stale, nil
	}

	pipe := s.rdb.Pipeline()
	for _, workerID := range stale {
		pipe.SRem(ctx, "workers:known", workerID)
		pipe.SRem(ctx, "workers:active", workerID)
		pipe.Del(ctx, staleWorkerDeleteKeys(workerID)...)
	}
	_, err = pipe.Exec(ctx)
	if err != nil {
		return nil, err
	}

	return stale, nil
}

func (s *Store) GetWorkers(ctx context.Context) ([]WorkerSummary, error) {
	ids, err := s.rdb.SMembers(ctx, "workers:active").Result()
	if err != nil {
		return nil, err
	}

	results := make([]WorkerSummary, 0, len(ids))
	for _, id := range ids {
		heartbeat, err := s.rdb.Get(ctx, workerKey(id, "heartbeat")).Result()
		if err == redis.Nil {
			_ = s.rdb.SRem(ctx, "workers:active", id).Err()
			continue
		}
		if err != nil {
			return nil, err
		}

		status, _ := s.rdb.Get(ctx, workerKey(id, "status")).Result()
		desired, _ := s.rdb.Get(ctx, workerKey(id, "desired_state")).Result()
		if desired == "" {
			desired = "running"
		}

		metaJSON, _ := s.rdb.Get(ctx, workerKey(id, "meta")).Result()
		var meta workerMeta
		if metaJSON != "" {
			_ = json.Unmarshal([]byte(metaJSON), &meta)
		}

		pool := meta.Pool
		if pool == "" {
			pool, _ = s.rdb.Get(ctx, workerKey(id, "pool")).Result()
		}

		quarantined, _ := s.isWorkerQuarantined(ctx, id)

		var stageMetrics *StageMetrics
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "stage_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := StageMetrics{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				stageMetrics = &parsed
			}
		}

		var smtpMetrics *SMTPMetrics
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "smtp_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := SMTPMetrics{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				smtpMetrics = &parsed
			}
		}

		var providerMetrics []ProviderMetric
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "provider_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := make([]ProviderMetric, 0)
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				providerMetrics = parsed
			}
		}

		var routingMetrics *RoutingMetrics
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "routing_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := RoutingMetrics{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				routingMetrics = &parsed
			}
		}

		var sessionMetrics *SessionMetrics
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "session_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := SessionMetrics{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				sessionMetrics = &parsed
			}
		}

		var attemptRouteMetrics *AttemptRouteMetrics
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "attempt_route_metrics")).Result(); payloadErr == nil && payload != "" {
			parsed := AttemptRouteMetrics{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				attemptRouteMetrics = &parsed
			}
		}

		retryAntiAffinityHits := int64(0)
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "retry_anti_affinity_hits")).Result(); payloadErr == nil && payload != "" {
			if parsed, parseErr := strconv.ParseInt(payload, 10, 64); parseErr == nil {
				retryAntiAffinityHits = parsed
			}
		}

		unknownReasonTags := map[string]int64{}
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "unknown_reason_tags")).Result(); payloadErr == nil && payload != "" {
			parsed := map[string]int64{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				unknownReasonTags = parsed
			}
		}

		sessionStrategyID, _ := s.rdb.Get(ctx, workerKey(id, "session_strategy_id")).Result()

		reasonTagCounts := map[string]int64{}
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "reason_tag_counters")).Result(); payloadErr == nil && payload != "" {
			parsed := map[string]int64{}
			if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr == nil {
				reasonTagCounts = parsed
			}
		}

		poolHealthHint := 0.0
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "pool_health_hint")).Result(); payloadErr == nil && payload != "" {
			if parsed, parseErr := strconv.ParseFloat(payload, 64); parseErr == nil {
				poolHealthHint = parsed
			}
		}

		results = append(results, WorkerSummary{
			WorkerID:              id,
			Host:                  meta.Host,
			IPAddress:             meta.IPAddress,
			Version:               meta.Version,
			Pool:                  pool,
			Tags:                  meta.Tags,
			Status:                defaultString(status, "unknown"),
			DesiredState:          desired,
			Quarantined:           quarantined,
			LastHeartbeat:         heartbeat,
			CurrentJobID:          meta.CurrentJobID,
			CurrentChunkID:        meta.CurrentChunkID,
			CorrelationID:         meta.CorrelationID,
			StageMetrics:          stageMetrics,
			SMTPMetrics:           smtpMetrics,
			ProviderMetrics:       providerMetrics,
			RoutingMetrics:        routingMetrics,
			SessionMetrics:        sessionMetrics,
			AttemptRouteMetrics:   attemptRouteMetrics,
			RetryAntiAffinityHits: retryAntiAffinityHits,
			UnknownReasonTags:     unknownReasonTags,
			SessionStrategyID:     sessionStrategyID,
			ReasonTagCounts:       reasonTagCounts,
			PoolHealthHint:        poolHealthHint,
		})
	}

	return results, nil
}

func (s *Store) GetPools(ctx context.Context) ([]PoolSummary, error) {
	pools, err := s.rdb.SMembers(ctx, "pools:known").Result()
	if err != nil {
		return nil, err
	}

	workers, err := s.GetWorkers(ctx)
	if err != nil {
		return nil, err
	}

	onlineCounts := make(map[string]int)
	healthScoreTotals := make(map[string]float64)
	healthScoreCounts := make(map[string]int)
	for _, worker := range workers {
		if worker.Pool == "" {
			continue
		}
		onlineCounts[worker.Pool]++
		if worker.PoolHealthHint > 0 {
			healthScoreTotals[worker.Pool] += worker.PoolHealthHint
			healthScoreCounts[worker.Pool]++
		}
	}

	results := make([]PoolSummary, 0, len(pools))
	for _, pool := range pools {
		desired := 0
		value, err := s.rdb.Get(ctx, poolKey(pool, "desired_count")).Result()
		if err == nil && value != "" {
			if parsed, parseErr := parseInt(value); parseErr == nil {
				desired = parsed
			}
		}

		healthScore := 0.0
		if healthScoreCounts[pool] > 0 {
			healthScore = healthScoreTotals[pool] / float64(healthScoreCounts[pool])
		}

		results = append(results, PoolSummary{
			Pool:        pool,
			Online:      onlineCounts[pool],
			Desired:     desired,
			HealthScore: healthScore,
		})
	}

	return results, nil
}

func (s *Store) SetPoolDesiredCount(ctx context.Context, pool string, desired int) error {
	if pool == "" {
		return fmt.Errorf("pool is required")
	}
	if desired < 0 {
		return fmt.Errorf("desired must be >= 0")
	}
	pipe := s.rdb.Pipeline()
	pipe.Set(ctx, poolKey(pool, "desired_count"), desired, 0)
	pipe.SAdd(ctx, "pools:known", pool)
	_, err := pipe.Exec(ctx)
	return err
}

func (s *Store) ActivateIncident(ctx context.Context, key, incidentType, severity, message string, contextData map[string]interface{}) (IncidentRecord, bool, error) {
	now := time.Now().UTC().Format(time.RFC3339)
	existing, ok, err := s.GetIncident(ctx, key)
	if err != nil {
		return IncidentRecord{}, false, err
	}

	if ok && existing.Status == "active" {
		existing.Message = message
		existing.Context = contextData
		existing.Severity = severity
		existing.Type = incidentType
		existing.UpdatedAt = now
		if err := s.saveIncident(ctx, existing); err != nil {
			return IncidentRecord{}, false, err
		}
		return existing, false, nil
	}

	record := IncidentRecord{
		Key:       key,
		Type:      incidentType,
		Severity:  severity,
		Status:    "active",
		Message:   message,
		Context:   contextData,
		OpenedAt:  now,
		UpdatedAt: now,
	}
	if ok && existing.OpenedAt != "" {
		record.OpenedAt = existing.OpenedAt
	}

	if err := s.saveIncident(ctx, record); err != nil {
		return IncidentRecord{}, false, err
	}

	return record, true, nil
}

func (s *Store) ResolveIncident(ctx context.Context, key, message string, contextData map[string]interface{}) (IncidentRecord, bool, error) {
	existing, ok, err := s.GetIncident(ctx, key)
	if err != nil {
		return IncidentRecord{}, false, err
	}
	if !ok || existing.Status != "active" {
		return IncidentRecord{}, false, nil
	}

	now := time.Now().UTC().Format(time.RFC3339)
	existing.Status = "resolved"
	if message != "" {
		existing.Message = message
	}
	existing.Context = contextData
	existing.UpdatedAt = now
	existing.ResolvedAt = now

	if err := s.saveIncident(ctx, existing); err != nil {
		return IncidentRecord{}, false, err
	}

	return existing, true, nil
}

func (s *Store) GetIncident(ctx context.Context, key string) (IncidentRecord, bool, error) {
	if key == "" {
		return IncidentRecord{}, false, nil
	}

	value, err := s.rdb.Get(ctx, incidentKey(key)).Result()
	if err == redis.Nil || value == "" {
		return IncidentRecord{}, false, nil
	}
	if err != nil {
		return IncidentRecord{}, false, err
	}

	var record IncidentRecord
	if err := json.Unmarshal([]byte(value), &record); err != nil {
		return IncidentRecord{}, false, err
	}
	return record, true, nil
}

func (s *Store) ListIncidents(ctx context.Context, limit int, includeResolved bool) ([]IncidentRecord, error) {
	if limit <= 0 {
		limit = 100
	}

	keys, err := s.rdb.ZRevRange(ctx, incidentsIndexKey, 0, int64(limit-1)).Result()
	if err != nil {
		return nil, err
	}

	incidents := make([]IncidentRecord, 0, len(keys))
	for _, key := range keys {
		record, ok, getErr := s.GetIncident(ctx, key)
		if getErr != nil || !ok {
			continue
		}
		if !includeResolved && record.Status != "active" {
			continue
		}
		incidents = append(incidents, record)
	}

	return incidents, nil
}

func (s *Store) saveIncident(ctx context.Context, record IncidentRecord) error {
	payload, err := json.Marshal(record)
	if err != nil {
		return err
	}

	score := float64(time.Now().UTC().Unix())
	pipe := s.rdb.Pipeline()
	pipe.Set(ctx, incidentKey(record.Key), payload, 0)
	pipe.ZAdd(ctx, incidentsIndexKey, redis.Z{Score: score, Member: record.Key})
	if record.Status == "active" {
		pipe.SAdd(ctx, incidentsActiveKey, record.Key)
	} else {
		pipe.SRem(ctx, incidentsActiveKey, record.Key)
	}
	_, err = pipe.Exec(ctx)
	return err
}

func (s *Store) SetProviderMode(ctx context.Context, provider, mode, source string) (ProviderModeState, error) {
	rawProvider := strings.TrimSpace(provider)
	if rawProvider == "" {
		return ProviderModeState{}, fmt.Errorf("provider is required")
	}
	provider = normalizeProviderName(rawProvider)
	if provider == "" {
		return ProviderModeState{}, fmt.Errorf("unsupported provider")
	}

	mode = normalizeProviderMode(mode)
	if mode == "" {
		return ProviderModeState{}, fmt.Errorf("invalid provider mode")
	}

	source = strings.TrimSpace(strings.ToLower(source))
	if source == "" {
		source = "manual"
	}

	state := ProviderModeState{
		Provider:  provider,
		Mode:      mode,
		Source:    source,
		UpdatedAt: time.Now().UTC().Format(time.RFC3339),
	}

	payload, err := json.Marshal(state)
	if err != nil {
		return ProviderModeState{}, err
	}

	if err := s.rdb.HSet(ctx, providerModesKey, provider, payload).Err(); err != nil {
		return ProviderModeState{}, err
	}

	return state, nil
}

func (s *Store) GetProviderModes(ctx context.Context) (map[string]ProviderModeState, error) {
	values, err := s.rdb.HGetAll(ctx, providerModesKey).Result()
	if err != nil {
		return nil, err
	}

	return buildProviderModes(values), nil
}

func buildProviderModes(values map[string]string) map[string]ProviderModeState {
	modes := make(map[string]ProviderModeState, len(values))
	for provider, payload := range values {
		normalizedKey := normalizeProviderName(provider)

		parsed := ProviderModeState{}
		if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr != nil {
			continue
		}

		normalizedProvider := normalizeProviderName(parsed.Provider)
		if normalizedProvider == "" {
			normalizedProvider = normalizedKey
		}
		if normalizedProvider == "" {
			continue
		}

		parsed.Provider = normalizedProvider
		if normalizeProviderMode(parsed.Mode) == "" {
			parsed.Mode = "normal"
		}
		modes[normalizedProvider] = parsed
	}

	return modes
}

func (s *Store) GetProviderMode(ctx context.Context, provider string) (ProviderModeState, bool, error) {
	provider = normalizeProviderName(provider)
	if provider == "" {
		return ProviderModeState{}, false, nil
	}

	payload, err := s.rdb.HGet(ctx, providerModesKey, provider).Result()
	if err == redis.Nil || payload == "" {
		return ProviderModeState{}, false, nil
	}
	if err != nil {
		return ProviderModeState{}, false, err
	}

	parsed := ProviderModeState{}
	if unmarshalErr := json.Unmarshal([]byte(payload), &parsed); unmarshalErr != nil {
		return ProviderModeState{}, false, unmarshalErr
	}
	if parsed.Provider == "" {
		parsed.Provider = provider
	}
	if normalizeProviderMode(parsed.Mode) == "" {
		parsed.Mode = "normal"
	}

	return parsed, true, nil
}

func (s *Store) GetProviderPolicyState(ctx context.Context) (ProviderPolicyState, error) {
	value, err := s.rdb.Get(ctx, providerPolicyStateKey).Result()
	if err == redis.Nil || value == "" {
		return ProviderPolicyState{}, nil
	}
	if err != nil {
		return ProviderPolicyState{}, err
	}

	state := ProviderPolicyState{}
	if unmarshalErr := json.Unmarshal([]byte(value), &state); unmarshalErr != nil {
		return ProviderPolicyState{}, unmarshalErr
	}
	return state, nil
}

func (s *Store) MarkProviderPoliciesReloaded(ctx context.Context) (ProviderPolicyState, error) {
	current, err := s.GetProviderPolicyState(ctx)
	if err != nil {
		return ProviderPolicyState{}, err
	}

	current.ReloadCount++
	current.LastReloadAt = time.Now().UTC().Format(time.RFC3339)
	current.UpdatedAt = current.LastReloadAt

	payload, marshalErr := json.Marshal(current)
	if marshalErr != nil {
		return ProviderPolicyState{}, marshalErr
	}

	if setErr := s.rdb.Set(ctx, providerPolicyStateKey, payload, 0).Err(); setErr != nil {
		return ProviderPolicyState{}, setErr
	}

	return current, nil
}

func (s *Store) ListSMTPPolicyVersions(ctx context.Context) ([]SMTPPolicyVersionRecord, string, error) {
	values, err := s.rdb.HGetAll(ctx, smtpPolicyVersionsKey).Result()
	if err != nil {
		return nil, "", err
	}

	activeVersion, err := s.rdb.Get(ctx, smtpPolicyActiveKey).Result()
	if err == redis.Nil {
		activeVersion = ""
	} else if err != nil {
		return nil, "", err
	}

	items := buildSMTPPolicyVersionList(values, activeVersion)

	sort.Slice(items, func(i, j int) bool {
		if items[i].UpdatedAt == items[j].UpdatedAt {
			return items[i].Version < items[j].Version
		}
		return items[i].UpdatedAt > items[j].UpdatedAt
	})

	return items, normalizePolicyVersion(activeVersion), nil
}

func (s *Store) PromoteSMTPPolicyVersion(
	ctx context.Context,
	version string,
	canaryPercent int,
	triggeredBy string,
	notes string,
) (SMTPPolicyVersionRecord, error) {
	version = normalizePolicyVersion(version)
	if version == "" {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("version is required")
	}

	canaryPercent = normalizeCanaryPercent(canaryPercent)
	if triggeredBy == "" {
		triggeredBy = "manual"
	}

	records, activeVersion, err := s.loadSMTPPolicyVersionMap(ctx)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}
	targetRecord, exists := records[version]
	if !exists {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("policy version is not validated")
	}
	if s.policyPayloadStrictValidation && normalizePolicyValidationStatus(targetRecord.ValidationStatus) != policyValidationStatusValid {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("policy version must be validated before promote")
	}

	validation, err := s.preflightPolicyPayload(ctx, version, s.policyPayloadStrictValidation)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	now := time.Now().UTC().Format(time.RFC3339)
	records, target, nextActiveVersion := applySMTPPolicyPromoteState(
		records,
		activeVersion,
		version,
		canaryPercent,
		triggeredBy,
		now,
	)
	target.ValidationStatus = normalizePolicyValidationStatus(validation.Status)
	target.ValidationError = strings.TrimSpace(validation.ErrorMessage)
	target.PayloadChecksum = strings.TrimSpace(validation.Checksum)
	target.PayloadValidatedAt = strings.TrimSpace(validation.ValidatedAt)
	records[version] = target

	if err := s.saveSMTPPolicyVersionMap(ctx, records, nextActiveVersion); err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	entry := buildSMTPPolicyRolloutRecord("promote", version, canaryPercent, triggeredBy, notes, now)
	entry.ValidationStatus = target.ValidationStatus
	entry.PayloadChecksum = target.PayloadChecksum
	entry.PayloadValidatedAt = target.PayloadValidatedAt
	if historyErr := s.appendSMTPPolicyRolloutHistory(ctx, entry); historyErr != nil {
		return SMTPPolicyVersionRecord{}, historyErr
	}

	return target, nil
}

func (s *Store) ValidateSMTPPolicyVersion(ctx context.Context, version string, triggeredBy string, notes string) (SMTPPolicyVersionRecord, error) {
	version = normalizePolicyVersion(version)
	if version == "" {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("version is required")
	}
	if triggeredBy == "" {
		triggeredBy = "manual"
	}

	records, activeVersion, err := s.loadSMTPPolicyVersionMap(ctx)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	now := time.Now().UTC().Format(time.RFC3339)
	record := records[version]
	record.Version = version
	if record.Status == "" {
		record.Status = "draft"
	}
	record.Active = version == activeVersion
	record.UpdatedAt = now
	record.UpdatedBy = triggeredBy

	validation, validationErr := s.preflightPolicyPayload(ctx, version, true)
	if validationErr != nil {
		record.ValidationStatus = policyValidationStatusInvalid
		record.ValidationError = validationErr.Error()
		record.PayloadValidatedAt = now
		record.PayloadChecksum = ""
	} else {
		record.ValidationStatus = policyValidationStatusValid
		record.ValidationError = ""
		record.PayloadValidatedAt = validation.ValidatedAt
		record.PayloadChecksum = validation.Checksum
	}

	records[version] = record
	if saveErr := s.saveSMTPPolicyVersionMap(ctx, records, activeVersion); saveErr != nil {
		return SMTPPolicyVersionRecord{}, saveErr
	}

	entry := buildSMTPPolicyRolloutRecord("validate", version, record.CanaryPercent, triggeredBy, notes, now)
	entry.ValidationStatus = record.ValidationStatus
	entry.PayloadChecksum = record.PayloadChecksum
	entry.PayloadValidatedAt = record.PayloadValidatedAt
	if historyErr := s.appendSMTPPolicyRolloutHistory(ctx, entry); historyErr != nil {
		return SMTPPolicyVersionRecord{}, historyErr
	}

	if validationErr != nil {
		return record, validationErr
	}

	return record, nil
}

func (s *Store) RollbackSMTPPolicyVersion(ctx context.Context, triggeredBy string, notes string) (SMTPPolicyVersionRecord, error) {
	if triggeredBy == "" {
		triggeredBy = "manual"
	}

	records, activeVersion, err := s.loadSMTPPolicyVersionMap(ctx)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}
	if activeVersion == "" {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("no active policy version")
	}

	history, err := s.GetSMTPPolicyRolloutHistory(ctx, 50)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	targetVersion, targetCanary, err := selectSMTPRollbackTarget(records, activeVersion, history)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}
	targetRecord := records[targetVersion]
	if s.policyPayloadStrictValidation && normalizePolicyValidationStatus(targetRecord.ValidationStatus) != policyValidationStatusValid {
		return SMTPPolicyVersionRecord{}, fmt.Errorf("rollback target is not validated")
	}

	if _, err := s.preflightPolicyPayload(ctx, targetVersion, s.policyPayloadStrictValidation); err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	record, err := s.PromoteSMTPPolicyVersion(ctx, targetVersion, targetCanary, triggeredBy, notes)
	if err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	record.RolledBackAt = time.Now().UTC().Format(time.RFC3339)
	record.Active = true
	record.Status = "active"
	if err := s.upsertSMTPPolicyVersionRecord(ctx, record); err != nil {
		return SMTPPolicyVersionRecord{}, err
	}

	entry := buildSMTPPolicyRolloutRecord("rollback", targetVersion, targetCanary, triggeredBy, notes, record.RolledBackAt)
	entry.ValidationStatus = record.ValidationStatus
	entry.PayloadChecksum = record.PayloadChecksum
	entry.PayloadValidatedAt = record.PayloadValidatedAt
	if historyErr := s.appendSMTPPolicyRolloutHistory(ctx, entry); historyErr != nil {
		return SMTPPolicyVersionRecord{}, historyErr
	}

	return record, nil
}

func (s *Store) GetSMTPPolicyRolloutHistory(ctx context.Context, limit int) ([]SMTPPolicyRolloutRecord, error) {
	if limit <= 0 {
		limit = 20
	}
	values, err := s.rdb.LRange(ctx, smtpPolicyHistoryKey, 0, int64(limit-1)).Result()
	if err != nil {
		return nil, err
	}

	history := make([]SMTPPolicyRolloutRecord, 0, len(values))
	for _, payload := range values {
		entry := SMTPPolicyRolloutRecord{}
		if unmarshalErr := json.Unmarshal([]byte(payload), &entry); unmarshalErr != nil {
			continue
		}
		entry.Version = normalizePolicyVersion(entry.Version)
		if entry.Version == "" {
			continue
		}
		entry.Action = normalizePolicyAction(entry.Action)
		entry.CanaryPercent = normalizeCanaryPercent(entry.CanaryPercent)
		entry.ValidationStatus = normalizePolicyValidationStatus(entry.ValidationStatus)
		entry.PayloadChecksum = strings.TrimSpace(entry.PayloadChecksum)
		entry.PayloadValidatedAt = strings.TrimSpace(entry.PayloadValidatedAt)
		history = append(history, entry)
	}

	return history, nil
}

func (s *Store) loadSMTPPolicyVersionMap(ctx context.Context) (map[string]SMTPPolicyVersionRecord, string, error) {
	values, err := s.rdb.HGetAll(ctx, smtpPolicyVersionsKey).Result()
	if err != nil {
		return nil, "", err
	}

	activeVersion, err := s.rdb.Get(ctx, smtpPolicyActiveKey).Result()
	if err == redis.Nil {
		activeVersion = ""
	} else if err != nil {
		return nil, "", err
	}
	activeVersion = normalizePolicyVersion(activeVersion)

	records := make(map[string]SMTPPolicyVersionRecord, len(values))
	for version, payload := range values {
		normalizedVersion := normalizePolicyVersion(version)
		if normalizedVersion == "" {
			continue
		}

		record := SMTPPolicyVersionRecord{}
		if unmarshalErr := json.Unmarshal([]byte(payload), &record); unmarshalErr != nil {
			continue
		}

		record.Version = normalizedVersion
		record.Status = normalizePolicyVersionStatus(record.Status)
		record.CanaryPercent = normalizeCanaryPercent(record.CanaryPercent)
		record.ValidationStatus = normalizePolicyValidationStatus(record.ValidationStatus)
		record.ValidationError = strings.TrimSpace(record.ValidationError)
		record.PayloadChecksum = strings.TrimSpace(record.PayloadChecksum)
		record.PayloadValidatedAt = strings.TrimSpace(record.PayloadValidatedAt)
		record.Active = normalizedVersion == activeVersion
		records[normalizedVersion] = record
	}

	return records, activeVersion, nil
}

func buildSMTPPolicyVersionList(values map[string]string, activeVersion string) []SMTPPolicyVersionRecord {
	normalizedActiveVersion := normalizePolicyVersion(activeVersion)
	items := make([]SMTPPolicyVersionRecord, 0, len(values))
	for version, payload := range values {
		normalizedVersion := normalizePolicyVersion(version)
		if normalizedVersion == "" {
			continue
		}

		record := SMTPPolicyVersionRecord{}
		if unmarshalErr := json.Unmarshal([]byte(payload), &record); unmarshalErr != nil {
			continue
		}

		record.Version = normalizePolicyVersion(record.Version)
		if record.Version == "" {
			record.Version = normalizedVersion
		}
		record.Status = normalizePolicyVersionStatus(record.Status)
		record.CanaryPercent = normalizeCanaryPercent(record.CanaryPercent)
		record.ValidationStatus = normalizePolicyValidationStatus(record.ValidationStatus)
		record.ValidationError = strings.TrimSpace(record.ValidationError)
		record.PayloadChecksum = strings.TrimSpace(record.PayloadChecksum)
		record.PayloadValidatedAt = strings.TrimSpace(record.PayloadValidatedAt)
		record.Active = strings.EqualFold(record.Version, normalizedActiveVersion)

		items = append(items, record)
	}

	return items
}

func applySMTPPolicyPromoteState(
	records map[string]SMTPPolicyVersionRecord,
	activeVersion string,
	version string,
	canaryPercent int,
	triggeredBy string,
	now string,
) (map[string]SMTPPolicyVersionRecord, SMTPPolicyVersionRecord, string) {
	updated := make(map[string]SMTPPolicyVersionRecord, len(records))
	for key, value := range records {
		updated[key] = value
	}

	if activeVersion != "" && activeVersion != version {
		current := updated[activeVersion]
		current.Version = activeVersion
		current.Active = false
		current.Status = "inactive"
		current.UpdatedAt = now
		current.UpdatedBy = triggeredBy
		updated[activeVersion] = current
	}

	target := updated[version]
	target.Version = version
	target.Active = true
	target.Status = "active"
	target.CanaryPercent = canaryPercent
	target.UpdatedAt = now
	target.PromotedAt = now
	target.RolledBackAt = ""
	target.UpdatedBy = triggeredBy
	updated[version] = target

	return updated, target, version
}

func selectSMTPRollbackTarget(
	records map[string]SMTPPolicyVersionRecord,
	activeVersion string,
	history []SMTPPolicyRolloutRecord,
) (string, int, error) {
	for _, entry := range history {
		if entry.Action != "promote" {
			continue
		}
		normalizedVersion := normalizePolicyVersion(entry.Version)
		if normalizedVersion == "" || normalizedVersion == activeVersion {
			continue
		}
		if _, ok := records[normalizedVersion]; !ok {
			continue
		}

		return normalizedVersion, normalizeCanaryPercent(entry.CanaryPercent), nil
	}

	return "", 0, fmt.Errorf("no rollback target available")
}

func buildSMTPPolicyRolloutRecord(
	action string,
	version string,
	canaryPercent int,
	triggeredBy string,
	notes string,
	createdAt string,
) SMTPPolicyRolloutRecord {
	return SMTPPolicyRolloutRecord{
		Action:        normalizePolicyAction(action),
		Version:       normalizePolicyVersion(version),
		CanaryPercent: normalizeCanaryPercent(canaryPercent),
		TriggeredBy:   strings.TrimSpace(triggeredBy),
		Notes:         strings.TrimSpace(notes),
		CreatedAt:     createdAt,
	}
}

func (s *Store) saveSMTPPolicyVersionMap(ctx context.Context, records map[string]SMTPPolicyVersionRecord, activeVersion string) error {
	pipe := s.rdb.Pipeline()
	for version, record := range records {
		record.Version = normalizePolicyVersion(version)
		if record.Version == "" {
			continue
		}
		record.Status = normalizePolicyVersionStatus(record.Status)
		record.CanaryPercent = normalizeCanaryPercent(record.CanaryPercent)
		record.ValidationStatus = normalizePolicyValidationStatus(record.ValidationStatus)
		record.ValidationError = strings.TrimSpace(record.ValidationError)
		record.PayloadChecksum = strings.TrimSpace(record.PayloadChecksum)
		record.PayloadValidatedAt = strings.TrimSpace(record.PayloadValidatedAt)
		record.Active = record.Version == activeVersion
		payload, err := json.Marshal(record)
		if err != nil {
			return err
		}
		pipe.HSet(ctx, smtpPolicyVersionsKey, record.Version, payload)
	}

	if activeVersion == "" {
		pipe.Del(ctx, smtpPolicyActiveKey)
	} else {
		pipe.Set(ctx, smtpPolicyActiveKey, activeVersion, 0)
	}

	_, err := pipe.Exec(ctx)
	return err
}

func (s *Store) upsertSMTPPolicyVersionRecord(ctx context.Context, record SMTPPolicyVersionRecord) error {
	record.Version = normalizePolicyVersion(record.Version)
	if record.Version == "" {
		return fmt.Errorf("version is required")
	}

	record.Status = normalizePolicyVersionStatus(record.Status)
	record.CanaryPercent = normalizeCanaryPercent(record.CanaryPercent)
	record.ValidationStatus = normalizePolicyValidationStatus(record.ValidationStatus)
	record.ValidationError = strings.TrimSpace(record.ValidationError)
	record.PayloadChecksum = strings.TrimSpace(record.PayloadChecksum)
	record.PayloadValidatedAt = strings.TrimSpace(record.PayloadValidatedAt)
	payload, err := json.Marshal(record)
	if err != nil {
		return err
	}

	return s.rdb.HSet(ctx, smtpPolicyVersionsKey, record.Version, payload).Err()
}

func (s *Store) appendSMTPPolicyRolloutHistory(ctx context.Context, entry SMTPPolicyRolloutRecord) error {
	payload, err := json.Marshal(entry)
	if err != nil {
		return err
	}

	pipe := s.rdb.Pipeline()
	pipe.LPush(ctx, smtpPolicyHistoryKey, payload)
	pipe.LTrim(ctx, smtpPolicyHistoryKey, 0, 199)
	_, err = pipe.Exec(ctx)
	return err
}

func (s *Store) AppendPolicyShadowRun(ctx context.Context, record PolicyShadowRunRecord) error {
	record.RunUUID = strings.TrimSpace(record.RunUUID)
	if record.RunUUID == "" {
		return fmt.Errorf("run_uuid is required")
	}
	record.CandidateVersion = normalizePolicyVersion(record.CandidateVersion)
	record.ActiveVersion = normalizePolicyVersion(record.ActiveVersion)
	record.TriggeredBy = strings.TrimSpace(record.TriggeredBy)
	record.Notes = strings.TrimSpace(record.Notes)
	record.EvaluatedAt = strings.TrimSpace(record.EvaluatedAt)
	if record.EvaluatedAt == "" {
		record.EvaluatedAt = time.Now().UTC().Format(time.RFC3339)
	}

	providers := make([]string, 0, len(record.Providers))
	seen := make(map[string]struct{}, len(record.Providers))
	for _, provider := range record.Providers {
		normalized := normalizeProviderName(provider)
		if normalized == "" {
			continue
		}
		if _, exists := seen[normalized]; exists {
			continue
		}
		seen[normalized] = struct{}{}
		providers = append(providers, normalized)
	}
	if len(providers) == 0 {
		providers = append(providers, "generic")
	}
	sort.Strings(providers)
	record.Providers = providers

	payload, err := json.Marshal(record)
	if err != nil {
		return err
	}

	pipe := s.rdb.Pipeline()
	pipe.LPush(ctx, smtpPolicyShadowRunsKey, payload)
	pipe.LTrim(ctx, smtpPolicyShadowRunsKey, 0, 199)
	_, err = pipe.Exec(ctx)

	return err
}

func (s *Store) ListPolicyShadowRuns(ctx context.Context, limit int) ([]PolicyShadowRunRecord, error) {
	if limit <= 0 {
		limit = 20
	}

	values, err := s.rdb.LRange(ctx, smtpPolicyShadowRunsKey, 0, int64(limit-1)).Result()
	if err != nil {
		return nil, err
	}

	runs := make([]PolicyShadowRunRecord, 0, len(values))
	for _, payload := range values {
		record := PolicyShadowRunRecord{}
		if unmarshalErr := json.Unmarshal([]byte(payload), &record); unmarshalErr != nil {
			continue
		}

		record.RunUUID = strings.TrimSpace(record.RunUUID)
		record.CandidateVersion = normalizePolicyVersion(record.CandidateVersion)
		record.ActiveVersion = normalizePolicyVersion(record.ActiveVersion)
		record.TriggeredBy = strings.TrimSpace(record.TriggeredBy)
		record.Notes = strings.TrimSpace(record.Notes)
		record.EvaluatedAt = strings.TrimSpace(record.EvaluatedAt)
		if record.RunUUID == "" || record.CandidateVersion == "" {
			continue
		}

		providers := make([]string, 0, len(record.Providers))
		for _, provider := range record.Providers {
			normalized := normalizeProviderName(provider)
			if normalized == "" {
				continue
			}
			providers = append(providers, normalized)
		}
		if len(providers) == 0 {
			providers = []string{"generic"}
		}
		sort.Strings(providers)
		record.Providers = providers
		record.Summary.ProviderCount = len(providers)
		runs = append(runs, record)
	}

	return runs, nil
}

func (s *Store) preflightPolicyPayload(ctx context.Context, version string, enforce bool) (PolicyPayloadValidation, error) {
	now := time.Now().UTC().Format(time.RFC3339)
	result := PolicyPayloadValidation{
		Version:     normalizePolicyVersion(version),
		ValidatedAt: now,
		Status:      policyValidationStatusPending,
	}

	if !enforce {
		return result, nil
	}
	if s.policyPayloadValidator == nil {
		result.Status = policyValidationStatusInvalid
		result.ErrorMessage = "policy payload validator is not configured"
		return result, fmt.Errorf(result.ErrorMessage)
	}

	validation, err := s.policyPayloadValidator.ValidateVersion(ctx, version)
	if err != nil {
		validation.Status = policyValidationStatusInvalid
		if strings.TrimSpace(validation.ValidatedAt) == "" {
			validation.ValidatedAt = now
		}
		return validation, err
	}

	validation.Status = normalizePolicyValidationStatus(validation.Status)
	if validation.Status == policyValidationStatusPending {
		validation.Status = policyValidationStatusValid
	}
	if strings.TrimSpace(validation.ValidatedAt) == "" {
		validation.ValidatedAt = now
	}

	return validation, nil
}

func incidentKey(key string) string {
	return fmt.Sprintf("control_plane:incident:%s", key)
}

func normalizeProviderName(provider string) string {
	provider = strings.ToLower(strings.TrimSpace(provider))
	switch provider {
	case "gmail", "microsoft", "yahoo", "generic":
		return provider
	default:
		return ""
	}
}

func normalizeProviderMode(mode string) string {
	mode = strings.ToLower(strings.TrimSpace(mode))
	switch mode {
	case "normal", "cautious", "drain", "quarantine", "degraded_probe":
		return mode
	default:
		return ""
	}
}

func normalizePolicyVersion(version string) string {
	return strings.TrimSpace(version)
}

func normalizePolicyVersionStatus(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "active", "inactive", "draft":
		return strings.ToLower(strings.TrimSpace(status))
	default:
		return "draft"
	}
}

func normalizePolicyAction(action string) string {
	switch strings.ToLower(strings.TrimSpace(action)) {
	case "promote", "rollback", "validate":
		return strings.ToLower(strings.TrimSpace(action))
	default:
		return "promote"
	}
}

func normalizePolicyValidationStatus(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case policyValidationStatusValid:
		return policyValidationStatusValid
	case policyValidationStatusInvalid:
		return policyValidationStatusInvalid
	default:
		return policyValidationStatusPending
	}
}

func normalizeCanaryPercent(value int) int {
	if value <= 0 {
		return 100
	}
	if value > 100 {
		return 100
	}
	return value
}

func staleWorkerDeleteKeys(workerID string) []string {
	return []string{
		workerKey(workerID, "status"),
		workerKey(workerID, "heartbeat"),
		workerKey(workerID, "last_seen"),
		workerKey(workerID, "meta"),
		workerKey(workerID, "metrics"),
		workerKey(workerID, "stage_metrics"),
		workerKey(workerID, "smtp_metrics"),
		workerKey(workerID, "provider_metrics"),
		workerKey(workerID, "routing_metrics"),
		workerKey(workerID, "session_metrics"),
		workerKey(workerID, "attempt_route_metrics"),
		workerKey(workerID, "retry_anti_affinity_hits"),
		workerKey(workerID, "unknown_reason_tags"),
		workerKey(workerID, "session_strategy_id"),
		workerKey(workerID, "reason_tag_counters"),
		workerKey(workerID, "pool_health_hint"),
		workerKey(workerID, "pool"),
		workerKey(workerID, "desired_state"),
		workerKey(workerID, "desired_state_updated"),
		workerKey(workerID, "quarantined"),
	}
}

func workerKey(workerID string, field string) string {
	return fmt.Sprintf("worker:%s:%s", workerID, field)
}

func poolKey(pool string, field string) string {
	return fmt.Sprintf("pool:%s:%s", pool, field)
}

func defaultString(value, fallback string) string {
	if value == "" {
		return fallback
	}
	return value
}

func normalizeStatus(status string) string {
	switch status {
	case "running", "paused", "draining", "stopped":
		return status
	case "":
		return "running"
	default:
		return ""
	}
}

func normalizeDesiredState(state string) string {
	switch state {
	case "running", "paused", "draining", "stopped":
		return state
	case "resume":
		return "running"
	default:
		return ""
	}
}

func parseInt(value string) (int, error) {
	parsed, err := strconv.Atoi(value)
	if err != nil {
		return 0, err
	}
	return parsed, nil
}
