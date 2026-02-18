package main

import (
	"sort"
	"strings"
)

type providerHealthThresholds struct {
	TempfailWarn     float64
	TempfailCritical float64
	RejectWarn       float64
	RejectCritical   float64
	UnknownWarn      float64
	UnknownCritical  float64
}

type providerAggregate struct {
	workers        int
	tempfailSum    float64
	rejectSum      float64
	unknownSum     float64
	policyBlockSum float64
	retrySum       float64
}

func thresholdsFromConfig(cfg Config) providerHealthThresholds {
	return providerHealthThresholds{
		TempfailWarn:     cfg.ProviderTempfailWarnRate,
		TempfailCritical: cfg.ProviderTempfailCriticalRate,
		RejectWarn:       cfg.ProviderRejectWarnRate,
		RejectCritical:   cfg.ProviderRejectCriticalRate,
		UnknownWarn:      cfg.ProviderUnknownWarnRate,
		UnknownCritical:  cfg.ProviderUnknownCriticalRate,
	}
}

func thresholdsFromRuntimeSettings(settings RuntimeSettings) providerHealthThresholds {
	return providerHealthThresholds{
		TempfailWarn:     settings.ProviderTempfailWarnRate,
		TempfailCritical: settings.ProviderTempfailCriticalRate,
		RejectWarn:       settings.ProviderRejectWarnRate,
		RejectCritical:   settings.ProviderRejectCriticalRate,
		UnknownWarn:      settings.ProviderUnknownWarnRate,
		UnknownCritical:  settings.ProviderUnknownCriticalRate,
	}
}

func aggregateProviderHealth(
	workers []WorkerSummary,
	modes map[string]ProviderModeState,
	thresholds providerHealthThresholds,
) []ProviderHealthSummary {
	aggregates := map[string]providerAggregate{}

	for _, worker := range workers {
		for _, metric := range worker.ProviderMetrics {
			provider := normalizeProviderName(metric.Provider)
			if provider == "" {
				continue
			}

			entry := aggregates[provider]
			entry.workers++
			entry.tempfailSum += metric.TempfailRate
			entry.rejectSum += metric.RejectRate
			entry.unknownSum += metric.UnknownRate
			entry.policyBlockSum += metric.PolicyBlockRate
			entry.retrySum += metric.AvgRetryAfter
			aggregates[provider] = entry
		}
	}

	known := map[string]struct{}{
		"gmail":     {},
		"microsoft": {},
		"yahoo":     {},
		"generic":   {},
	}
	for provider := range modes {
		normalizedProvider := normalizeProviderName(provider)
		if normalizedProvider != "" {
			known[normalizedProvider] = struct{}{}
		}
	}
	for provider := range aggregates {
		normalizedProvider := normalizeProviderName(provider)
		if normalizedProvider != "" {
			known[normalizedProvider] = struct{}{}
		}
	}

	results := make([]ProviderHealthSummary, 0, len(known))
	for provider := range known {
		aggregate := aggregates[provider]
		workersCount := aggregate.workers
		if workersCount < 1 {
			workersCount = 0
		}

		tempfail := averageOrZero(aggregate.tempfailSum, aggregate.workers)
		reject := averageOrZero(aggregate.rejectSum, aggregate.workers)
		unknown := averageOrZero(aggregate.unknownSum, aggregate.workers)
		policyBlocked := averageOrZero(aggregate.policyBlockSum, aggregate.workers)
		retry := averageOrZero(aggregate.retrySum, aggregate.workers)

		mode := "normal"
		if override, ok := modes[provider]; ok {
			if normalized := normalizeProviderMode(override.Mode); normalized != "" {
				mode = normalized
			}
		}

		results = append(results, ProviderHealthSummary{
			Provider:          provider,
			Mode:              mode,
			Status:            classifyProviderStatus(tempfail, reject, unknown, thresholds),
			TempfailRate:      tempfail,
			RejectRate:        reject,
			UnknownRate:       unknown,
			PolicyBlockedRate: policyBlocked,
			AvgRetryAfter:     retry,
			Workers:           workersCount,
		})
	}

	sort.Slice(results, func(i, j int) bool {
		return strings.ToLower(results[i].Provider) < strings.ToLower(results[j].Provider)
	})

	return results
}

func classifyProviderStatus(tempfail, reject, unknown float64, thresholds providerHealthThresholds) string {
	if tempfail >= thresholds.TempfailCritical ||
		reject >= thresholds.RejectCritical ||
		unknown >= thresholds.UnknownCritical {
		return "critical"
	}

	if tempfail >= thresholds.TempfailWarn ||
		reject >= thresholds.RejectWarn ||
		unknown >= thresholds.UnknownWarn {
		return "warning"
	}

	return "healthy"
}

func averageOrZero(sum float64, count int) float64 {
	if count <= 0 {
		return 0
	}
	return sum / float64(count)
}
