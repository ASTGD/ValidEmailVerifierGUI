package main

import (
	"context"
	"strings"
	"testing"
)

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

func TestNormalizeRuntimeSettingsFallsBackUIRefreshIntervalsToDefaults(t *testing.T) {
	defaults := RuntimeSettings{
		UIOverviewLiveIntervalSecond: 7,
		UIWorkersRefreshSecond:       11,
		UIPoolsRefreshSecond:         13,
		UIAlertsRefreshSecond:        17,
	}
	settings := RuntimeSettings{
		UIOverviewLiveIntervalSecond: 0,
		UIWorkersRefreshSecond:       0,
		UIPoolsRefreshSecond:         0,
		UIAlertsRefreshSecond:        0,
	}

	normalized := normalizeRuntimeSettings(settings, defaults)
	if normalized.UIOverviewLiveIntervalSecond != defaults.UIOverviewLiveIntervalSecond {
		t.Fatalf("expected overview interval fallback %d, got %d", defaults.UIOverviewLiveIntervalSecond, normalized.UIOverviewLiveIntervalSecond)
	}
	if normalized.UIWorkersRefreshSecond != defaults.UIWorkersRefreshSecond {
		t.Fatalf("expected workers refresh fallback %d, got %d", defaults.UIWorkersRefreshSecond, normalized.UIWorkersRefreshSecond)
	}
	if normalized.UIPoolsRefreshSecond != defaults.UIPoolsRefreshSecond {
		t.Fatalf("expected pools refresh fallback %d, got %d", defaults.UIPoolsRefreshSecond, normalized.UIPoolsRefreshSecond)
	}
	if normalized.UIAlertsRefreshSecond != defaults.UIAlertsRefreshSecond {
		t.Fatalf("expected alerts refresh fallback %d, got %d", defaults.UIAlertsRefreshSecond, normalized.UIAlertsRefreshSecond)
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

func TestNormalizeProviderNameRejectsUnknownProvider(t *testing.T) {
	if provider := normalizeProviderName("gmail"); provider != "gmail" {
		t.Fatalf("expected gmail provider, got %q", provider)
	}
	if provider := normalizeProviderName("unknown-provider"); provider != "" {
		t.Fatalf("expected unknown provider to normalize to empty string, got %q", provider)
	}
}

func TestSetProviderModeRejectsUnknownProvider(t *testing.T) {
	store := &Store{}

	_, err := store.SetProviderMode(context.Background(), "unknown-provider", "normal", "manual")
	if err == nil {
		t.Fatal("expected error for unsupported provider")
	}
	if !strings.Contains(err.Error(), "unsupported provider") {
		t.Fatalf("expected unsupported provider error, got %q", err.Error())
	}
}

func TestBuildProviderModesFiltersUnknownProviders(t *testing.T) {
	values := map[string]string{
		"gmail":        `{"provider":"gmail","mode":"cautious","source":"manual"}`,
		"custom-mx":    `{"provider":"custom-mx","mode":"drain","source":"manual"}`,
		"legacy-bad":   `{"provider":"","mode":"normal","source":"manual"}`,
		"microsoft":    `{"provider":"microsoft","mode":"invalid","source":"manual"}`,
		"invalid_json": `not-json`,
	}

	modes := buildProviderModes(values)
	if _, ok := modes["custom-mx"]; ok {
		t.Fatal("expected unknown provider mode to be filtered out")
	}
	if _, ok := modes["legacy-bad"]; ok {
		t.Fatal("expected invalid legacy provider key to be filtered out")
	}

	gmail, ok := modes["gmail"]
	if !ok {
		t.Fatal("expected gmail mode to be retained")
	}
	if gmail.Mode != "cautious" {
		t.Fatalf("expected gmail cautious mode, got %q", gmail.Mode)
	}

	microsoft, ok := modes["microsoft"]
	if !ok {
		t.Fatal("expected microsoft mode to be retained")
	}
	if microsoft.Mode != "normal" {
		t.Fatalf("expected invalid microsoft mode to normalize to normal, got %q", microsoft.Mode)
	}
}

func TestNormalizePolicyVersionStatus(t *testing.T) {
	if status := normalizePolicyVersionStatus("active"); status != "active" {
		t.Fatalf("expected active, got %q", status)
	}
	if status := normalizePolicyVersionStatus("invalid"); status != "draft" {
		t.Fatalf("expected invalid status fallback to draft, got %q", status)
	}
}

func TestNormalizeCanaryPercent(t *testing.T) {
	if value := normalizeCanaryPercent(0); value != 100 {
		t.Fatalf("expected zero canary fallback to 100, got %d", value)
	}
	if value := normalizeCanaryPercent(101); value != 100 {
		t.Fatalf("expected canary >100 clamp to 100, got %d", value)
	}
	if value := normalizeCanaryPercent(25); value != 25 {
		t.Fatalf("expected canary 25 to pass through, got %d", value)
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
