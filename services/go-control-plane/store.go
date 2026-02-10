package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"strconv"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

type Store struct {
	rdb          *redis.Client
	heartbeatTTL time.Duration
}

const (
	runtimeSettingsKey     = "control_plane:runtime_settings"
	incidentsIndexKey      = "control_plane:incidents:index"
	incidentsActiveKey     = "control_plane:incidents:active"
	providerModesKey       = "control_plane:provider_modes"
	providerPolicyStateKey = "control_plane:provider_policy_state"
)

type RuntimeSettings struct {
	AlertsEnabled                bool    `json:"alerts_enabled"`
	AutoActionsEnabled           bool    `json:"auto_actions_enabled"`
	AlertErrorRateThreshold      float64 `json:"alert_error_rate_threshold"`
	AlertHeartbeatGraceSecond    int     `json:"alert_heartbeat_grace_seconds"`
	AlertCooldownSecond          int     `json:"alert_cooldown_seconds"`
	AutoscaleEnabled             bool    `json:"autoscale_enabled"`
	AutoscaleCanaryPercent       int     `json:"autoscale_canary_percent"`
	QuarantineErrorRateThreshold float64 `json:"quarantine_error_rate_threshold"`
	UIOverviewLiveIntervalSecond int     `json:"ui_overview_live_interval_seconds"`
	UIWorkersRefreshSecond       int     `json:"ui_workers_refresh_seconds"`
	UIPoolsRefreshSecond         int     `json:"ui_pools_refresh_seconds"`
	UIAlertsRefreshSecond        int     `json:"ui_alerts_refresh_seconds"`
}

func NewStore(rdb *redis.Client, ttl time.Duration) *Store {
	return &Store{
		rdb:          rdb,
		heartbeatTTL: ttl,
	}
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
	grace := int(cfg.AlertHeartbeatGrace / time.Second)
	if grace <= 0 {
		grace = 120
	}

	cooldown := int(cfg.AlertCooldown / time.Second)
	if cooldown <= 0 {
		cooldown = 300
	}

	return RuntimeSettings{
		AlertsEnabled:                cfg.AlertsEnabled,
		AutoActionsEnabled:           cfg.AutoActionsEnabled,
		AlertErrorRateThreshold:      cfg.AlertErrorRateThreshold,
		AlertHeartbeatGraceSecond:    grace,
		AlertCooldownSecond:          cooldown,
		AutoscaleEnabled:             cfg.AutoScaleEnabled,
		AutoscaleCanaryPercent:       cfg.AutoScaleCanaryPercent,
		QuarantineErrorRateThreshold: cfg.QuarantineErrorRate,
		UIOverviewLiveIntervalSecond: 5,
		UIWorkersRefreshSecond:       10,
		UIPoolsRefreshSecond:         10,
		UIAlertsRefreshSecond:        30,
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

	if out.AlertErrorRateThreshold < 0 {
		out.AlertErrorRateThreshold = defaults.AlertErrorRateThreshold
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

	return normalizeRuntimeSettings(settings, defaults), nil
}

func (s *Store) SaveRuntimeSettings(ctx context.Context, settings RuntimeSettings) error {
	payload, err := json.Marshal(settings)
	if err != nil {
		return err
	}

	return s.rdb.Set(ctx, runtimeSettingsKey, payload, 0).Err()
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

		poolHealthHint := 0.0
		if payload, payloadErr := s.rdb.Get(ctx, workerKey(id, "pool_health_hint")).Result(); payloadErr == nil && payload != "" {
			if parsed, parseErr := strconv.ParseFloat(payload, 64); parseErr == nil {
				poolHealthHint = parsed
			}
		}

		results = append(results, WorkerSummary{
			WorkerID:        id,
			Host:            meta.Host,
			IPAddress:       meta.IPAddress,
			Version:         meta.Version,
			Pool:            pool,
			Status:          defaultString(status, "unknown"),
			DesiredState:    desired,
			Quarantined:     quarantined,
			LastHeartbeat:   heartbeat,
			CurrentJobID:    meta.CurrentJobID,
			CurrentChunkID:  meta.CurrentChunkID,
			CorrelationID:   meta.CorrelationID,
			StageMetrics:    stageMetrics,
			SMTPMetrics:     smtpMetrics,
			ProviderMetrics: providerMetrics,
			PoolHealthHint:  poolHealthHint,
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
	case "normal", "cautious", "drain":
		return mode
	default:
		return ""
	}
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
