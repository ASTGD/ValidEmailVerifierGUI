package main

import "testing"

func TestBuildRuntimeSettingsHelpIncludesAllRuntimeKeys(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{})
	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	for _, key := range runtimeSettingHelpKeys() {
		tip, ok := help[key]
		if !ok {
			t.Fatalf("missing runtime help key: %s", key)
		}
		if tip.Key == "" || tip.Title == "" || tip.What == "" || tip.Why == "" || tip.RecommendedValue == "" || tip.Monitor == "" || tip.DocsURL == "" {
			t.Fatalf("runtime help key %s has incomplete tip data", key)
		}
		if tip.ChangeKind != "toggle" && tip.ChangeKind != "numeric" && tip.ChangeKind != "action" {
			t.Fatalf("runtime help key %s has invalid change kind: %s", key, tip.ChangeKind)
		}
		if tip.ChangeUp == "" || tip.ChangeDown == "" {
			t.Fatalf("runtime help key %s has missing change text", key)
		}
	}
}

func TestBuildRuntimeSettingsHelpUsesRuntimeDerivedDefaults(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{
		AlertsEnabled:                          true,
		AutoScaleEnabled:                       true,
		AutoScaleCanaryPercent:                 77,
		AlertErrorRateThreshold:                9.5,
		ProviderUnknownCriticalRate:            0.42,
		PolicyCanaryWindowMinutes:              25,
		PolicyCanaryPolicyBlockSpikeThreshold:  0.22,
		PolicyCanaryMinProviderWorkers:         5,
		PolicyCanaryAutopilotEnabled:           true,
		PolicyCanaryRequiredHealthWindows:      6,
		PolicyCanaryUnknownRegressionThreshold: 0.08,
	})

	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	assertRecommendedValue(t, help, "alerts_enabled", "Enabled")
	assertRecommendedValue(t, help, "autoscale_enabled", "Enabled")
	assertRecommendedValue(t, help, "autoscale_canary_percent", "77%")
	assertRecommendedValue(t, help, "alert_error_rate_threshold", "9.50 errors/min")
	assertRecommendedValue(t, help, "provider_unknown_critical_rate", "0.42")
	assertRecommendedValue(t, help, "policy_canary_window_minutes", "25 minutes")
	assertRecommendedValue(t, help, "policy_canary_policy_block_spike_threshold", "0.22")
	assertRecommendedValue(t, help, "policy_canary_min_provider_workers", "5 workers")
	assertRecommendedValue(t, help, "policy_canary_autopilot_enabled", "Enabled")
}

func TestBuildRuntimeSettingsHelpUsesToggleWordingForToggleSettings(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{})
	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	tip, ok := help["autoscale_enabled"]
	if !ok {
		t.Fatalf("missing key: autoscale_enabled")
	}
	if tip.ChangeKind != "toggle" {
		t.Fatalf("expected autoscale_enabled to be toggle kind, got %q", tip.ChangeKind)
	}
	if tip.ChangeUp == "" || tip.ChangeDown == "" {
		t.Fatalf("toggle key has empty change text")
	}
	if tip.ChangeKind != "toggle" {
		t.Fatalf("expected toggle kind for autoscale_enabled, got %q", tip.ChangeKind)
	}

	numericTip, ok := help["alert_error_rate_threshold"]
	if !ok {
		t.Fatalf("missing key: alert_error_rate_threshold")
	}
	if numericTip.ChangeKind != "numeric" {
		t.Fatalf("expected alert_error_rate_threshold to be numeric kind, got %q", numericTip.ChangeKind)
	}
}

func assertRecommendedValue(t *testing.T, help map[string]SettingHelpTip, key string, expected string) {
	t.Helper()

	tip, ok := help[key]
	if !ok {
		t.Fatalf("missing key: %s", key)
	}
	if tip.RecommendedValue != expected {
		t.Fatalf("key %s expected recommended value %q, got %q", key, expected, tip.RecommendedValue)
	}
}
