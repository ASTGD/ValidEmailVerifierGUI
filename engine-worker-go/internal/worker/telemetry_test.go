package worker

import "testing"

func TestRecordClaimRoutingCounters(t *testing.T) {
	t.Parallel()

	telemetry := newWorkerTelemetry()

	telemetry.recordClaimRouting(claimRoutingSnapshot{
		ProcessingStage:  "smtp_probe",
		RetryAttempt:     2,
		LastWorkerIDs:    []string{"worker-old-1", "worker-old-2"},
		WorkerID:         "worker-new",
		PreferredPool:    "pool-a",
		WorkerPool:       "pool-b",
		RoutingProvider:  "gmail",
		ProviderAffinity: "gmail",
	})

	telemetry.recordClaimRouting(claimRoutingSnapshot{
		ProcessingStage:  "smtp_probe",
		RetryAttempt:     0,
		WorkerID:         "worker-new",
		PreferredPool:    "pool-a",
		WorkerPool:       "pool-a",
		RoutingProvider:  "microsoft",
		ProviderAffinity: "gmail",
	})

	snapshot := telemetry.snapshot()
	if snapshot.routingMetrics == nil {
		t.Fatal("expected routing metrics snapshot to be present")
	}

	if snapshot.routingMetrics.RetryClaimsTotal != 1 {
		t.Fatalf("expected retry claims total=1, got %d", snapshot.routingMetrics.RetryClaimsTotal)
	}
	if snapshot.routingMetrics.RetryAntiAffinitySuccessTotal != 1 {
		t.Fatalf("expected anti-affinity success total=1, got %d", snapshot.routingMetrics.RetryAntiAffinitySuccessTotal)
	}
	if snapshot.routingMetrics.SameWorkerAvoidTotal != 1 {
		t.Fatalf("expected same-worker avoid total=1, got %d", snapshot.routingMetrics.SameWorkerAvoidTotal)
	}
	if snapshot.routingMetrics.SamePoolAvoidTotal != 1 {
		t.Fatalf("expected same-pool avoid total=1, got %d", snapshot.routingMetrics.SamePoolAvoidTotal)
	}
	if snapshot.routingMetrics.ProviderAffinityHitTotal != 1 {
		t.Fatalf("expected provider affinity hits=1, got %d", snapshot.routingMetrics.ProviderAffinityHitTotal)
	}
	if snapshot.routingMetrics.FallbackClaimTotal != 1 {
		t.Fatalf("expected fallback claims=1, got %d", snapshot.routingMetrics.FallbackClaimTotal)
	}
	if snapshot.attemptRouteMetrics == nil {
		t.Fatal("expected attempt route metrics snapshot to be present")
	}
	if snapshot.attemptRouteMetrics.RetryAttemptsTotal != 1 {
		t.Fatalf("expected retry attempts total=1, got %d", snapshot.attemptRouteMetrics.RetryAttemptsTotal)
	}
	if snapshot.retryAntiAffinityHits != 1 {
		t.Fatalf("expected retry anti-affinity hits=1, got %d", snapshot.retryAntiAffinityHits)
	}
}
