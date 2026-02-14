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
}

type policyCanaryAutopilotState struct {
	Version                 string  `json:"version"`
	HealthyWindows          int     `json:"healthy_windows"`
	BaselineUnknownRate     float64 `json:"baseline_unknown_rate"`
	BaselineTempfailRecover float64 `json:"baseline_tempfail_recover"`
	BaselinePolicyBlockRate float64 `json:"baseline_policy_block_rate"`
	LastEvaluatedAt         string  `json:"last_evaluated_at,omitempty"`
}

type policyCanaryKPI struct {
	UnknownRate      float64
	TempfailRecovery float64
	PolicyBlockRate  float64
}

func NewPolicyCanaryAutopilotService(store *Store, cfg Config, instanceID string) *PolicyCanaryAutopilotService {
	interval := time.Duration(cfg.PolicyCanaryWindowMinutes) * time.Minute
	if interval <= 0 {
		interval = 15 * time.Minute
	}

	return &PolicyCanaryAutopilotService{
		store:       store,
		cfg:         cfg,
		instanceID:  instanceID,
		stopChannel: make(chan struct{}),
		ticker:      time.NewTicker(interval),
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
	if !s.cfg.PolicyCanaryAutopilotEnabled {
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	if !s.shouldRun(ctx) {
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

	kpi, err := s.collectKPI(ctx, modes)
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
			BaselineUnknownRate:     kpi.UnknownRate,
			BaselineTempfailRecover: kpi.TempfailRecovery,
			BaselinePolicyBlockRate: kpi.PolicyBlockRate,
		}
	}

	rollbackReason := evaluatePolicyCanaryRollback(
		state,
		kpi,
		s.cfg.PolicyCanaryUnknownRegressionThreshold,
		s.cfg.PolicyCanaryTempfailRecoveryDropThreshold,
		s.cfg.PolicyCanaryPolicyBlockSpikeThreshold,
	)
	if rollbackReason != "" {
		if _, rollbackErr := s.store.RollbackSMTPPolicyVersion(ctx, "autopilot", rollbackReason); rollbackErr != nil {
			log.Printf("policy-autopilot: rollback failed: %v", rollbackErr)
			return
		}
		_ = s.clearState(ctx)
		return
	}

	state.HealthyWindows++
	state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)

	required := s.cfg.PolicyCanaryRequiredHealthWindows
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
	state.BaselineUnknownRate = kpi.UnknownRate
	state.BaselineTempfailRecover = kpi.TempfailRecovery
	state.BaselinePolicyBlockRate = kpi.PolicyBlockRate
	state.LastEvaluatedAt = time.Now().UTC().Format(time.RFC3339)
	_ = s.saveState(ctx, state)
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

func (s *PolicyCanaryAutopilotService) collectKPI(ctx context.Context, modes map[string]ProviderModeState) (policyCanaryKPI, error) {
	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		return policyCanaryKPI{}, err
	}

	health := aggregateProviderHealth(workers, modes, thresholdsFromConfig(s.cfg))
	if len(health) == 0 {
		return policyCanaryKPI{}, nil
	}

	unknownWeighted := 0.0
	policyBlockedWeighted := 0.0
	weightTotal := 0.0
	for _, provider := range health {
		weight := float64(provider.Workers)
		if weight < 1 {
			weight = 1
		}
		unknownWeighted += provider.UnknownRate * weight
		policyBlockedWeighted += provider.PolicyBlockedRate * weight
		weightTotal += weight
	}
	if weightTotal <= 0 {
		return policyCanaryKPI{}, nil
	}

	unknownRate := unknownWeighted / weightTotal
	policyBlockedRate := policyBlockedWeighted / weightTotal
	tempfailRecovery := 1 - unknownRate
	if tempfailRecovery < 0 {
		tempfailRecovery = 0
	}

	return policyCanaryKPI{
		UnknownRate:      unknownRate,
		TempfailRecovery: tempfailRecovery,
		PolicyBlockRate:  policyBlockedRate,
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
