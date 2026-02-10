package main

import (
	"context"
	"encoding/json"
	"log"
	"strings"
	"time"
)

type AlertEvent struct {
	Type      string
	Severity  string
	Message   string
	Context   map[string]interface{}
	CreatedAt time.Time
}

type AlertService struct {
	instanceID  string
	store       *Store
	snapshots   *SnapshotStore
	cfg         Config
	notifier    Notifier
	stopChannel chan struct{}
	checkTicker *time.Ticker
}

func NewAlertService(store *Store, snapshots *SnapshotStore, cfg Config, notifier Notifier, instanceID string) *AlertService {
	return &AlertService{
		instanceID:  instanceID,
		store:       store,
		snapshots:   snapshots,
		cfg:         cfg,
		notifier:    notifier,
		stopChannel: make(chan struct{}),
	}
}

func (s *AlertService) Start() {
	interval := s.cfg.AlertCheckInterval
	if interval <= 0 {
		interval = 30 * time.Second
	}

	s.checkTicker = time.NewTicker(interval)
	go func() {
		defer s.checkTicker.Stop()
		for {
			select {
			case <-s.checkTicker.C:
				s.runChecks()
			case <-s.stopChannel:
				return
			}
		}
	}()
}

func (s *AlertService) Stop() {
	close(s.stopChannel)
}

func (s *AlertService) runChecks() {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if !s.shouldRun(ctx) {
		return
	}

	settings := s.loadRuntimeSettings(ctx)

	s.cleanupStaleWorkers(ctx, settings)

	if !settings.AlertsEnabled && !settings.AutoActionsEnabled {
		return
	}

	s.checkOfflineWorkers(ctx, settings)
	s.checkPoolCapacity(ctx, settings)
	s.checkWorkerErrorRate(ctx, settings)
	s.checkStuckDesiredState(ctx, settings)
	s.checkProviderHealth(ctx, settings)
}

func (s *AlertService) shouldRun(ctx context.Context) bool {
	if !s.cfg.LeaderLockEnabled {
		return true
	}

	ok, err := s.store.HoldLeaderLease(ctx, "alerts", s.instanceID, s.cfg.LeaderLockTTL)
	if err != nil {
		log.Printf("alert: leader lock error: %v", err)
		return false
	}

	return ok
}

func (s *AlertService) cleanupStaleWorkers(ctx context.Context, settings RuntimeSettings) {
	if s.cfg.StaleWorkerTTL <= 0 {
		return
	}

	cutoff := time.Now().UTC().Add(-s.cfg.StaleWorkerTTL)
	staleWorkers, err := s.store.CleanupStaleKnownWorkers(ctx, cutoff)
	if err != nil {
		log.Printf("alert: stale worker cleanup failed: %v", err)
		return
	}

	for _, workerID := range staleWorkers {
		_, _, _ = s.store.ResolveIncident(ctx, incidentStateKey("worker_offline", workerID), "Worker removed during stale cleanup.", map[string]interface{}{
			"worker_id": workerID,
		})
		_, _, _ = s.store.ResolveIncident(ctx, incidentStateKey("worker_error_rate", workerID), "Worker removed during stale cleanup.", map[string]interface{}{
			"worker_id": workerID,
		})
		_, _, _ = s.store.ResolveIncident(ctx, incidentStateKey("worker_stuck_desired", workerID), "Worker removed during stale cleanup.", map[string]interface{}{
			"worker_id": workerID,
		})

		if settings.AlertsEnabled {
			s.dispatchAlert(ctx, AlertEvent{
				Type:      "worker_stale_removed",
				Severity:  "warning",
				Message:   "Worker removed from active state after stale TTL exceeded",
				Context:   map[string]interface{}{"worker_id": workerID, "cutoff": cutoff.Format(time.RFC3339)},
				CreatedAt: time.Now().UTC(),
			})
		}
	}
}

func (s *AlertService) checkOfflineWorkers(ctx context.Context, settings RuntimeSettings) {
	workerIDs, err := s.store.GetKnownWorkerIDs(ctx)
	if err != nil {
		log.Printf("alert: failed to load known workers: %v", err)
		return
	}

	grace := time.Duration(settings.AlertHeartbeatGraceSecond) * time.Second
	if grace <= 0 {
		grace = 120 * time.Second
	}

	now := time.Now().UTC()
	for _, workerID := range workerIDs {
		lastSeen, ok, seenErr := s.store.GetWorkerLastSeen(ctx, workerID)
		if seenErr != nil {
			log.Printf("alert: failed to get last seen for %s: %v", workerID, seenErr)
			continue
		}

		active := false
		contextData := map[string]interface{}{"worker_id": workerID}
		if ok {
			contextData["last_seen"] = lastSeen.Format(time.RFC3339)
			active = now.Sub(lastSeen) > grace
		}

		s.syncIncident(
			ctx,
			settings,
			incidentStateKey("worker_offline", workerID),
			"worker_offline",
			"critical",
			"Worker heartbeat missing",
			active,
			contextData,
		)
	}
}

