package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"strings"
	"time"

	"github.com/redis/go-redis/v9"
)

const policyCanaryAutopilotStateKey = "control_plane:policy_canary:autopilot_state"

type PolicyCanaryAutopilotService struct {
	store       *Store
	cfg         Config
	instanceID  string
	stopChannel chan struct{}
	ticker      *time.Ticker
	lastRunAt   time.Time
}

type policyCanaryAutopilotState struct {
	Version                 string                     `json:"version"`
	HealthyWindows          int                        `json:"healthy_windows"`
	BaselineUnknownRate     float64                    `json:"baseline_unknown_rate"`
	BaselineTempfailRecover float64                    `json:"baseline_tempfail_recover"`
	BaselinePolicyBlockRate float64                    `json:"baseline_policy_block_rate"`
	ProviderBaselines       map[string]policyCanaryKPI `json:"provider_baselines,omitempty"`
	LastEvaluatedAt         string                     `json:"last_evaluated_at,omitempty"`
}

type policyCanaryKPI struct {
	UnknownRate      float64
	TempfailRecovery float64
	PolicyBlockRate  float64
	Workers          int
}

type policyCanarySnapshot struct {
	Aggregate policyCanaryKPI
	Providers map[string]policyCanaryKPI
}

func NewPolicyCanaryAutopilotService(store *Store, cfg Config, instanceID string) *PolicyCanaryAutopilotService {
	return &PolicyCanaryAutopilotService{
		store:       store,
		cfg:         cfg,
		instanceID:  instanceID,
		stopChannel: make(chan struct{}),
		ticker:      time.NewTicker(30 * time.Second),
	}
}

func (s *PolicyCanaryAutopilotService) Start() {
	go func() {
		defer s.ticker.Stop()

		for {
			select {
			case <-s.ticker.C:
				s.run()
			case <-s.stopChannel:
				return
			}
		}
	}()
}

func (s *PolicyCanaryAutopilotService) Stop() {
	close(s.stopChannel)
}

func (s *PolicyCanaryAutopilotService) run() {
	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	defaults := defaultRuntimeSettings(s.cfg)
	settings, err := s.store.GetRuntimeSettings(ctx, defaults)
	if err != nil {
		settings = defaults
	}

	if !settings.PolicyCanaryAutopilotEnabled {
		return
	}

	if !s.shouldRun(ctx) {
		return
	}
	if !s.runDue(settings) {
		return
	}

	modes, err := s.store.GetProviderModes(ctx)
	if err != nil {
		log.Printf("policy-autopilot: failed to read provider modes: %v", err)
		return
	}
	if hasManualProviderOverride(modes) {
		return
	}

	versions, activeVersion, err := s.store.ListSMTPPolicyVersions(ctx)
	if err != nil {
		log.Printf("policy-autopilot: failed to list policy versions: %v", err)
		return
	}
	if activeVersion == "" {
		return
	}

	var activeRecord SMTPPolicyVersionRecord
	foundActive := false
	for _, record := range versions {
		if normalizePolicyVersion(record.Version) == activeVersion {
			activeRecord = record
			foundActive = true
			break
		}
	}
	if !foundActive {
		return
	}
	if activeRecord.CanaryPercent >= 100 {
		_ = s.clearState(ctx)
		return
	}

	snapshot, err := s.collectKPI(ctx, modes, settings)
	if err != nil {
		log.Printf("policy-autopilot: failed to collect kpi: %v", err)
		return
	}

	state, err := s.loadState(ctx)
	if err != nil {
		log.Printf("policy-autopilot: failed to load state: %v", err)
		return
	}
	if normalizePolicyVersion(state.Version) != activeVersion {
		state = policyCanaryAutopilotState{
			Version:                 activeVersion,
			HealthyWindows:          0,
			BaselineUnknownRate:     snapshot.Aggregate.UnknownRate,
			BaselineTempfailRecover: snapshot.Aggregate.TempfailRecovery,
			BaselinePolicyBlockRate: snapshot.Aggregate.PolicyBlockRate,
			ProviderBaselines:       snapshot.Providers,
		}
	}

	rollbackReason := evaluatePolicyCanaryRollback(
		state,
		snapshot.Aggregate,
		settings.PolicyCanaryUnknownRegressionThreshold,
		settings.PolicyCanaryTempfailRecoveryDropThreshold,
		settings.PolicyCanaryPolicyBlockSpikeThreshold,
	)
	provider, providerRollbackReason := evaluateProviderPolicyCanaryRollback(
		state.ProviderBaselines,
		snapshot.Providers,
		settings.PolicyCanaryUnknownRegressionThreshold,
		settings.PolicyCanaryTempfailRecoveryDropThreshold,
		settings.PolicyCanaryPolicyBlockSpikeThreshold,
		settings.PolicyCanaryMinProviderWorkers,
	)
	if providerRollbackReason != "" {
		currentMode := "normal"
		if override, ok := modes[provider]; ok {
			currentMode = normalizeProviderMode(override.Mode)
		}
		nextMode, shouldRollback := nextProviderMitigationMode(currentMode)
		if !shouldRollback {
			if _, modeErr := s.store.SetProviderMode(ctx, provider, nextMode, "autopilot"); modeErr != nil {
				log.Printf("policy-autopilot: failed to set %s mode for provider %s: %v", nextMode, provider, modeErr)
				return
			}
			state.HealthyWindows = 0
			state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)
			state.ProviderBaselines = snapshot.Providers
			_ = s.saveState(ctx, state)
			return
		}

		rollbackReason = providerRollbackReason
	}

	if rollbackReason != "" {
		if _, rollbackErr := s.store.RollbackSMTPPolicyVersion(ctx, "autopilot", rollbackReason); rollbackErr != nil {
			log.Printf("policy-autopilot: rollback failed: %v", rollbackErr)
			return
		}
		_ = s.clearState(ctx)
		return
	}

	if !hasMinimumProviderTelemetry(snapshot.Providers, settings.PolicyCanaryMinProviderWorkers) {
		state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)
		_ = s.saveState(ctx, state)
		return
	}

	state.HealthyWindows++
	state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)

	required := settings.PolicyCanaryRequiredHealthWindows
	if required < 1 {
		required = 1
	}
	if state.HealthyWindows < required {
		_ = s.saveState(ctx, state)
		return
	}

	nextCanary, ok := nextPolicyCanaryStep(activeRecord.CanaryPercent)
	if !ok {
		_ = s.clearState(ctx)
		return
	}

	if _, promoteErr := s.store.PromoteSMTPPolicyVersion(
		ctx,
		activeVersion,
		nextCanary,
		"autopilot",
		fmt.Sprintf("auto canary progression %d->%d after %d healthy windows", activeRecord.CanaryPercent, nextCanary, state.HealthyWindows),
	); promoteErr != nil {
		log.Printf("policy-autopilot: progression failed: %v", promoteErr)
		return
	}

	state.Version = activeVersion
	state.HealthyWindows = 0
	state.BaselineUnknownRate = snapshot.Aggregate.UnknownRate
	state.BaselineTempfailRecover = snapshot.Aggregate.TempfailRecovery
	state.BaselinePolicyBlockRate = snapshot.Aggregate.PolicyBlockRate
	state.ProviderBaselines = snapshot.Providers
	state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)
	_ = s.saveState(ctx, state)
}

