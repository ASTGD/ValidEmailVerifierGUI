package main

import (
	"context"
	"encoding/json"
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

func TestDefaultRuntimeSettingsIncludesProviderFeatureToggles(t *testing.T) {
	cfg := Config{
		ProviderPolicyEngineEnabled: true,
		AdaptiveRetryEnabled:        true,
		ProviderAutoprotectEnabled:  true,
	}

	settings := defaultRuntimeSettings(cfg)
	if !settings.ProviderPolicyEngineEnabled {
		t.Fatal("expected provider policy engine default toggle to be true")
	}
	if !settings.AdaptiveRetryEnabled {
		t.Fatal("expected adaptive retry default toggle to be true")
	}
	if !settings.ProviderAutoprotectEnabled {
		t.Fatal("expected provider auto-protect default toggle to be true")
	}
}

func TestNormalizeRuntimeSettingsPreservesProviderFeatureToggles(t *testing.T) {
	defaults := RuntimeSettings{
		ProviderPolicyEngineEnabled: true,
		AdaptiveRetryEnabled:        true,
		ProviderAutoprotectEnabled:  true,
	}
	settings := RuntimeSettings{
		ProviderPolicyEngineEnabled: false,
		AdaptiveRetryEnabled:        true,
		ProviderAutoprotectEnabled:  false,
	}

	normalized := normalizeRuntimeSettings(settings, defaults)
	if normalized.ProviderPolicyEngineEnabled {
		t.Fatal("expected provider policy engine toggle to preserve explicit false")
	}
	if !normalized.AdaptiveRetryEnabled {
		t.Fatal("expected adaptive retry toggle to preserve explicit true")
	}
	if normalized.ProviderAutoprotectEnabled {
		t.Fatal("expected provider auto-protect toggle to preserve explicit false")
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

func TestApplySMTPPolicyPromoteStateActivatesTargetAndDeactivatesCurrent(t *testing.T) {
	now := "2026-01-02T03:04:05Z"
	records := map[string]SMTPPolicyVersionRecord{
		"v1": {
			Version:       "v1",
			Status:        "active",
			Active:        true,
			CanaryPercent: 100,
		},
	}

	updated, target, activeVersion := applySMTPPolicyPromoteState(records, "v1", "v2", 25, "ops", now)
	if activeVersion != "v2" {
		t.Fatalf("expected active version v2, got %q", activeVersion)
	}
	if target.Version != "v2" || !target.Active || target.Status != "active" {
		t.Fatalf("expected target v2 active record, got %+v", target)
	}
	if target.CanaryPercent != 25 {
		t.Fatalf("expected target canary 25, got %d", target.CanaryPercent)
	}

	previous := updated["v1"]
	if previous.Active {
		t.Fatalf("expected previous version to be inactive, got %+v", previous)
	}
	if previous.Status != "inactive" {
		t.Fatalf("expected previous version status inactive, got %q", previous.Status)
	}
}

func TestSelectSMTPRollbackTargetSelectsPreviousPromotedVersion(t *testing.T) {
	records := map[string]SMTPPolicyVersionRecord{
		"v1": {Version: "v1", Status: "inactive"},
		"v2": {Version: "v2", Status: "active", Active: true},
	}
	history := []SMTPPolicyRolloutRecord{
		{Action: "rollback", Version: "v1", CanaryPercent: 100},
		{Action: "promote", Version: "v2", CanaryPercent: 40},
		{Action: "promote", Version: "v1", CanaryPercent: 100},
	}

	version, canary, err := selectSMTPRollbackTarget(records, "v2", history)
	if err != nil {
		t.Fatalf("expected rollback target, got error: %v", err)
	}
	if version != "v1" {
		t.Fatalf("expected rollback target v1, got %q", version)
	}
	if canary != 100 {
		t.Fatalf("expected rollback canary 100, got %d", canary)
	}

	now := "2026-01-04T03:04:05Z"
	updated, target, activeVersion := applySMTPPolicyPromoteState(records, "v2", version, canary, "ops", now)
	if activeVersion != "v1" {
		t.Fatalf("expected active version to switch to v1, got %q", activeVersion)
	}
	if !target.Active || target.Status != "active" {
		t.Fatalf("expected rollback target record active, got %+v", target)
	}
	if updated["v2"].Active {
		t.Fatalf("expected previous active version v2 to be inactive after rollback promote, got %+v", updated["v2"])
	}
}

func TestSelectSMTPRollbackTargetErrorsWhenNoCandidateExists(t *testing.T) {
	records := map[string]SMTPPolicyVersionRecord{
		"v2": {Version: "v2", Status: "active", Active: true},
	}
	history := []SMTPPolicyRolloutRecord{
		{Action: "promote", Version: "v2", CanaryPercent: 100},
	}

	_, _, err := selectSMTPRollbackTarget(records, "v2", history)
	if err == nil || !strings.Contains(err.Error(), "no rollback target available") {
		t.Fatalf("expected no rollback target error, got %v", err)
	}
}

func TestBuildSMTPPolicyVersionListMarksActiveVersion(t *testing.T) {
	v1Payload, err := json.Marshal(SMTPPolicyVersionRecord{
		Version:       "v1",
		Status:        "inactive",
		CanaryPercent: 10,
	})
	if err != nil {
		t.Fatalf("failed to marshal v1 payload: %v", err)
	}
	v2Payload, err := json.Marshal(SMTPPolicyVersionRecord{
		Version:       "v2",
		Status:        "active",
		CanaryPercent: 60,
	})
	if err != nil {
		t.Fatalf("failed to marshal v2 payload: %v", err)
	}

	items := buildSMTPPolicyVersionList(map[string]string{
		"v1": string(v1Payload),
		"v2": string(v2Payload),
	}, "v2")

	if len(items) != 2 {
		t.Fatalf("expected 2 policy version items, got %d", len(items))
	}

	activeCount := 0
	for _, item := range items {
		if item.Active {
			activeCount++
			if item.Version != "v2" {
				t.Fatalf("expected only v2 to be active, got %q", item.Version)
			}
		}
	}
	if activeCount != 1 {
		t.Fatalf("expected exactly one active version, got %d", activeCount)
	}
}

func TestBuildSMTPPolicyRolloutRecordSupportsPromoteAndRollbackActions(t *testing.T) {
	promote := buildSMTPPolicyRolloutRecord("promote", "v3", 20, "ops", "canary start", "2026-01-02T03:04:05Z")
	if promote.Action != "promote" || promote.Version != "v3" {
		t.Fatalf("unexpected promote rollout entry: %+v", promote)
	}

	rollback := buildSMTPPolicyRolloutRecord("rollback", "v2", 100, "ops", "restore previous", "2026-01-03T03:04:05Z")
	if rollback.Action != "rollback" || rollback.Version != "v2" {
		t.Fatalf("unexpected rollback rollout entry: %+v", rollback)
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