func (s *AlertService) checkPoolCapacity(ctx context.Context, settings RuntimeSettings) {
	pools, err := s.store.GetPools(ctx)
	if err != nil {
		log.Printf("alert: failed to load pools: %v", err)
		return
	}

	for _, pool := range pools {
		active := pool.Desired > 0 && pool.Online < pool.Desired

		s.syncIncident(
			ctx,
			settings,
			incidentStateKey("pool_under_capacity", pool.Pool),
			"pool_under_capacity",
			"warning",
			"Pool online workers below desired capacity",
			active,
			map[string]interface{}{
				"pool":    pool.Pool,
				"online":  pool.Online,
				"desired": pool.Desired,
			},
		)
	}
}

func (s *AlertService) checkWorkerErrorRate(ctx context.Context, settings RuntimeSettings) {
	threshold := settings.AlertErrorRateThreshold
	if threshold <= 0 {
		return
	}

	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("alert: failed to load workers: %v", err)
		return
	}

	quarantineThreshold := settings.QuarantineErrorRateThreshold
	if quarantineThreshold <= 0 {
		quarantineThreshold = threshold * 1.5
	}

	for _, worker := range workers {
		metrics, metricsErr := s.store.GetWorkerMetrics(ctx, worker.WorkerID)
		if metricsErr != nil || metrics == nil {
			continue
		}

		errorRate := metrics.ErrorsPerMin
		active := errorRate >= threshold

		s.syncIncident(
			ctx,
			settings,
			incidentStateKey("worker_error_rate", worker.WorkerID),
			"worker_error_rate",
			"warning",
			"Worker error rate above threshold",
			active,
			map[string]interface{}{
				"worker_id":      worker.WorkerID,
				"errors_per_min": errorRate,
				"threshold":      threshold,
				"pool":           worker.Pool,
			},
		)

		if !active {
			continue
		}

		if settings.AutoActionsEnabled {
			_ = s.store.SetDesiredState(ctx, worker.WorkerID, "draining")
		}

		if settings.AutoActionsEnabled && quarantineThreshold > 0 && errorRate >= quarantineThreshold {
			_ = s.store.SetWorkerQuarantined(ctx, worker.WorkerID, true, "error_rate_threshold")
		}
	}
}

func (s *AlertService) checkStuckDesiredState(ctx context.Context, settings RuntimeSettings) {
	grace := s.cfg.StuckDesiredGrace
	if grace <= 0 {
		grace = 10 * time.Minute
	}

	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("alert: failed to load workers for desired-state drift: %v", err)
		return
	}

	now := time.Now().UTC()
	for _, worker := range workers {
		desired := normalizeDesiredState(worker.DesiredState)
		if desired == "" {
			continue
		}

		status := normalizeDesiredState(worker.Status)
		stateUpdated, ok, updatedErr := s.store.GetDesiredStateUpdatedAt(ctx, worker.WorkerID)
		if updatedErr != nil {
			continue
		}

		active := false
		contextData := map[string]interface{}{
			"worker_id":      worker.WorkerID,
			"current_status": status,
			"desired_status": desired,
			"last_heartbeat": worker.LastHeartbeat,
		}

		if ok {
			contextData["desired_state_updated"] = stateUpdated.Format(time.RFC3339)
		}

		if status != desired && ok {
			active = now.Sub(stateUpdated) > grace
		}

		s.syncIncident(
			ctx,
			settings,
			incidentStateKey("worker_stuck_desired", worker.WorkerID),
			"worker_stuck_desired",
			"warning",
			"Worker is not converging to desired state",
			active,
			contextData,
		)
	}
}

func (s *AlertService) checkProviderHealth(ctx context.Context, settings RuntimeSettings) {
	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("alert: failed to load workers for provider health: %v", err)
		return
	}

	modes, modesErr := s.store.GetProviderModes(ctx)
	if modesErr != nil {
		modes = map[string]ProviderModeState{}
	}

	health := aggregateProviderHealth(workers, modes, thresholdsFromConfig(s.cfg))
	for _, provider := range health {
		active := provider.Status != "healthy"
		contextData := map[string]interface{}{
			"provider":        provider.Provider,
			"status":          provider.Status,
			"mode":            provider.Mode,
			"tempfail_rate":   provider.TempfailRate,
			"reject_rate":     provider.RejectRate,
			"unknown_rate":    provider.UnknownRate,
			"avg_retry_after": provider.AvgRetryAfter,
			"workers":         provider.Workers,
		}

		s.syncIncident(
			ctx,
			settings,
			incidentStateKey("provider_health", provider.Provider),
			"provider_health",
			map[string]string{"critical": "critical", "warning": "warning", "healthy": "info"}[provider.Status],
			"Provider health degraded",
			active,
			contextData,
		)

		if !s.cfg.ProviderAutoprotectEnabled || !settings.AutoActionsEnabled {
			continue
		}

		override, hasOverride := modes[provider.Provider]
		if hasOverride && strings.ToLower(strings.TrimSpace(override.Source)) == "manual" {
			continue
		}

		targetMode := "normal"
		if provider.Status == "warning" {
			targetMode = "cautious"
		}
		if provider.Status == "critical" {
			targetMode = "drain"
		}
		if provider.Mode == targetMode {
			continue
		}

		if _, setErr := s.store.SetProviderMode(ctx, provider.Provider, targetMode, "autoprotect"); setErr != nil {
			log.Printf("alert: failed to apply provider auto-protect mode provider=%s mode=%s err=%v", provider.Provider, targetMode, setErr)
		}
	}
}

