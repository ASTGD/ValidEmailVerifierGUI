package main

import (
	"strings"
	"testing"
)

func TestProviderAccuracyCalibrationFromHealth(t *testing.T) {
	health := []ProviderHealthSummary{
		{
			Provider:          "gmail",
			Workers:           5,
			UnknownRate:       0.2,
			PolicyBlockedRate: 0.1,
		},
		{
			Provider:          "yahoo",
			Workers:           1,
			UnknownRate:       0.4,
			PolicyBlockedRate: 0.2,
		},
	}

	calibration := providerAccuracyCalibrationFromHealth(health)
	if len(calibration) != 2 {
		t.Fatalf("expected two calibration rows, got %d", len(calibration))
	}
	if calibration[0].Provider != "gmail" {
		t.Fatalf("expected first provider to be gmail, got %s", calibration[0].Provider)
	}
	if calibration[0].CalibrationConfidence != "high" {
		t.Fatalf("expected gmail confidence high, got %s", calibration[0].CalibrationConfidence)
	}
	if calibration[1].CalibrationConfidence != "low" {
		t.Fatalf("expected yahoo confidence low, got %s", calibration[1].CalibrationConfidence)
	}
}

func TestProviderUnknownClustersFromWorkers(t *testing.T) {
	workers := []WorkerSummary{
		{
			WorkerID: "worker-1",
			ProviderMetrics: []ProviderMetric{
				{Provider: "gmail"},
			},
			UnknownReasonTags: map[string]int64{
				"unknown_transient": 3,
			},
		},
		{
			WorkerID: "worker-2",
			ProviderMetrics: []ProviderMetric{
				{Provider: "gmail"},
			},
			UnknownReasonTags: map[string]int64{
				"unknown_transient": 2,
			},
		},
	}

	clusters := providerUnknownClustersFromWorkers(workers)
	if len(clusters) != 1 {
		t.Fatalf("expected one cluster, got %d", len(clusters))
	}

	cluster := clusters[0]
	if cluster.Provider != "gmail" {
		t.Fatalf("expected cluster provider gmail, got %s", cluster.Provider)
	}
	if cluster.Tag != "unknown_transient" {
		t.Fatalf("expected unknown_transient tag, got %s", cluster.Tag)
	}
	if cluster.Count != 5 {
		t.Fatalf("expected aggregated tag count 5, got %d", cluster.Count)
	}
	if cluster.SampleWorkers != 2 {
		t.Fatalf("expected sample worker count 2, got %d", cluster.SampleWorkers)
	}
}

func TestProviderUnknownClustersFromWorkersUsesGenericForMultiProviderWorkers(t *testing.T) {
	workers := []WorkerSummary{
		{
			WorkerID: "worker-1",
			ProviderMetrics: []ProviderMetric{
				{Provider: "gmail"},
				{Provider: "microsoft"},
			},
			UnknownReasonTags: map[string]int64{
				"unknown_transient": 3,
			},
		},
	}

	clusters := providerUnknownClustersFromWorkers(workers)
	if len(clusters) != 1 {
		t.Fatalf("expected one cluster, got %d", len(clusters))
	}

	if clusters[0].Provider != "generic" {
		t.Fatalf("expected multi-provider worker to map unknown cluster to generic, got %s", clusters[0].Provider)
	}
	if clusters[0].Count != 3 {
		t.Fatalf("expected cluster count 3, got %d", clusters[0].Count)
	}
}

func TestProviderUnknownReasonConcentrationFromWorkers(t *testing.T) {
	workers := []WorkerSummary{
		{
			WorkerID: "worker-1",
			ProviderMetrics: []ProviderMetric{
				{Provider: "gmail"},
			},
			UnknownReasonTags: map[string]int64{
				"provider_tempfail_unresolved": 6,
				"unknown_transient":            4,
			},
		},
	}

	reasons := providerUnknownReasonConcentrationFromWorkers(workers)
	if len(reasons) != 2 {
		t.Fatalf("expected two reason rows, got %d", len(reasons))
	}

	if reasons[0].Tag != "provider_tempfail_unresolved" {
		t.Fatalf("expected top reason provider_tempfail_unresolved, got %s", reasons[0].Tag)
	}
	if reasons[0].Share < 0.599 || reasons[0].Share > 0.601 {
		t.Fatalf("expected top reason share near 0.6, got %.4f", reasons[0].Share)
	}
	if reasons[1].Share < 0.399 || reasons[1].Share > 0.401 {
		t.Fatalf("expected second reason share near 0.4, got %.4f", reasons[1].Share)
	}
}

