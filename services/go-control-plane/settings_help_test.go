package main

import (
	"testing"
	"time"
)

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
		if tip.ChangeKind == "numeric" {
			if !tip.HasRecommendedRange {
				t.Fatalf("runtime help key %s missing recommended range", key)
			}
			if !tip.HasCautionRange {
				t.Fatalf("runtime help key %s missing caution range", key)
			}
			if tip.CautionRangeMax < tip.RecommendedRangeMin || tip.CautionRangeMin > tip.RecommendedRangeMax {
				t.Fatalf("runtime help key %s expected caution range to overlap safe range", key)
			}
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

	numericTip, ok := help["alert_error_rate_threshold"]
	if !ok {
		t.Fatalf("missing key: alert_error_rate_threshold")
	}
	if numericTip.ChangeKind != "numeric" {
		t.Fatalf("expected alert_error_rate_threshold to be numeric kind, got %q", numericTip.ChangeKind)
	}

	toggleTip, ok := help["adaptive_retry_enabled"]
	if !ok {
		t.Fatalf("missing key: adaptive_retry_enabled")
	}
	if toggleTip.ChangeUp == "" || toggleTip.ChangeDown == "" {
		t.Fatalf("expected toggle guidance text for adaptive_retry_enabled")
	}
}

func TestBuildRuntimeSettingsHelpIncludesRecommendedRangeForNumericFields(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{})
	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	numericTip, ok := help["alert_error_rate_threshold"]
	if !ok {
		t.Fatalf("missing key: alert_error_rate_threshold")
	}
	if !numericTip.HasRecommendedRange {
		t.Fatalf("expected alert_error_rate_threshold to include recommended range")
	}
	if numericTip.RecommendedRangeLabel == "" {
		t.Fatalf("expected alert_error_rate_threshold to include recommended range label")
	}
	if !numericTip.HasCautionRange {
		t.Fatalf("expected alert_error_rate_threshold to include caution range")
	}
	if numericTip.CautionRangeLabel == "" {
		t.Fatalf("expected alert_error_rate_threshold to include caution range label")
	}
	if numericTip.CautionRangeMin < 0 {
		t.Fatalf("expected alert_error_rate_threshold caution min to be clamped >= 0, got %.2f", numericTip.CautionRangeMin)
	}

	toggleTip, ok := help["autoscale_enabled"]
	if !ok {
		t.Fatalf("missing key: autoscale_enabled")
	}
	if toggleTip.HasRecommendedRange {
		t.Fatalf("did not expect toggle setting to include numeric range")
	}
	if toggleTip.HasCautionRange {
		t.Fatalf("did not expect toggle setting to include caution range")
	}
}

func TestBuildRuntimeSettingsHelpClampsCautionRangeForZeroToOneThresholds(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{
		ProviderUnknownWarnRate: 0.20,
	})
	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	tip, ok := help["provider_unknown_warn_rate"]
	if !ok {
		t.Fatalf("missing key: provider_unknown_warn_rate")
	}
	if !tip.HasCautionRange {
		t.Fatalf("expected provider_unknown_warn_rate to include caution range")
	}
	if tip.CautionRangeMin < 0 || tip.CautionRangeMax > 1 {
		t.Fatalf("expected caution range to be clamped in [0,1], got %.2f..%.2f", tip.CautionRangeMin, tip.CautionRangeMax)
	}
}

func TestFirstUnsafeRuntimeSettingDetectsOnlyHighRiskValues(t *testing.T) {
	defaults := defaultRuntimeSettings(Config{
		AlertHeartbeatGrace:                       300 * time.Second,
		AlertCooldown:                             300 * time.Second,
		AlertCheckInterval:                        30 * time.Second,
		AlertErrorRateThreshold:                   10,
		StaleWorkerTTL:                            24 * time.Hour,
		StuckDesiredGrace:                         600 * time.Second,
		AutoScaleInterval:                         30 * time.Second,
		AutoScaleCooldown:                         120 * time.Second,
		AutoScaleMinDesired:                       1,
		AutoScaleMaxDesired:                       4,
		AutoScaleCanaryPercent:                    100,
		QuarantineErrorRate:                       15,
		PolicyCanaryWindowMinutes:                 15,
		PolicyCanaryRequiredHealthWindows:         4,
		PolicyCanaryUnknownRegressionThreshold:    0.05,
		PolicyCanaryTempfailRecoveryDropThreshold: 0.10,
		PolicyCanaryPolicyBlockSpikeThreshold:     0.10,
		PolicyCanaryMinProviderWorkers:            1,
	})
	defaults = normalizeRuntimeSettings(defaults, defaults)

	safeSettings := defaults
	if key, _, _, found := firstUnsafeRuntimeSetting(defaults, safeSettings); found {
		t.Fatalf("did not expect unsafe key for safe settings, got %s", key)
	}

	mediumSettings := defaults
	mediumSettings.AlertErrorRateThreshold = 13
	if key, _, _, found := firstUnsafeRuntimeSetting(defaults, mediumSettings); found {
		t.Fatalf("did not expect medium setting to be unsafe, got %s", key)
	}

	unsafeSettings := defaults
	unsafeSettings.AlertErrorRateThreshold = 100
	key, tip, _, found := firstUnsafeRuntimeSetting(defaults, unsafeSettings)
	if !found {
		t.Fatal("expected unsafe setting to be detected")
	}
	if key != "alert_error_rate_threshold" {
		t.Fatalf("expected alert_error_rate_threshold to be unsafe, got %s", key)
	}
	if tip.CautionRangeLabel == "" {
		t.Fatalf("expected unsafe tip to include caution range label")
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
