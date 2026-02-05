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
	store       *Store
	snapshots   *SnapshotStore
	cfg         Config
	notifier    Notifier
	stopChannel chan struct{}
	checkTicker *time.Ticker
}

func NewAlertService(store *Store, snapshots *SnapshotStore, cfg Config, notifier Notifier) *AlertService {
	return &AlertService{
		store:       store,
		snapshots:   snapshots,
		cfg:         cfg,
		notifier:    notifier,
		stopChannel: make(chan struct{}),
	}
}

func (s *AlertService) Start() {
	if !s.cfg.AlertsEnabled && !s.cfg.AutoActionsEnabled {
		return
	}

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

	s.checkOfflineWorkers(ctx)
	s.checkPoolCapacity(ctx)
	s.checkWorkerErrorRate(ctx)
}

func (s *AlertService) checkOfflineWorkers(ctx context.Context) {
	workerIDs, err := s.store.GetKnownWorkerIDs(ctx)
	if err != nil {
		log.Printf("alert: failed to load known workers: %v", err)
		return
	}

	grace := s.cfg.AlertHeartbeatGrace
	if grace <= 0 {
		grace = 120 * time.Second
	}

	now := time.Now().UTC()
	for _, workerID := range workerIDs {
		lastSeen, ok, err := s.store.GetWorkerLastSeen(ctx, workerID)
		if err != nil {
			log.Printf("alert: failed to get last seen for %s: %v", workerID, err)
			continue
		}
		if !ok {
			continue
		}
		if now.Sub(lastSeen) <= grace {
			continue
		}

		alertKey := "alert:offline:" + workerID
		if !s.shouldSend(ctx, alertKey) {
			continue
		}

		alert := AlertEvent{
			Type:      "worker_offline",
			Severity:  "critical",
			Message:   "Worker heartbeat missing",
			Context:   map[string]interface{}{"worker_id": workerID, "last_seen": lastSeen.Format(time.RFC3339)},
			CreatedAt: now,
		}
		s.dispatchAlert(ctx, alert)
	}
}

func (s *AlertService) checkPoolCapacity(ctx context.Context) {
	pools, err := s.store.GetPools(ctx)
	if err != nil {
		log.Printf("alert: failed to load pools: %v", err)
		return
	}

	for _, pool := range pools {
		if pool.Desired <= 0 || pool.Online >= pool.Desired {
			continue
		}

		alertKey := "alert:pool_under:" + pool.Pool
		if !s.shouldSend(ctx, alertKey) {
			continue
		}

		alert := AlertEvent{
			Type:      "pool_under_capacity",
			Severity:  "warning",
			Message:   "Pool online workers below desired capacity",
			Context:   map[string]interface{}{"pool": pool.Pool, "online": pool.Online, "desired": pool.Desired},
			CreatedAt: time.Now().UTC(),
		}
		s.dispatchAlert(ctx, alert)
	}
}

func (s *AlertService) checkWorkerErrorRate(ctx context.Context) {
	threshold := s.cfg.AlertErrorRateThreshold
	if threshold <= 0 {
		return
	}

	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("alert: failed to load workers: %v", err)
		return
	}

	for _, worker := range workers {
		metrics, err := s.store.GetWorkerMetrics(ctx, worker.WorkerID)
		if err != nil || metrics == nil {
			continue
		}
		if metrics.ErrorsPerMin < threshold {
			continue
		}

		alertKey := "alert:error_rate:" + worker.WorkerID
		if !s.shouldSend(ctx, alertKey) {
			continue
		}

		alert := AlertEvent{
			Type:     "worker_error_rate",
			Severity: "warning",
			Message:  "Worker error rate above threshold",
			Context: map[string]interface{}{
				"worker_id":      worker.WorkerID,
				"errors_per_min": metrics.ErrorsPerMin,
				"threshold":      threshold,
			},
			CreatedAt: time.Now().UTC(),
		}
		s.dispatchAlert(ctx, alert)

		if s.cfg.AutoActionsEnabled {
			_ = s.store.SetDesiredState(ctx, worker.WorkerID, "draining")
		}
	}
}

func (s *AlertService) shouldSend(ctx context.Context, key string) bool {
	cooldown := s.cfg.AlertCooldown
	if cooldown <= 0 {
		cooldown = 300 * time.Second
	}

	allowed, err := s.store.ShouldSendAlert(ctx, key, cooldown)
	if err != nil {
		log.Printf("alert: suppression check failed: %v", err)
		return false
	}

	return allowed
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
		if notifier != nil {
			filtered = append(filtered, notifier)
		}
	}
	return &MultiNotifier{notifiers: filtered}
}

func (m *MultiNotifier) Notify(ctx context.Context, alert AlertEvent) error {
	for _, notifier := range m.notifiers {
		if err := notifier.Notify(ctx, alert); err != nil {
			return err
		}
	}
	return nil
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