func TestFilterProviderDrift(t *testing.T) {
	drift := []ProviderQualityDriftSummary{
		{Provider: "gmail"},
		{Provider: "microsoft"},
	}

	filtered := filterProviderDrift(drift, []string{"gmail"})
	if len(filtered) != 1 {
		t.Fatalf("expected one filtered row, got %d", len(filtered))
	}
	if filtered[0].Provider != "gmail" {
		t.Fatalf("expected filtered provider gmail, got %s", filtered[0].Provider)
	}
}

func TestBuildPolicyShadowRunSummary(t *testing.T) {
	summary := buildPolicyShadowRunSummary([]PolicyShadowEvaluateResult{
		{
			Provider:            "gmail",
			UnknownRate:         0.20,
			TempfailRecoveryPct: 80,
			PolicyBlockedRate:   0.04,
			Recommendation:      "set_cautious",
		},
		{
			Provider:            "microsoft",
			UnknownRate:         0.10,
			TempfailRecoveryPct: 90,
			PolicyBlockedRate:   0.02,
			Recommendation:      "rollback_candidate",
		},
	})

	if summary.ProviderCount != 2 {
		t.Fatalf("expected provider count 2, got %d", summary.ProviderCount)
	}
	if summary.HighestRiskRecommendation != "rollback_candidate" {
		t.Fatalf("expected highest recommendation rollback_candidate, got %s", summary.HighestRiskRecommendation)
	}
	if summary.UnknownRateAvg <= 0 || summary.TempfailRecoveryPctAvg <= 0 {
		t.Fatalf("expected positive averages, got unknown=%f tempfail=%f", summary.UnknownRateAvg, summary.TempfailRecoveryPctAvg)
	}
}

func TestBuildPolicyShadowCompareFlagsRegressionViolations(t *testing.T) {
	server := &Server{
		cfg: Config{
			PolicyCanaryUnknownRegressionThreshold:    0.05,
			PolicyCanaryTempfailRecoveryDropThreshold: 0.10,
			PolicyCanaryPolicyBlockSpikeThreshold:     0.10,
			PolicyShadowPromoteGateEnforced:           true,
		},
	}

	stats := ControlPlaneStats{
		Settings: RuntimeSettings{
			PolicyCanaryUnknownRegressionThreshold:    0.05,
			PolicyCanaryTempfailRecoveryDropThreshold: 0.10,
			PolicyCanaryPolicyBlockSpikeThreshold:     0.10,
		},
		ProviderPolicies: ProviderPoliciesData{
			ActiveVersion: "v1.0.0",
		},
		ProviderHealth: []ProviderHealthSummary{
			{
				Provider:          "gmail",
				UnknownRate:       0.20,
				PolicyBlockedRate: 0.03,
			},
		},
		PolicyShadowRuns: []PolicyShadowRunRecord{
			{
				CandidateVersion: "v1.1.0",
				ActiveVersion:    "v1.0.0",
				EvaluatedAt:      "2026-02-18T12:00:00Z",
				Results: []PolicyShadowEvaluateResult{
					{
						Provider:            "gmail",
						UnknownRate:         0.30,
						TempfailRecoveryPct: 60,
						PolicyBlockedRate:   0.20,
						Recommendation:      "rollback_candidate",
					},
				},
			},
		},
	}

	compare := server.buildPolicyShadowCompare(stats, "v1.1.0")
	if compare.Eligible {
		t.Fatal("expected compare to be ineligible")
	}
	if compare.GateMode != "enforce" {
		t.Fatalf("expected enforce gate mode, got %s", compare.GateMode)
	}
	if len(compare.Violations) == 0 {
		t.Fatal("expected violations in compare output")
	}
}

func TestNewPolicyShadowRunUUID(t *testing.T) {
	value := newPolicyShadowRunUUID()
	if strings.TrimSpace(value) == "" {
		t.Fatal("expected non-empty uuid")
	}
	if len(value) < 32 {
		t.Fatalf("expected uuid-like value with length >= 32, got %q", value)
	}
}
