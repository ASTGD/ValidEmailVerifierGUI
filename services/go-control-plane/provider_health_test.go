package main

import "testing"

func TestAggregateProviderHealthAppliesModeOverridesAndThresholds(t *testing.T) {
	workers := []WorkerSummary{
		{
			WorkerID: "w1",
			ProviderMetrics: []ProviderMetric{
				{
					Provider:      "gmail",
					TempfailRate:  0.62,
					RejectRate:    0.10,
					UnknownRate:   0.12,
					AvgRetryAfter: 260,
				},
			},
		},
		{
			WorkerID: "w2",
			ProviderMetrics: []ProviderMetric{
				{
					Provider:      "gmail",
					TempfailRate:  0.58,
					RejectRate:    0.08,
					UnknownRate:   0.15,
					AvgRetryAfter: 240,
				},
				{
					Provider:      "yahoo",
					TempfailRate:  0.12,
					RejectRate:    0.11,
					UnknownRate:   0.07,
					AvgRetryAfter: 90,
				},
			},
		},
	}

	modes := map[string]ProviderModeState{
		"gmail": {
			Provider: "gmail",
			Mode:     "cautious",
			Source:   "manual",
		},
	}

	health := aggregateProviderHealth(workers, modes, providerHealthThresholds{
		TempfailWarn:     0.30,
		TempfailCritical: 0.55,
		RejectWarn:       0.20,
		RejectCritical:   0.40,
		UnknownWarn:      0.20,
		UnknownCritical:  0.35,
	})

	gmail := findProvider(health, "gmail")
	if gmail == nil {
		t.Fatal("expected gmail provider health")
	}
	if gmail.Mode != "cautious" {
		t.Fatalf("expected gmail mode cautious, got %q", gmail.Mode)
	}
	if gmail.Status != "critical" {
		t.Fatalf("expected gmail critical status, got %q", gmail.Status)
	}
	if gmail.Workers != 2 {
		t.Fatalf("expected gmail workers 2, got %d", gmail.Workers)
	}

	yahoo := findProvider(health, "yahoo")
	if yahoo == nil {
		t.Fatal("expected yahoo provider health")
	}
	if yahoo.Status != "healthy" {
		t.Fatalf("expected yahoo healthy status, got %q", yahoo.Status)
	}
}

func TestClassifyProviderStatusThresholds(t *testing.T) {
	thresholds := providerHealthThresholds{
		TempfailWarn:     0.30,
		TempfailCritical: 0.55,
		RejectWarn:       0.20,
		RejectCritical:   0.40,
		UnknownWarn:      0.20,
		UnknownCritical:  0.35,
	}

	if status := classifyProviderStatus(0.60, 0.0, 0.0, thresholds); status != "critical" {
		t.Fatalf("expected critical status, got %q", status)
	}
	if status := classifyProviderStatus(0.32, 0.0, 0.0, thresholds); status != "warning" {
		t.Fatalf("expected warning status, got %q", status)
	}
	if status := classifyProviderStatus(0.10, 0.10, 0.05, thresholds); status != "healthy" {
		t.Fatalf("expected healthy status, got %q", status)
	}
}

func TestAggregateProviderHealthSkipsUnknownProviders(t *testing.T) {
	workers := []WorkerSummary{
		{
			WorkerID: "w1",
			ProviderMetrics: []ProviderMetric{
				{
					Provider:      "custom-provider",
					TempfailRate:  0.40,
					RejectRate:    0.20,
					UnknownRate:   0.30,
					AvgRetryAfter: 200,
				},
				{
					Provider:      "gmail",
					TempfailRate:  0.10,
					RejectRate:    0.05,
					UnknownRate:   0.02,
					AvgRetryAfter: 120,
				},
			},
		},
	}

	modes := map[string]ProviderModeState{
		"custom-provider": {Provider: "custom-provider", Mode: "drain"},
		"gmail":           {Provider: "gmail", Mode: "cautious"},
	}

	health := aggregateProviderHealth(workers, modes, providerHealthThresholds{
		TempfailWarn:     0.30,
		TempfailCritical: 0.55,
		RejectWarn:       0.20,
		RejectCritical:   0.40,
		UnknownWarn:      0.20,
		UnknownCritical:  0.35,
	})

	if findProvider(health, "custom-provider") != nil {
		t.Fatal("expected unknown provider to be omitted from provider health aggregation")
	}

	gmail := findProvider(health, "gmail")
	if gmail == nil {
		t.Fatal("expected gmail provider health to be present")
	}
	if gmail.Mode != "cautious" {
		t.Fatalf("expected gmail mode cautious, got %q", gmail.Mode)
	}
}