func incidentStateKey(incidentType, subject string) string {
	return incidentType + ":" + subject
}

func (s *AlertService) syncIncident(
	ctx context.Context,
	settings RuntimeSettings,
	incidentKey string,
	incidentType string,
	severity string,
	message string,
	active bool,
	contextData map[string]interface{},
) {
	if active {
		_, opened, err := s.store.ActivateIncident(ctx, incidentKey, incidentType, severity, message, contextData)
		if err != nil {
			log.Printf("alert: activate incident failed type=%s key=%s err=%v", incidentType, incidentKey, err)
			return
		}

		if settings.AlertsEnabled && opened {
			s.dispatchAlert(ctx, AlertEvent{
				Type:      incidentType,
				Severity:  severity,
				Message:   message,
				Context:   contextData,
				CreatedAt: time.Now().UTC(),
			})
		}

		return
	}

	record, resolved, err := s.store.ResolveIncident(ctx, incidentKey, message, contextData)
	if err != nil {
		log.Printf("alert: resolve incident failed type=%s key=%s err=%v", incidentType, incidentKey, err)
		return
	}
	if !resolved || !settings.AlertsEnabled {
		return
	}

	recoveryContext := map[string]interface{}{}
	for key, value := range record.Context {
		recoveryContext[key] = value
	}
	for key, value := range contextData {
		recoveryContext[key] = value
	}
	recoveryContext["incident_key"] = incidentKey
	recoveryContext["resolved_at"] = time.Now().UTC().Format(time.RFC3339)

	s.dispatchAlert(ctx, AlertEvent{
		Type:      incidentType + "_recovered",
		Severity:  "info",
		Message:   "Incident recovered",
		Context:   recoveryContext,
		CreatedAt: time.Now().UTC(),
	})
}

func (s *AlertService) loadRuntimeSettings(ctx context.Context) RuntimeSettings {
	defaults := defaultRuntimeSettings(s.cfg)
	settings, err := s.store.GetRuntimeSettings(ctx, defaults)
	if err != nil {
		log.Printf("alert: failed to load runtime settings, using defaults: %v", err)
		return defaults
	}
	return settings
}

func (s *AlertService) dispatchAlert(ctx context.Context, alert AlertEvent) {
	if s.snapshots != nil {
		if err := s.snapshots.SaveAlert(ctx, alert); err != nil {
			log.Printf("alert: save failed: %v", err)
		}
	}

	if s.notifier != nil {
		if err := s.notifier.Notify(ctx, alert); err != nil {
			log.Printf("alert: notify failed: %v", err)
		}
	}
}

type Notifier interface {
	Notify(ctx context.Context, alert AlertEvent) error
}

type MultiNotifier struct {
	notifiers []Notifier
}

func NewMultiNotifier(notifiers ...Notifier) *MultiNotifier {
	filtered := make([]Notifier, 0, len(notifiers))
	for _, notifier := range notifiers {
		if notifierIsNil(notifier) {
			continue
		}
		filtered = append(filtered, notifier)
	}
	return &MultiNotifier{notifiers: filtered}
}

func (m *MultiNotifier) Notify(ctx context.Context, alert AlertEvent) error {
	for _, notifier := range m.notifiers {
		if notifierIsNil(notifier) {
			continue
		}
		if err := notifier.Notify(ctx, alert); err != nil {
			return err
		}
	}
	return nil
}

func notifierIsNil(notifier Notifier) bool {
	if notifier == nil {
		return true
	}

	switch typed := notifier.(type) {
	case *SlackNotifier:
		return typed == nil
	case *EmailNotifier:
		return typed == nil
	case *MultiNotifier:
		return typed == nil
	default:
		return false
	}
}

func alertSummary(alert AlertEvent) string {
	payload := map[string]interface{}{
		"type":     alert.Type,
		"severity": alert.Severity,
		"message":  alert.Message,
		"context":  alert.Context,
	}
	data, _ := json.Marshal(payload)
	return string(data)
}

func alertTitle(alert AlertEvent) string {
	summary := strings.ToUpper(alert.Severity)
	return summary + " · " + alert.Type
}
