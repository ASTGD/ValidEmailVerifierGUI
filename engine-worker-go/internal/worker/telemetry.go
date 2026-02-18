package worker

import (
	"strings"
	"sync"

	"engine-worker-go/internal/api"
)

type workerTelemetry struct {
	mu sync.Mutex

	screeningProcessed int64
	screeningErrors    int64

	smtpProcessed int64
	smtpErrors    int64

	smtpTempfail int64
	smtpReject   int64
	smtpUnknown  int64
	smtpCatchAll int64

	provider          map[string]*providerCounters
	reasonTagCounters map[string]int64

	retryClaimsTotal              int64
	retryAntiAffinitySuccessTotal int64
	sameWorkerAvoidTotal          int64
	samePoolAvoidTotal            int64
	providerAffinityHitTotal      int64
	fallbackClaimTotal            int64

	sessionRetrySameConnTotal int64
	sessionRetryNewConnTotal  int64
	throttleAppliedTotal      int64
	mxFallbackAttemptsTotal   int64
}

type providerCounters struct {
	Processed int64
	Tempfail  int64
	Reject    int64
	Unknown   int64
	CatchAll  int64
}

type telemetrySnapshot struct {
	stageMetrics          *api.ControlPlaneStageMetrics
	smtpMetrics           *api.ControlPlaneSMTPMetrics
	providerMetrics       []api.ControlPlaneProviderMetric
	routingMetrics        *api.ControlPlaneRoutingMetrics
	sessionMetrics        *api.ControlPlaneSessionMetrics
	attemptRouteMetrics   *api.ControlPlaneAttemptRouteMetrics
	retryAntiAffinityHits int64
	unknownReasonTags     map[string]int64
	reasonTagCounts       map[string]int64
}

func newWorkerTelemetry() *workerTelemetry {
	return &workerTelemetry{
		provider:          map[string]*providerCounters{},
		reasonTagCounters: map[string]int64{},
	}
}

func (t *workerTelemetry) recordChunkSuccess(
	stage string,
	provider string,
	outputs *chunkOutputs,
) {
	if outputs == nil {
		return
	}

	t.mu.Lock()
	defer t.mu.Unlock()

	switch stage {
	case "smtp_probe":
		t.smtpProcessed += int64(outputs.EmailCount)
		t.smtpReject += int64(outputs.InvalidCount)
		t.smtpUnknown += int64(outputs.RiskyCount)
		t.smtpTempfail += int64(outputs.baseReasonCount("smtp_tempfail"))
		t.smtpCatchAll += int64(outputs.baseReasonPrefixCount("catch_all"))

		providerName := normalizeProviderName(provider)
		counters := t.provider[providerName]
		if counters == nil {
			counters = &providerCounters{}
			t.provider[providerName] = counters
		}
		counters.Processed += int64(outputs.EmailCount)
		counters.Reject += int64(outputs.InvalidCount)
		counters.Unknown += int64(outputs.RiskyCount)
		counters.Tempfail += int64(outputs.baseReasonCount("smtp_tempfail"))
		counters.CatchAll += int64(outputs.baseReasonPrefixCount("catch_all"))
		for reasonTag, count := range outputs.ReasonTags {
			normalizedTag := strings.ToLower(strings.TrimSpace(reasonTag))
			if normalizedTag == "" {
				continue
			}
			t.reasonTagCounters[normalizedTag] += int64(count)
		}
		if tempfailCount := outputs.baseReasonCount("smtp_tempfail"); tempfailCount > 0 {
			t.throttleAppliedTotal += int64(tempfailCount)
		}
		if mxFallbackCount := outputs.baseReasonPrefixCount("mx_fallback"); mxFallbackCount > 0 {
			t.mxFallbackAttemptsTotal += int64(mxFallbackCount)
		}
	default:
		t.screeningProcessed += int64(outputs.EmailCount)
	}
}

func (t *workerTelemetry) recordChunkFailure(stage string) {
	t.mu.Lock()
	defer t.mu.Unlock()

	if stage == "smtp_probe" {
		t.smtpErrors++
		return
	}

	t.screeningErrors++
}

type claimRoutingSnapshot struct {
	ProcessingStage  string
	RetryAttempt     int
	LastWorkerIDs    []string
	WorkerID         string
	PreferredPool    string
	WorkerPool       string
	RoutingProvider  string
	ProviderAffinity string
}

func (t *workerTelemetry) recordClaimRouting(snapshot claimRoutingSnapshot) {
	if strings.ToLower(strings.TrimSpace(snapshot.ProcessingStage)) != "smtp_probe" {
		return
	}

	t.mu.Lock()
	defer t.mu.Unlock()

	if snapshot.ProviderAffinity != "" && snapshot.RoutingProvider == snapshot.ProviderAffinity {
		t.providerAffinityHitTotal++
	} else {
		t.fallbackClaimTotal++
	}

	if snapshot.RetryAttempt <= 0 {
		return
	}

	t.retryClaimsTotal++
	t.sessionRetryNewConnTotal++

	sameWorkerAvoided := false
	if len(snapshot.LastWorkerIDs) > 0 {
		sameWorkerAvoided = true
		for _, workerID := range snapshot.LastWorkerIDs {
			if strings.TrimSpace(workerID) == snapshot.WorkerID {
				sameWorkerAvoided = false
				break
			}
		}
	}
	if sameWorkerAvoided {
		t.sameWorkerAvoidTotal++
	}

	samePoolAvoided := false
	if snapshot.PreferredPool != "" && snapshot.WorkerPool != "" && snapshot.PreferredPool != snapshot.WorkerPool {
		samePoolAvoided = true
		t.samePoolAvoidTotal++
	}

	if sameWorkerAvoided || samePoolAvoided {
		t.retryAntiAffinitySuccessTotal++
	}
}

