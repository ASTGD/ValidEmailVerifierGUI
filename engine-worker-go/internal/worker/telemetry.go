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

	provider map[string]*providerCounters
}

type providerCounters struct {
	Processed int64
	Tempfail  int64
	Reject    int64
	Unknown   int64
	CatchAll  int64
}

type telemetrySnapshot struct {
	stageMetrics    *api.ControlPlaneStageMetrics
	smtpMetrics     *api.ControlPlaneSMTPMetrics
	providerMetrics []api.ControlPlaneProviderMetric
}

func newWorkerTelemetry() *workerTelemetry {
	return &workerTelemetry{
		provider: map[string]*providerCounters{},
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
	}
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
