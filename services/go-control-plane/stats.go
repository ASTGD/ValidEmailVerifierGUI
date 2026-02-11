package main

import (
	"context"
	"sort"
)

type ControlPlaneStats struct {
	Workers                 []WorkerSummary
	Pools                   []PoolSummary
	Incidents               []IncidentRecord
	WorkerCount             int
	PoolCount               int
	DesiredTotal            int
	ErrorRateTotal          float64
	ErrorRateAverage        float64
	WorkerErrorRates        map[string]float64
	IncidentCount           int
	ProbeUnknownRate        float64
	ProbeTempfailRate       float64
	ProbeRejectRate         float64
	ScreeningProcessedTotal int64
	ProbeProcessedTotal     int64
	LaravelFallbackWorkers  int
	Settings                RuntimeSettings
	ProviderHealth          []ProviderHealthSummary
	ProviderPolicies        ProviderPoliciesData
	RoutingQuality          RoutingQualitySummary
}

func (s *Server) collectControlPlaneStats(ctx context.Context) (ControlPlaneStats, error) {
	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		return ControlPlaneStats{}, err
	}

	pools, err := s.store.GetPools(ctx)
	if err != nil {
		return ControlPlaneStats{}, err
	}

	sort.Slice(workers, func(i, j int) bool {
		return workers[i].WorkerID < workers[j].WorkerID
	})

	sort.Slice(pools, func(i, j int) bool {
		return pools[i].Pool < pools[j].Pool
	})

	desiredTotal := 0
	for _, pool := range pools {
		desiredTotal += pool.Desired
	}

	workerErrorRates := make(map[string]float64, len(workers))
	errorTotal := 0.0
	for _, worker := range workers {
		metrics, metricsErr := s.store.GetWorkerMetrics(ctx, worker.WorkerID)
		if metricsErr != nil || metrics == nil {
			workerErrorRates[worker.WorkerID] = 0
			continue
		}

		workerErrorRates[worker.WorkerID] = metrics.ErrorsPerMin
		errorTotal += metrics.ErrorsPerMin
	}

	errorAvg := 0.0
	if len(workers) > 0 {
		errorAvg = errorTotal / float64(len(workers))
	}

	var unknownTotal float64
	var tempfailTotal float64
	var rejectTotal float64
	var smtpMetricsWorkers int
	var screeningProcessed int64
	var probeProcessed int64
	laravelFallbackWorkers := 0
	routingQuality := RoutingQualitySummary{}
	for _, worker := range workers {
		if hasWorkerTag(worker.Tags, "laravel_heartbeat:true") {
			laravelFallbackWorkers++
		}
		if worker.SMTPMetrics != nil {
			unknownTotal += worker.SMTPMetrics.UnknownRate
			tempfailTotal += worker.SMTPMetrics.TempfailRate
			rejectTotal += worker.SMTPMetrics.RejectRate
			smtpMetricsWorkers++
		}
		if worker.StageMetrics != nil {
			if worker.StageMetrics.Screening != nil {
				screeningProcessed += worker.StageMetrics.Screening.Processed
			}
			if worker.StageMetrics.SMTPProbe != nil {
				probeProcessed += worker.StageMetrics.SMTPProbe.Processed
			}
		}
		if worker.RoutingMetrics != nil {
			routingQuality.RetryClaimsTotal += worker.RoutingMetrics.RetryClaimsTotal
			routingQuality.RetryAntiAffinitySuccessTotal += worker.RoutingMetrics.RetryAntiAffinitySuccessTotal
			routingQuality.SameWorkerAvoidTotal += worker.RoutingMetrics.SameWorkerAvoidTotal
			routingQuality.SamePoolAvoidTotal += worker.RoutingMetrics.SamePoolAvoidTotal
			routingQuality.ProviderAffinityHitTotal += worker.RoutingMetrics.ProviderAffinityHitTotal
			routingQuality.FallbackClaimTotal += worker.RoutingMetrics.FallbackClaimTotal
		}
	}

	unknownAvg := 0.0
	tempfailAvg := 0.0
	rejectAvg := 0.0
	if smtpMetricsWorkers > 0 {
		denominator := float64(smtpMetricsWorkers)
		unknownAvg = unknownTotal / denominator
		tempfailAvg = tempfailTotal / denominator
		rejectAvg = rejectTotal / denominator
	}

	if routingQuality.RetryClaimsTotal > 0 {
		routingQuality.AntiAffinitySuccessRate = float64(routingQuality.RetryAntiAffinitySuccessTotal) / float64(routingQuality.RetryClaimsTotal)
	}

	routingClaimsTotal := routingQuality.ProviderAffinityHitTotal + routingQuality.FallbackClaimTotal
	if routingClaimsTotal > 0 {
		routingQuality.ProviderAffinityHitRate = float64(routingQuality.ProviderAffinityHitTotal) / float64(routingClaimsTotal)
		routingQuality.RetryFallbackRate = float64(routingQuality.FallbackClaimTotal) / float64(routingClaimsTotal)
	}

	if desiredTotal > 0 {
		topPoolDesired := 0
		for _, pool := range pools {
			if pool.Desired > topPoolDesired {
				topPoolDesired = pool.Desired
			}
		}
		routingQuality.TopPoolShare = float64(topPoolDesired) / float64(desiredTotal)
	}

	defaults := defaultRuntimeSettings(s.cfg)
	settings, settingsErr := s.store.GetRuntimeSettings(ctx, defaults)
	if settingsErr != nil {
		settings = defaults
	}

	providerModesMap, providerModesErr := s.store.GetProviderModes(ctx)
	if providerModesErr != nil {
		providerModesMap = map[string]ProviderModeState{}
	}

	providerPolicyState, providerPolicyErr := s.store.GetProviderPolicyState(ctx)
	if providerPolicyErr != nil {
		providerPolicyState = ProviderPolicyState{}
	}

	_, activePolicyVersion, policyVersionsErr := s.store.ListSMTPPolicyVersions(ctx)
	if policyVersionsErr != nil {
		activePolicyVersion = ""
	}

	providerHealth := aggregateProviderHealth(workers, providerModesMap, thresholdsFromConfig(s.cfg))
	providerModes := make([]ProviderModeState, 0, len(providerModesMap))
	for _, mode := range providerModesMap {
		providerModes = append(providerModes, mode)
	}
	sort.Slice(providerModes, func(i, j int) bool {
		return providerModes[i].Provider < providerModes[j].Provider
	})

	incidents, incidentsErr := s.store.ListIncidents(ctx, 200, false)
	if incidentsErr != nil {
		incidents = nil
	}

	return ControlPlaneStats{
		Workers:                 workers,
		Pools:                   pools,
		Incidents:               incidents,
		WorkerCount:             len(workers),
		PoolCount:               len(pools),
		DesiredTotal:            desiredTotal,
		ErrorRateTotal:          errorTotal,
		ErrorRateAverage:        errorAvg,
		WorkerErrorRates:        workerErrorRates,
		IncidentCount:           len(incidents),
		ProbeUnknownRate:        unknownAvg,
		ProbeTempfailRate:       tempfailAvg,
		ProbeRejectRate:         rejectAvg,
		ScreeningProcessedTotal: screeningProcessed,
		ProbeProcessedTotal:     probeProcessed,
		LaravelFallbackWorkers:  laravelFallbackWorkers,
		Settings:                settings,
		ProviderHealth:          providerHealth,
		ProviderPolicies: ProviderPoliciesData{
			PolicyEngineEnabled:  settings.ProviderPolicyEngineEnabled,
			AdaptiveRetryEnabled: settings.AdaptiveRetryEnabled,
			AutoProtectEnabled:   settings.ProviderAutoprotectEnabled,
			AutopilotEnabled:     s.cfg.PolicyCanaryAutopilotEnabled,
			ActiveVersion:        activePolicyVersion,
			LastReloadAt:         providerPolicyState.LastReloadAt,
			ReloadCount:          providerPolicyState.ReloadCount,
			Modes:                providerModes,
		},
		RoutingQuality: routingQuality,
	}, nil
}

func hasWorkerTag(tags []string, expected string) bool {
	for _, tag := range tags {
		if tag == expected {
			return true
		}
	}

	return false
}