func (s *PolicyCanaryAutopilotService) runDue(settings RuntimeSettings) bool {
	windowMinutes := settings.PolicyCanaryWindowMinutes
	if windowMinutes <= 0 {
		windowMinutes = 15
	}

	interval := time.Duration(windowMinutes) * time.Minute
	now := time.Now().UTC()
	if !s.lastRunAt.IsZero() && now.Sub(s.lastRunAt) < interval {
		return false
	}

	s.lastRunAt = now
	return true
}

func (s *PolicyCanaryAutopilotService) shouldRun(ctx context.Context) bool {
	if !s.cfg.LeaderLockEnabled {
		return true
	}

	ok, err := s.store.HoldLeaderLease(ctx, "policy-canary-autopilot", s.instanceID, s.cfg.LeaderLockTTL)
	if err != nil {
		log.Printf("policy-autopilot: leader lock error: %v", err)
		return false
	}

	return ok
}

func (s *PolicyCanaryAutopilotService) collectKPI(
	ctx context.Context,
	modes map[string]ProviderModeState,
	settings RuntimeSettings,
) (policyCanarySnapshot, error) {
	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		return policyCanarySnapshot{}, err
	}

	health := aggregateProviderHealth(workers, modes, thresholdsFromRuntimeSettings(settings))
	if len(health) == 0 {
		return policyCanarySnapshot{
			Aggregate: policyCanaryKPI{},
			Providers: map[string]policyCanaryKPI{},
		}, nil
	}

	unknownWeighted := 0.0
	policyBlockedWeighted := 0.0
	weightTotal := 0.0
	providerKPI := make(map[string]policyCanaryKPI, len(health))
	for _, provider := range health {
		weight := float64(provider.Workers)
		if weight < 1 {
			weight = 1
		}
		unknownWeighted += provider.UnknownRate * weight
		policyBlockedWeighted += provider.PolicyBlockedRate * weight
		weightTotal += weight
		providerKPI[provider.Provider] = policyCanaryKPI{
			UnknownRate:      provider.UnknownRate,
			TempfailRecovery: maxFloat(0, 1-provider.UnknownRate),
			PolicyBlockRate:  provider.PolicyBlockedRate,
			Workers:          provider.Workers,
		}
	}
	if weightTotal <= 0 {
		return policyCanarySnapshot{
			Aggregate: policyCanaryKPI{},
			Providers: providerKPI,
		}, nil
	}

	unknownRate := unknownWeighted / weightTotal
	policyBlockedRate := policyBlockedWeighted / weightTotal
	tempfailRecovery := 1 - unknownRate
	if tempfailRecovery < 0 {
		tempfailRecovery = 0
	}

	return policyCanarySnapshot{
		Aggregate: policyCanaryKPI{
			UnknownRate:      unknownRate,
			TempfailRecovery: tempfailRecovery,
			PolicyBlockRate:  policyBlockedRate,
			Workers:          len(workers),
		},
		Providers: providerKPI,
	}, nil
}