func (t *workerTelemetry) snapshot() telemetrySnapshot {
	t.mu.Lock()
	defer t.mu.Unlock()

	stageMetrics := &api.ControlPlaneStageMetrics{
		Screening: &api.ControlPlaneStageMetric{
			Processed: t.screeningProcessed,
			Errors:    t.screeningErrors,
		},
		SMTPProbe: &api.ControlPlaneStageMetric{
			Processed: t.smtpProcessed,
			Errors:    t.smtpErrors,
		},
	}

	smtpMetrics := &api.ControlPlaneSMTPMetrics{}
	if t.smtpProcessed > 0 {
		denominator := float64(t.smtpProcessed)
		smtpMetrics.TempfailRate = float64(t.smtpTempfail) / denominator
		smtpMetrics.RejectRate = float64(t.smtpReject) / denominator
		smtpMetrics.UnknownRate = float64(t.smtpUnknown) / denominator
		smtpMetrics.CatchAllRate = float64(t.smtpCatchAll) / denominator
	}

	providerMetrics := make([]api.ControlPlaneProviderMetric, 0, len(t.provider))
	for providerName, counters := range t.provider {
		if counters == nil || counters.Processed <= 0 {
			continue
		}

		denominator := float64(counters.Processed)
		providerMetrics = append(providerMetrics, api.ControlPlaneProviderMetric{
			Provider:        providerName,
			TempfailRate:    float64(counters.Tempfail) / denominator,
			RejectRate:      float64(counters.Reject) / denominator,
			UnknownRate:     float64(counters.Unknown) / denominator,
			PolicyBlockRate: 0,
			AvgRetryAfter:   0,
		})
	}

	return telemetrySnapshot{
		stageMetrics:    stageMetrics,
		smtpMetrics:     smtpMetrics,
		providerMetrics: providerMetrics,
		routingMetrics: &api.ControlPlaneRoutingMetrics{
			RetryClaimsTotal:              t.retryClaimsTotal,
			RetryAntiAffinitySuccessTotal: t.retryAntiAffinitySuccessTotal,
			SameWorkerAvoidTotal:          t.sameWorkerAvoidTotal,
			SamePoolAvoidTotal:            t.samePoolAvoidTotal,
			ProviderAffinityHitTotal:      t.providerAffinityHitTotal,
			FallbackClaimTotal:            t.fallbackClaimTotal,
		},
		sessionMetrics: &api.ControlPlaneSessionMetrics{
			ConnectionReuseRate:       0,
			SessionRetrySameConnTotal: t.sessionRetrySameConnTotal,
			SessionRetryNewConnTotal:  t.sessionRetryNewConnTotal,
			ThrottleAppliedTotal:      t.throttleAppliedTotal,
		},
		attemptRouteMetrics: &api.ControlPlaneAttemptRouteMetrics{
			AttemptsTotal:           t.smtpProcessed,
			RetryAttemptsTotal:      t.retryClaimsTotal,
			MXFallbackAttemptsTotal: t.mxFallbackAttemptsTotal,
		},
		retryAntiAffinityHits: t.retryAntiAffinitySuccessTotal,
		unknownReasonTags:     unknownReasonTagCounters(t.reasonTagCounters),
		reasonTagCounts:       cloneReasonTagCounters(t.reasonTagCounters),
	}
}

func cloneReasonTagCounters(source map[string]int64) map[string]int64 {
	if len(source) == 0 {
		return map[string]int64{}
	}

	cloned := make(map[string]int64, len(source))
	for key, value := range source {
		cloned[key] = value
	}

	return cloned
}

func unknownReasonTagCounters(source map[string]int64) map[string]int64 {
	if len(source) == 0 {
		return map[string]int64{}
	}

	allowed := map[string]string{
		"unknown_transient":            "unknown_transient",
		"policy_blocked_ambiguous":     "policy_blocked_ambiguous",
		"provider_tempfail_unresolved": "provider_tempfail_unresolved",
		"connection_unstable":          "connection_unstable",
		"identity_rejected":            "identity_rejected",
		"other_unknown":                "other_unknown",
		// Backward compatibility aliases.
		"policy_blocked": "policy_blocked_ambiguous",
		"greylist":       "provider_tempfail_unresolved",
		"rate_limit":     "provider_tempfail_unresolved",
		"auth_required":  "identity_rejected",
	}

	filtered := make(map[string]int64)
	for key, value := range source {
		normalized := strings.ToLower(strings.TrimSpace(key))
		if normalized == "" || value <= 0 {
			continue
		}

		if mapped, ok := allowed[normalized]; ok {
			filtered[mapped] += value
		}
	}

	return filtered
}

func normalizeProviderName(provider string) string {
	value := strings.ToLower(strings.TrimSpace(provider))
	switch value {
	case "gmail", "microsoft", "yahoo", "generic":
		return value
	default:
		return "generic"
	}
}
