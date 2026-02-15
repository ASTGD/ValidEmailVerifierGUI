package main

import (
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
	"time"
)

func TestValidateRuntimeSettingsRiskAllowsLowAndMediumValues(t *testing.T) {
	defaults := validRuntimeSettingsForTest()

	low := defaults
	if err := validateRuntimeSettingsRisk(defaults, low); err != nil {
		t.Fatalf("expected low values to pass risk validation, got %v", err)
	}

	medium := defaults
	medium.AlertErrorRateThreshold = 13
	if err := validateRuntimeSettingsRisk(defaults, medium); err != nil {
		t.Fatalf("expected medium values to pass risk validation, got %v", err)
	}
}

func TestValidateRuntimeSettingsRiskRejectsUnsafeValues(t *testing.T) {
	defaults := validRuntimeSettingsForTest()
	unsafe := defaults
	unsafe.AlertErrorRateThreshold = 100

	err := validateRuntimeSettingsRisk(defaults, unsafe)
	if err == nil {
		t.Fatal("expected unsafe values to fail risk validation")
	}
	if !strings.Contains(err.Error(), "alert_error_rate_threshold") {
		t.Fatalf("expected unsafe error to include key, got %q", err.Error())
	}
}

func TestHandleUIUpdateSettingsRejectsUnsafeValuesWith422(t *testing.T) {
	defaults := validRuntimeSettingsForTest()
	unsafe := defaults
	unsafe.AlertErrorRateThreshold = 100

	form := runtimeSettingsFormValues(unsafe)
	req := httptest.NewRequest(http.MethodPost, "/verifier-engine-room/settings", strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	recorder := httptest.NewRecorder()

	server := &Server{cfg: Config{}}
	server.handleUIUpdateSettings(recorder, req)

	if recorder.Code != http.StatusUnprocessableEntity {
		t.Fatalf("expected status %d, got %d", http.StatusUnprocessableEntity, recorder.Code)
	}
	if !strings.Contains(recorder.Body.String(), "alert_error_rate_threshold") {
		t.Fatalf("expected response to include offending key, got %q", recorder.Body.String())
	}
}

func validRuntimeSettingsForTest() RuntimeSettings {
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

	return normalizeRuntimeSettings(defaults, defaults)
}

func runtimeSettingsFormValues(settings RuntimeSettings) url.Values {
	values := url.Values{}

	if settings.AlertsEnabled {
		values.Set("alerts_enabled", "on")
	}
	if settings.AutoActionsEnabled {
		values.Set("auto_actions_enabled", "on")
	}
	if settings.ProviderPolicyEngineEnabled {
		values.Set("provider_policy_engine_enabled", "on")
	}
	if settings.AdaptiveRetryEnabled {
		values.Set("adaptive_retry_enabled", "on")
	}
	if settings.ProviderAutoprotectEnabled {
		values.Set("provider_autoprotect_enabled", "on")
	}
	if settings.AutoscaleEnabled {
		values.Set("autoscale_enabled", "on")
	}
	if settings.PolicyCanaryAutopilotEnabled {
		values.Set("policy_canary_autopilot_enabled", "on")
	}

	values.Set("alert_error_rate_threshold", formatRuntimeSettingValue(settings.AlertErrorRateThreshold))
	values.Set("alert_heartbeat_grace_seconds", formatRuntimeSettingValue(float64(settings.AlertHeartbeatGraceSecond)))
	values.Set("alert_cooldown_seconds", formatRuntimeSettingValue(float64(settings.AlertCooldownSecond)))
	values.Set("alert_check_interval_seconds", formatRuntimeSettingValue(float64(settings.AlertCheckIntervalSecond)))
	values.Set("stale_worker_ttl_seconds", formatRuntimeSettingValue(float64(settings.StaleWorkerTTLSecond)))
	values.Set("stuck_desired_grace_seconds", formatRuntimeSettingValue(float64(settings.StuckDesiredGraceSecond)))
	values.Set("autoscale_interval_seconds", formatRuntimeSettingValue(float64(settings.AutoscaleIntervalSecond)))
	values.Set("autoscale_cooldown_seconds", formatRuntimeSettingValue(float64(settings.AutoscaleCooldownSecond)))
	values.Set("autoscale_min_desired", formatRuntimeSettingValue(float64(settings.AutoscaleMinDesired)))
	values.Set("autoscale_max_desired", formatRuntimeSettingValue(float64(settings.AutoscaleMaxDesired)))
	values.Set("autoscale_canary_percent", formatRuntimeSettingValue(float64(settings.AutoscaleCanaryPercent)))
	values.Set("quarantine_error_rate_threshold", formatRuntimeSettingValue(settings.QuarantineErrorRateThreshold))
	values.Set("provider_tempfail_warn_rate", formatRuntimeSettingValue(settings.ProviderTempfailWarnRate))
	values.Set("provider_tempfail_critical_rate", formatRuntimeSettingValue(settings.ProviderTempfailCriticalRate))
	values.Set("provider_reject_warn_rate", formatRuntimeSettingValue(settings.ProviderRejectWarnRate))
	values.Set("provider_reject_critical_rate", formatRuntimeSettingValue(settings.ProviderRejectCriticalRate))
	values.Set("provider_unknown_warn_rate", formatRuntimeSettingValue(settings.ProviderUnknownWarnRate))
	values.Set("provider_unknown_critical_rate", formatRuntimeSettingValue(settings.ProviderUnknownCriticalRate))
	values.Set("policy_canary_window_minutes", formatRuntimeSettingValue(float64(settings.PolicyCanaryWindowMinutes)))
	values.Set("policy_canary_required_health_windows", formatRuntimeSettingValue(float64(settings.PolicyCanaryRequiredHealthWindows)))
	values.Set("policy_canary_unknown_regression_threshold", formatRuntimeSettingValue(settings.PolicyCanaryUnknownRegressionThreshold))
	values.Set("policy_canary_tempfail_recovery_drop_threshold", formatRuntimeSettingValue(settings.PolicyCanaryTempfailRecoveryDropThreshold))
	values.Set("policy_canary_policy_block_spike_threshold", formatRuntimeSettingValue(settings.PolicyCanaryPolicyBlockSpikeThreshold))
	values.Set("policy_canary_min_provider_workers", formatRuntimeSettingValue(float64(settings.PolicyCanaryMinProviderWorkers)))
	values.Set("ui_overview_live_interval_seconds", formatRuntimeSettingValue(float64(settings.UIOverviewLiveIntervalSecond)))
	values.Set("ui_workers_refresh_seconds", formatRuntimeSettingValue(float64(settings.UIWorkersRefreshSecond)))
	values.Set("ui_pools_refresh_seconds", formatRuntimeSettingValue(float64(settings.UIPoolsRefreshSecond)))
	values.Set("ui_alerts_refresh_seconds", formatRuntimeSettingValue(float64(settings.UIAlertsRefreshSecond)))

	return values
}