func (s *PolicyCanaryAutopilotService) loadState(ctx context.Context) (policyCanaryAutopilotState, error) {
	value, err := s.store.rdb.Get(ctx, policyCanaryAutopilotStateKey).Result()
	if err == redis.Nil || strings.TrimSpace(value) == "" {
		return policyCanaryAutopilotState{}, nil
	}
	if err != nil {
		return policyCanaryAutopilotState{}, err
	}

	state := policyCanaryAutopilotState{}
	if err := json.Unmarshal([]byte(value), &state); err != nil {
		return policyCanaryAutopilotState{}, err
	}

	return state, nil
}

func (s *PolicyCanaryAutopilotService) saveState(ctx context.Context, state policyCanaryAutopilotState) error {
	payload, err := json.Marshal(state)
	if err != nil {
		return err
	}
	return s.store.rdb.Set(ctx, policyCanaryAutopilotStateKey, payload, 0).Err()
}

func (s *PolicyCanaryAutopilotService) clearState(ctx context.Context) error {
	return s.store.rdb.Del(ctx, policyCanaryAutopilotStateKey).Err()
}

func hasManualProviderOverride(modes map[string]ProviderModeState) bool {
	for _, mode := range modes {
		if normalizeProviderMode(mode.Mode) == "" || normalizeProviderMode(mode.Mode) == "normal" {
			continue
		}
		if strings.EqualFold(strings.TrimSpace(mode.Source), "manual") {
			return true
		}
	}

	return false
}

func nextPolicyCanaryStep(current int) (int, bool) {
	switch {
	case current < 5:
		return 5, true
	case current < 25:
		return 25, true
	case current < 50:
		return 50, true
	case current < 100:
		return 100, true
	default:
		return 100, false
	}
}

func evaluatePolicyCanaryRollback(
	state policyCanaryAutopilotState,
	current policyCanaryKPI,
	unknownRegressionThreshold float64,
	tempfailRecoveryDropThreshold float64,
	policyBlockSpikeThreshold float64,
) string {
	if (current.UnknownRate - state.BaselineUnknownRate) > unknownRegressionThreshold {
		return fmt.Sprintf(
			"auto rollback: unknown-rate regression %.4f exceeded threshold %.4f",
			current.UnknownRate-state.BaselineUnknownRate,
			unknownRegressionThreshold,
		)
	}

	if (state.BaselineTempfailRecover - current.TempfailRecovery) > tempfailRecoveryDropThreshold {
		return fmt.Sprintf(
			"auto rollback: tempfail-recovery drop %.4f exceeded threshold %.4f",
			state.BaselineTempfailRecover-current.TempfailRecovery,
			tempfailRecoveryDropThreshold,
		)
	}

	if (current.PolicyBlockRate - state.BaselinePolicyBlockRate) > policyBlockSpikeThreshold {
		return fmt.Sprintf(
			"auto rollback: policy-block spike %.4f exceeded threshold %.4f",
			current.PolicyBlockRate-state.BaselinePolicyBlockRate,
			policyBlockSpikeThreshold,
		)
	}

	return ""
}

func evaluateProviderPolicyCanaryRollback(
	baseline map[string]policyCanaryKPI,
	current map[string]policyCanaryKPI,
	unknownRegressionThreshold float64,
	tempfailRecoveryDropThreshold float64,
	policyBlockSpikeThreshold float64,
	minProviderWorkers int,
) (string, string) {
	if minProviderWorkers < 1 {
		minProviderWorkers = 1
	}

	for provider, currentKPI := range current {
		if currentKPI.Workers < minProviderWorkers {
			continue
		}

		baseKPI, ok := baseline[provider]
		if !ok {
			continue
		}

		reason := evaluatePolicyCanaryRollback(
			policyCanaryAutopilotState{
				BaselineUnknownRate:     baseKPI.UnknownRate,
				BaselineTempfailRecover: baseKPI.TempfailRecovery,
				BaselinePolicyBlockRate: baseKPI.PolicyBlockRate,
			},
			currentKPI,
			unknownRegressionThreshold,
			tempfailRecoveryDropThreshold,
			policyBlockSpikeThreshold,
		)
		if reason == "" {
			continue
		}

		return provider, fmt.Sprintf("provider %s regression: %s", provider, reason)
	}

	return "", ""
}

func maxFloat(a float64, b float64) float64 {
	if a > b {
		return a
	}

	return b
}

func nextProviderMitigationMode(currentMode string) (string, bool) {
	switch normalizeProviderMode(currentMode) {
	case "normal", "":
		return "cautious", false
	case "cautious":
		return "degraded_probe", false
	case "degraded_probe":
		return "drain", false
	case "drain":
		return "", true
	default:
		return "cautious", false
	}
}

func hasMinimumProviderTelemetry(providers map[string]policyCanaryKPI, minWorkers int) bool {
	if minWorkers < 1 {
		minWorkers = 1
	}

	for _, kpi := range providers {
		if kpi.Workers >= minWorkers {
			return true
		}
	}

	return false
}