func TestProviderQualityFromHealth(t *testing.T) {
	health := []ProviderHealthSummary{
		{
			Provider:          "gmail",
			Mode:              "normal",
			Status:            "warning",
			TempfailRate:      0.30,
			RejectRate:        0.10,
			UnknownRate:       0.20,
			PolicyBlockedRate: 0.05,
			Workers:           3,
		},
	}

	quality := providerQualityFromHealth(health)
	if len(quality) != 1 {
		t.Fatalf("expected 1 quality record, got %d", len(quality))
	}

	record := quality[0]
	if record.Provider != "gmail" {
		t.Fatalf("expected gmail provider, got %q", record.Provider)
	}
	if record.PolicyBlockedRate != 0.05 {
		t.Fatalf("expected policy blocked rate 0.05, got %.2f", record.PolicyBlockedRate)
	}
	if record.TempfailRecoveryPct <= 0 {
		t.Fatalf("expected positive tempfail recovery percent, got %.2f", record.TempfailRecoveryPct)
	}
}

func TestProviderQualityDriftFromHealth(t *testing.T) {
	health := []ProviderHealthSummary{
		{
			Provider:          "gmail",
			Mode:              "normal",
			Status:            "critical",
			TempfailRate:      0.60,
			UnknownRate:       0.32,
			PolicyBlockedRate: 0.06,
			Workers:           2,
		},
	}

	drift := providerQualityDriftFromHealth(health, providerHealthThresholds{
		TempfailWarn:     0.30,
		TempfailCritical: 0.55,
		RejectWarn:       0.20,
		RejectCritical:   0.40,
		UnknownWarn:      0.20,
		UnknownCritical:  0.35,
	})
	if len(drift) != 1 {
		t.Fatalf("expected one drift record, got %d", len(drift))
	}

	record := drift[0]
	if record.Provider != "gmail" {
		t.Fatalf("expected gmail provider drift record, got %q", record.Provider)
	}
	if record.DriftRecommendation != "rollback_candidate" {
		t.Fatalf("expected rollback_candidate recommendation, got %q", record.DriftRecommendation)
	}
	if record.UnknownDelta <= 0 {
		t.Fatalf("expected positive unknown delta, got %.4f", record.UnknownDelta)
	}
}

func TestProviderRetryEffectivenessFromHealth(t *testing.T) {
	health := []ProviderHealthSummary{
		{
			Provider:      "yahoo",
			Mode:          "cautious",
			Status:        "warning",
			TempfailRate:  0.25,
			UnknownRate:   0.10,
			AvgRetryAfter: 180,
			Workers:       4,
		},
	}

	effectiveness := providerRetryEffectivenessFromHealth(health)
	if len(effectiveness) != 1 {
		t.Fatalf("expected one retry-effectiveness record, got %d", len(effectiveness))
	}

	record := effectiveness[0]
	if record.Provider != "yahoo" {
		t.Fatalf("expected yahoo provider, got %q", record.Provider)
	}
	if record.TempfailRecoveryPct <= 0 {
		t.Fatalf("expected positive tempfail recovery percent, got %.2f", record.TempfailRecoveryPct)
	}
	if record.AvgRetryAfter != 180 {
		t.Fatalf("expected avg retry after 180, got %.2f", record.AvgRetryAfter)
	}
}

func findProvider(items []ProviderHealthSummary, provider string) *ProviderHealthSummary {
	for i := range items {
		if items[i].Provider == provider {
			return &items[i]
		}
	}

	return nil
}
