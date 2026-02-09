package main

import "testing"

func TestNormalizeRuntimeSettingsFallsBackCanaryToDefaultWhenZero(t *testing.T) {
	defaults := RuntimeSettings{
		AutoscaleCanaryPercent: 35,
	}

	settings := RuntimeSettings{
		AutoscaleCanaryPercent: 0,
	}

	normalized := normalizeRuntimeSettings(settings, defaults)
	if normalized.AutoscaleCanaryPercent != defaults.AutoscaleCanaryPercent {
		t.Fatalf("expected autoscale canary fallback %d, got %d", defaults.AutoscaleCanaryPercent, normalized.AutoscaleCanaryPercent)
	}
}

func TestNormalizeRuntimeSettingsFallbackCanaryUsesSafeDefaultWhenConfiguredDefaultZero(t *testing.T) {
	defaults := RuntimeSettings{AutoscaleCanaryPercent: 0}
	settings := RuntimeSettings{AutoscaleCanaryPercent: 0}

	normalized := normalizeRuntimeSettings(settings, defaults)
	if normalized.AutoscaleCanaryPercent != 100 {
		t.Fatalf("expected fallback autoscale canary 100, got %d", normalized.AutoscaleCanaryPercent)
	}
}

func TestStaleWorkerDeleteKeysIncludePoolAndLastSeen(t *testing.T) {
	workerID := "worker-abc"
	keys := staleWorkerDeleteKeys(workerID)

	expectedPool := workerKey(workerID, "pool")
	expectedLastSeen := workerKey(workerID, "last_seen")

	if !containsString(keys, expectedPool) {
		t.Fatalf("expected stale delete keys to include %q", expectedPool)
	}
	if !containsString(keys, expectedLastSeen) {
		t.Fatalf("expected stale delete keys to include %q", expectedLastSeen)
	}
	expectedProviderMetrics := workerKey(workerID, "provider_metrics")
	if !containsString(keys, expectedProviderMetrics) {
		t.Fatalf("expected stale delete keys to include %q", expectedProviderMetrics)
	}
}

func TestNormalizeProviderMode(t *testing.T) {
	if mode := normalizeProviderMode("cautious"); mode != "cautious" {
		t.Fatalf("expected cautious, got %q", mode)
	}
	if mode := normalizeProviderMode("invalid"); mode != "" {
		t.Fatalf("expected empty mode for invalid input, got %q", mode)
	}
}

func containsString(values []string, target string) bool {
	for _, value := range values {
		if value == target {
			return true
		}
	}

	return false
}
