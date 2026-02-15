package main

import (
	"fmt"
	"strings"
)

type SettingHelpTip struct {
	Key                   string
	Title                 string
	What                  string
	Why                   string
	StandardValue         string
	IfIncreased           string
	IfDecreased           string
	Monitor               string
	DocsURL               string
	ChangeKind            string
	RecommendedLabel      string
	RecommendedValue      string
	RecommendedTone       string
	RiskLevel             string
	ChangeUp              string
	ChangeDown            string
	HasRecommendedRange   bool
	RecommendedRangeMin   float64
	RecommendedRangeMax   float64
	RecommendedRangeLabel string
	HasCautionRange       bool
	CautionRangeMin       float64
	CautionRangeMax       float64
	CautionRangeLabel     string
}

type numericRangeLimit struct {
	Min    float64
	Max    float64
	HasMax bool
}

func runtimeSettingHelpKeys() []string {
	return []string{
		"alerts_enabled",
		"auto_actions_enabled",
		"autoscale_enabled",
		"provider_policy_engine_enabled",
		"adaptive_retry_enabled",
		"provider_autoprotect_enabled",
		"policy_canary_autopilot_enabled",
		"alert_error_rate_threshold",
		"alert_heartbeat_grace_seconds",
		"alert_cooldown_seconds",
		"alert_check_interval_seconds",
		"stale_worker_ttl_seconds",
		"stuck_desired_grace_seconds",
		"quarantine_error_rate_threshold",
		"provider_tempfail_warn_rate",
		"provider_tempfail_critical_rate",
		"provider_reject_warn_rate",
		"provider_reject_critical_rate",
		"provider_unknown_warn_rate",
		"provider_unknown_critical_rate",
		"autoscale_interval_seconds",
		"autoscale_cooldown_seconds",
		"autoscale_canary_percent",
		"autoscale_min_desired",
		"autoscale_max_desired",
		"policy_canary_window_minutes",
		"policy_canary_required_health_windows",
		"policy_canary_min_provider_workers",
		"policy_canary_unknown_regression_threshold",
		"policy_canary_tempfail_recovery_drop_threshold",
		"policy_canary_policy_block_spike_threshold",
		"ui_overview_live_interval_seconds",
		"ui_workers_refresh_seconds",
		"ui_pools_refresh_seconds",
		"ui_alerts_refresh_seconds",
	}
}

func runtimeSettingNumericBounds() map[string]numericRangeLimit {
	return map[string]numericRangeLimit{
		"alert_error_rate_threshold":                     {Min: 0, Max: 1000000, HasMax: true},
		"alert_heartbeat_grace_seconds":                  {Min: 1, Max: 86400, HasMax: true},
		"alert_cooldown_seconds":                         {Min: 1, Max: 86400, HasMax: true},
		"alert_check_interval_seconds":                   {Min: 5, Max: 600, HasMax: true},
		"stale_worker_ttl_seconds":                       {Min: 60, Max: 2592000, HasMax: true},
		"stuck_desired_grace_seconds":                    {Min: 30, Max: 86400, HasMax: true},
		"quarantine_error_rate_threshold":                {Min: 0, Max: 1000000, HasMax: true},
		"provider_tempfail_warn_rate":                    {Min: 0, Max: 1, HasMax: true},
		"provider_tempfail_critical_rate":                {Min: 0, Max: 1, HasMax: true},
		"provider_reject_warn_rate":                      {Min: 0, Max: 1, HasMax: true},
		"provider_reject_critical_rate":                  {Min: 0, Max: 1, HasMax: true},
		"provider_unknown_warn_rate":                     {Min: 0, Max: 1, HasMax: true},
		"provider_unknown_critical_rate":                 {Min: 0, Max: 1, HasMax: true},
		"autoscale_interval_seconds":                     {Min: 5, Max: 600, HasMax: true},
		"autoscale_cooldown_seconds":                     {Min: 10, Max: 86400, HasMax: true},
		"autoscale_canary_percent":                       {Min: 1, Max: 100, HasMax: true},
		"autoscale_min_desired":                          {Min: 0, Max: 1000, HasMax: true},
		"autoscale_max_desired":                          {Min: 0, Max: 1000, HasMax: true},
		"policy_canary_window_minutes":                   {Min: 1, Max: 240, HasMax: true},
		"policy_canary_required_health_windows":          {Min: 1, Max: 20, HasMax: true},
		"policy_canary_min_provider_workers":             {Min: 1, Max: 1000, HasMax: true},
		"policy_canary_unknown_regression_threshold":     {Min: 0, Max: 1, HasMax: true},
		"policy_canary_tempfail_recovery_drop_threshold": {Min: 0, Max: 1, HasMax: true},
		"policy_canary_policy_block_spike_threshold":     {Min: 0, Max: 1, HasMax: true},
		"ui_overview_live_interval_seconds":              {Min: 2, Max: 60, HasMax: true},
		"ui_workers_refresh_seconds":                     {Min: 2, Max: 120, HasMax: true},
		"ui_pools_refresh_seconds":                       {Min: 2, Max: 120, HasMax: true},
		"ui_alerts_refresh_seconds":                      {Min: 5, Max: 300, HasMax: true},
	}
}

func runtimeSettingsNumericValues(settings RuntimeSettings) map[string]float64 {
	return map[string]float64{
		"alert_error_rate_threshold":                     settings.AlertErrorRateThreshold,
		"alert_heartbeat_grace_seconds":                  float64(settings.AlertHeartbeatGraceSecond),
		"alert_cooldown_seconds":                         float64(settings.AlertCooldownSecond),
		"alert_check_interval_seconds":                   float64(settings.AlertCheckIntervalSecond),
		"stale_worker_ttl_seconds":                       float64(settings.StaleWorkerTTLSecond),
		"stuck_desired_grace_seconds":                    float64(settings.StuckDesiredGraceSecond),
		"quarantine_error_rate_threshold":                settings.QuarantineErrorRateThreshold,
		"provider_tempfail_warn_rate":                    settings.ProviderTempfailWarnRate,
		"provider_tempfail_critical_rate":                settings.ProviderTempfailCriticalRate,
		"provider_reject_warn_rate":                      settings.ProviderRejectWarnRate,
		"provider_reject_critical_rate":                  settings.ProviderRejectCriticalRate,
		"provider_unknown_warn_rate":                     settings.ProviderUnknownWarnRate,
		"provider_unknown_critical_rate":                 settings.ProviderUnknownCriticalRate,
		"autoscale_interval_seconds":                     float64(settings.AutoscaleIntervalSecond),
		"autoscale_cooldown_seconds":                     float64(settings.AutoscaleCooldownSecond),
		"autoscale_canary_percent":                       float64(settings.AutoscaleCanaryPercent),
		"autoscale_min_desired":                          float64(settings.AutoscaleMinDesired),
		"autoscale_max_desired":                          float64(settings.AutoscaleMaxDesired),
		"policy_canary_window_minutes":                   float64(settings.PolicyCanaryWindowMinutes),
		"policy_canary_required_health_windows":          float64(settings.PolicyCanaryRequiredHealthWindows),
		"policy_canary_min_provider_workers":             float64(settings.PolicyCanaryMinProviderWorkers),
		"policy_canary_unknown_regression_threshold":     settings.PolicyCanaryUnknownRegressionThreshold,
		"policy_canary_tempfail_recovery_drop_threshold": settings.PolicyCanaryTempfailRecoveryDropThreshold,
		"policy_canary_policy_block_spike_threshold":     settings.PolicyCanaryPolicyBlockSpikeThreshold,
		"ui_overview_live_interval_seconds":              float64(settings.UIOverviewLiveIntervalSecond),
		"ui_workers_refresh_seconds":                     float64(settings.UIWorkersRefreshSecond),
		"ui_pools_refresh_seconds":                       float64(settings.UIPoolsRefreshSecond),
		"ui_alerts_refresh_seconds":                      float64(settings.UIAlertsRefreshSecond),
	}
}

func firstUnsafeRuntimeSetting(defaults RuntimeSettings, settings RuntimeSettings) (string, SettingHelpTip, float64, bool) {
	help := buildRuntimeSettingsHelp(defaults, "")
	values := runtimeSettingsNumericValues(settings)

	for _, key := range runtimeSettingHelpKeys() {
		value, hasValue := values[key]
		if !hasValue {
			continue
		}
		tip, ok := help[key]
		if !ok || !tip.HasCautionRange {
			continue
		}
		if value < tip.CautionRangeMin || value > tip.CautionRangeMax {
			return key, tip, value, true
		}
	}

	return "", SettingHelpTip{}, 0, false
}

func buildRuntimeSettingsHelp(defaults RuntimeSettings, docsBaseURL string) map[string]SettingHelpTip {
	defaults = normalizeRuntimeSettings(defaults, defaults)
	guideURL := runtimeSettingsGuideURL(docsBaseURL)

	tips := map[string]SettingHelpTip{
		"alerts_enabled": {
			Key:           "alerts_enabled",
			Title:         "Enable alerts",
			What:          "Turns alert checks and notifications on/off.",
			Why:           "Without alerts, incidents can stay hidden.",
			StandardValue: boolStandard(defaults.AlertsEnabled),
			IfIncreased:   "Enabled increases incident visibility.",
			IfDecreased:   "Disabled suppresses alert detection and notifications.",
			Monitor:       "Active incident count and alert delivery logs.",
			DocsURL:       guideURL,
		},
		"auto_actions_enabled": {
			Key:           "auto_actions_enabled",
			Title:         "Enable auto actions",
			What:          "Allows automatic drain/quarantine actions from policy gates.",
			Why:           "Reduces manual response time during provider degradation.",
			StandardValue: boolStandard(defaults.AutoActionsEnabled),
			IfIncreased:   "Enabled allows faster safety response.",
			IfDecreased:   "Disabled requires manual mitigation.",
			Monitor:       "Auto action audit trail and recovery time.",
			DocsURL:       guideURL,
		},
		"autoscale_enabled": {
			Key:           "autoscale_enabled",
			Title:         "Enable autoscaling",
			What:          "Allows pool desired counts to adjust by load.",
			Why:           "Helps absorb spikes without permanent over-provisioning.",
			StandardValue: boolStandard(defaults.AutoscaleEnabled),
			IfIncreased:   "Enabled improves burst handling.",
			IfDecreased:   "Disabled keeps fixed pool sizes.",
			Monitor:       "Queue age, backlog, desired-vs-online drift.",
			DocsURL:       guideURL,
		},
		"provider_policy_engine_enabled": {
			Key:           "provider_policy_engine_enabled",
			Title:         "Enable provider policy engine",
			What:          "Applies provider-specific SMTP policy packs.",
			Why:           "Improves classification consistency by provider.",
			StandardValue: boolStandard(defaults.ProviderPolicyEngineEnabled),
			IfIncreased:   "Enabled uses provider-aware logic.",
			IfDecreased:   "Disabled falls back to generic behavior.",
			Monitor:       "Unknown and tempfail trends by provider.",
			DocsURL:       guideURL,
		},
		"adaptive_retry_enabled": {
			Key:           "adaptive_retry_enabled",
			Title:         "Enable adaptive retry",
			What:          "Uses provider-aware retry pacing and cooldown.",
			Why:           "Improves tempfail recovery while reducing retry waste.",
			StandardValue: boolStandard(defaults.AdaptiveRetryEnabled),
			IfIncreased:   "Enabled can recover more transient failures.",
			IfDecreased:   "Disabled uses static retry behavior.",
			Monitor:       "Tempfail recovery rate and retry waste.",
			DocsURL:       guideURL,
		},
		"provider_autoprotect_enabled": {
			Key:           "provider_autoprotect_enabled",
			Title:         "Enable provider auto protect",
			What:          "Auto-switches provider mode (normal/cautious/drain) on health degradation.",
			Why:           "Protects probe reputation during rejection spikes.",
			StandardValue: boolStandard(defaults.ProviderAutoprotectEnabled),
			IfIncreased:   "Enabled applies protective mode shifts automatically.",
			IfDecreased:   "Disabled requires manual mode actions.",
			Monitor:       "Provider mode transitions and rejection rates.",
			DocsURL:       guideURL,
		},
		"policy_canary_autopilot_enabled": {
			Key:           "policy_canary_autopilot_enabled",
			Title:         "Enable canary autopilot",
			What:          "Automates canary step-up and rollback decisions.",
			Why:           "Reduces rollout risk and manual lag.",
			StandardValue: boolStandard(defaults.PolicyCanaryAutopilotEnabled),
			IfIncreased:   "Enabled automates rollout progression.",
			IfDecreased:   "Disabled keeps rollout manual-only.",
			Monitor:       "Canary window outcomes and rollback events.",
			DocsURL:       guideURL,
		},
		"alert_error_rate_threshold": {
			Key:           "alert_error_rate_threshold",
			Title:         "Error threshold (errors/min)",
			What:          "Triggers incidents when worker error rate exceeds this value.",
			Why:           "Controls sensitivity of reliability alerts.",
			StandardValue: fmt.Sprintf("%.2f errors/min", defaults.AlertErrorRateThreshold),
			IfIncreased:   "Higher value reduces alert noise but may delay detection.",
			IfDecreased:   "Lower value catches problems earlier but may increase noise.",
			Monitor:       "Alert frequency and true/false positive ratio.",
			DocsURL:       guideURL,
		},
		"alert_heartbeat_grace_seconds": {
			Key:           "alert_heartbeat_grace_seconds",
			Title:         "Heartbeat grace (seconds)",
			What:          "Allowed heartbeat gap before worker is treated as unhealthy.",
			Why:           "Balances outage detection against transient jitter.",
			StandardValue: intStandard(defaults.AlertHeartbeatGraceSecond, "seconds"),
			IfIncreased:   "Higher grace reduces false offline alerts.",
			IfDecreased:   "Lower grace detects outages faster.",
			Monitor:       "Offline incidents and heartbeat jitter.",
			DocsURL:       guideURL,
		},
		"alert_cooldown_seconds": {
			Key:           "alert_cooldown_seconds",
			Title:         "Alert cooldown (seconds)",
			What:          "Deduplication window for repeating alerts.",
			Why:           "Prevents alert floods for the same incident.",
			StandardValue: intStandard(defaults.AlertCooldownSecond, "seconds"),
			IfIncreased:   "Higher cooldown reduces repeated notifications.",
			IfDecreased:   "Lower cooldown increases repeated alerts.",
			Monitor:       "Alert volume per incident key.",
			DocsURL:       guideURL,
		},
		"alert_check_interval_seconds": {
			Key:           "alert_check_interval_seconds",
			Title:         "Alert check interval (seconds)",
			What:          "How often health checks run.",
			Why:           "Controls detection latency vs control-plane load.",
			StandardValue: intStandard(defaults.AlertCheckIntervalSecond, "seconds"),
			IfIncreased:   "Higher interval lowers load but delays detection.",
			IfDecreased:   "Lower interval detects faster but increases load.",
			Monitor:       "Detection delay, Redis ops/sec, CPU.",
			DocsURL:       guideURL,
		},
		"stale_worker_ttl_seconds": {
			Key:           "stale_worker_ttl_seconds",
			Title:         "Stale worker TTL (seconds)",
			What:          "Time before inactive workers are cleaned from known-worker state.",
			Why:           "Prevents stale routing and stale incident context.",
			StandardValue: intStandard(defaults.StaleWorkerTTLSecond, "seconds"),
			IfIncreased:   "Higher TTL keeps stale workers longer.",
			IfDecreased:   "Lower TTL removes stale workers faster.",
			Monitor:       "Stale cleanup events and worker re-registration.",
			DocsURL:       guideURL,
		},
		"stuck_desired_grace_seconds": {
			Key:           "stuck_desired_grace_seconds",
			Title:         "Stuck desired grace (seconds)",
			What:          "Grace period before desired-state mismatch is treated as stuck.",
			Why:           "Detects failed pause/drain transitions.",
			StandardValue: intStandard(defaults.StuckDesiredGraceSecond, "seconds"),
			IfIncreased:   "Higher grace reduces false stuck incidents.",
			IfDecreased:   "Lower grace detects stuck transitions faster.",
			Monitor:       "worker_stuck_desired incidents.",
			DocsURL:       guideURL,
		},
		"quarantine_error_rate_threshold": {
			Key:           "quarantine_error_rate_threshold",
			Title:         "Quarantine threshold",
			What:          "Error rate threshold for automatic worker quarantine.",
			Why:           "Stops unstable workers from damaging output quality.",
			StandardValue: fmt.Sprintf("%.2f errors/min", defaults.QuarantineErrorRateThreshold),
			IfIncreased:   "Higher threshold quarantines less often.",
			IfDecreased:   "Lower threshold quarantines more aggressively.",
			Monitor:       "Quarantine count and available capacity.",
			DocsURL:       guideURL,
		},
		"provider_tempfail_warn_rate": {
			Key:           "provider_tempfail_warn_rate",
			Title:         "Tempfail warn rate",
			What:          "Provider tempfail ratio that enters warning state.",
			Why:           "Early indicator of provider instability.",
			StandardValue: rateStandard(defaults.ProviderTempfailWarnRate),
			IfIncreased:   "Higher threshold warns later.",
			IfDecreased:   "Lower threshold warns earlier.",
			Monitor:       "Provider tempfail trend.",
			DocsURL:       guideURL,
		},
		"provider_tempfail_critical_rate": {
			Key:           "provider_tempfail_critical_rate",
			Title:         "Tempfail critical rate",
			What:          "Provider tempfail ratio that enters critical state.",
			Why:           "Drives stronger mitigation decisions.",
			StandardValue: rateStandard(defaults.ProviderTempfailCriticalRate),
			IfIncreased:   "Higher threshold delays critical state.",
			IfDecreased:   "Lower threshold escalates sooner.",
			Monitor:       "Critical incidents and mode shifts.",
			DocsURL:       guideURL,
		},
		"provider_reject_warn_rate": {
			Key:           "provider_reject_warn_rate",
			Title:         "Reject warn rate",
			What:          "Provider reject ratio that enters warning state.",
			Why:           "Tracks provider rejection pressure early.",
			StandardValue: rateStandard(defaults.ProviderRejectWarnRate),
			IfIncreased:   "Higher threshold warns later.",
			IfDecreased:   "Lower threshold warns earlier.",
			Monitor:       "Reject trend by provider.",
			DocsURL:       guideURL,
		},
		"provider_reject_critical_rate": {
			Key:           "provider_reject_critical_rate",
			Title:         "Reject critical rate",
			What:          "Provider reject ratio that enters critical state.",
			Why:           "Protects sending posture before hard blocking spreads.",
			StandardValue: rateStandard(defaults.ProviderRejectCriticalRate),
			IfIncreased:   "Higher threshold delays critical mitigation.",
			IfDecreased:   "Lower threshold escalates to critical sooner.",
			Monitor:       "Critical reject incidents and drain actions.",
			DocsURL:       guideURL,
		},
		"provider_unknown_warn_rate": {
			Key:           "provider_unknown_warn_rate",
			Title:         "Unknown warn rate",
			What:          "Provider unknown ratio that enters warning state.",
			Why:           "Detects early drift in classification confidence.",
			StandardValue: rateStandard(defaults.ProviderUnknownWarnRate),
			IfIncreased:   "Higher threshold warns later.",
			IfDecreased:   "Lower threshold warns earlier.",
			Monitor:       "Unknown rate by provider.",
			DocsURL:       guideURL,
		},
		"provider_unknown_critical_rate": {
			Key:           "provider_unknown_critical_rate",
			Title:         "Unknown critical rate",
			What:          "Provider unknown ratio that enters critical state.",
			Why:           "Protects quality when uncertainty spikes.",
			StandardValue: rateStandard(defaults.ProviderUnknownCriticalRate),
			IfIncreased:   "Higher threshold delays critical unknown alarms.",
			IfDecreased:   "Lower threshold escalates unknown spikes faster.",
			Monitor:       "Unknown critical incidents and policy rollbacks.",
			DocsURL:       guideURL,
		},
		"autoscale_interval_seconds": {
			Key:           "autoscale_interval_seconds",
			Title:         "Autoscale interval (seconds)",
			What:          "How often autoscale evaluates pool demand.",
			Why:           "Controls responsiveness vs stability.",
			StandardValue: intStandard(defaults.AutoscaleIntervalSecond, "seconds"),
			IfIncreased:   "Higher interval reduces churn but reacts slower.",
			IfDecreased:   "Lower interval reacts faster but may flap.",
			Monitor:       "Desired changes/hour and backlog age.",
			DocsURL:       guideURL,
		},
		"autoscale_cooldown_seconds": {
			Key:           "autoscale_cooldown_seconds",
			Title:         "Autoscale cooldown (seconds)",
			What:          "Minimum time between autoscale actions.",
			Why:           "Prevents repeated oscillation.",
			StandardValue: intStandard(defaults.AutoscaleCooldownSecond, "seconds"),
			IfIncreased:   "Higher cooldown improves stability, slows correction.",
			IfDecreased:   "Lower cooldown speeds correction, can increase flapping.",
			Monitor:       "Scale action frequency and pool oscillation.",
			DocsURL:       guideURL,
		},
		"autoscale_canary_percent": {
			Key:           "autoscale_canary_percent",
			Title:         "Autoscale canary (%)",
			What:          "Share of pools where autoscale changes are allowed.",
			Why:           "Limits blast radius while tuning autoscale.",
			StandardValue: percentStandard(defaults.AutoscaleCanaryPercent),
			IfIncreased:   "Higher percent applies autoscale broadly.",
			IfDecreased:   "Lower percent constrains autoscale scope.",
			Monitor:       "Pools impacted and backlog skew.",
			DocsURL:       guideURL,
		},
		"autoscale_min_desired": {
			Key:           "autoscale_min_desired",
			Title:         "Autoscale min desired",
			What:          "Lower bound for desired workers per pool.",
			Why:           "Keeps baseline capacity available.",
			StandardValue: intStandard(defaults.AutoscaleMinDesired, "workers"),
			IfIncreased:   "Higher minimum improves readiness but raises idle cost.",
			IfDecreased:   "Lower minimum reduces cost but can add cold-start delay.",
			Monitor:       "Low-traffic queue latency and worker idle time.",
			DocsURL:       guideURL,
		},
		"autoscale_max_desired": {
			Key:           "autoscale_max_desired",
			Title:         "Autoscale max desired",
			What:          "Upper bound for desired workers per pool.",
			Why:           "Caps burst capacity and infrastructure usage.",
			StandardValue: intStandard(defaults.AutoscaleMaxDesired, "workers"),
			IfIncreased:   "Higher maximum drains spikes faster with more cost.",
			IfDecreased:   "Lower maximum limits burst handling.",
			Monitor:       "Spike backlog drain time and resource use.",
			DocsURL:       guideURL,
		},
		"policy_canary_window_minutes": {
			Key:           "policy_canary_window_minutes",
			Title:         "Canary window (minutes)",
			What:          "Evaluation window length for autopilot health checks.",
			Why:           "Longer windows reduce noise in decisions.",
			StandardValue: intStandard(defaults.PolicyCanaryWindowMinutes, "minutes"),
			IfIncreased:   "Higher window smooths data, slows decisions.",
			IfDecreased:   "Lower window speeds decisions, increases noise sensitivity.",
			Monitor:       "Canary decision stability and reversals.",
			DocsURL:       guideURL,
		},
		"policy_canary_required_health_windows": {
			Key:           "policy_canary_required_health_windows",
			Title:         "Required healthy windows",
			What:          "Consecutive healthy windows required before step-up.",
			Why:           "Protects against promoting on short-lived good samples.",
			StandardValue: intStandard(defaults.PolicyCanaryRequiredHealthWindows, "windows"),
			IfIncreased:   "Higher count makes promotion safer but slower.",
			IfDecreased:   "Lower count speeds rollout but raises regression risk.",
			Monitor:       "Promotion speed vs post-promotion regressions.",
			DocsURL:       guideURL,
		},
		"policy_canary_min_provider_workers": {
			Key:           "policy_canary_min_provider_workers",
			Title:         "Minimum provider workers",
			What:          "Minimum sample size gate for provider canary decisions.",
			Why:           "Avoids decisions based on tiny traffic samples.",
			StandardValue: intStandard(defaults.PolicyCanaryMinProviderWorkers, "workers"),
			IfIncreased:   "Higher value requires stronger sample volume.",
			IfDecreased:   "Lower value allows decisions on smaller samples.",
			Monitor:       "Canary holds due to insufficient sample.",
			DocsURL:       guideURL,
		},
		"policy_canary_unknown_regression_threshold": {
			Key:           "policy_canary_unknown_regression_threshold",
			Title:         "Unknown regression threshold",
			What:          "Maximum allowed unknown-rate increase vs baseline.",
			Why:           "Prevents canary progression when uncertainty rises.",
			StandardValue: rateStandard(defaults.PolicyCanaryUnknownRegressionThreshold),
			IfIncreased:   "Higher threshold is more tolerant of unknown increases.",
			IfDecreased:   "Lower threshold is stricter and rolls back sooner.",
			Monitor:       "Unknown delta during canary windows.",
			DocsURL:       guideURL,
		},
		"policy_canary_tempfail_recovery_drop_threshold": {
			Key:           "policy_canary_tempfail_recovery_drop_threshold",
			Title:         "Tempfail recovery drop threshold",
			What:          "Maximum allowed drop in tempfail recovery vs baseline.",
			Why:           "Protects retry quality during canary rollout.",
			StandardValue: rateStandard(defaults.PolicyCanaryTempfailRecoveryDropThreshold),
			IfIncreased:   "Higher threshold tolerates larger recovery drops.",
			IfDecreased:   "Lower threshold enforces stricter quality guard.",
			Monitor:       "Tempfail recovery deltas per window.",
			DocsURL:       guideURL,
		},
		"policy_canary_policy_block_spike_threshold": {
			Key:           "policy_canary_policy_block_spike_threshold",
			Title:         "Policy-block spike threshold",
			What:          "Maximum allowed rise in policy-block rate vs baseline.",
			Why:           "Detects potential provider hostility before full rollout.",
			StandardValue: rateStandard(defaults.PolicyCanaryPolicyBlockSpikeThreshold),
			IfIncreased:   "Higher threshold is more tolerant of policy-block spikes.",
			IfDecreased:   "Lower threshold rolls back faster on spikes.",
			Monitor:       "Policy-block delta and provider mode shifts.",
			DocsURL:       guideURL,
		},
		"ui_overview_live_interval_seconds": {
			Key:           "ui_overview_live_interval_seconds",
			Title:         "Overview stream interval (seconds)",
			What:          "Refresh cadence for overview live payload updates.",
			Why:           "Balances dashboard freshness and browser/network load.",
			StandardValue: intStandard(defaults.UIOverviewLiveIntervalSecond, "seconds"),
			IfIncreased:   "Higher interval lowers UI load but updates less frequently.",
			IfDecreased:   "Lower interval increases freshness and load.",
			Monitor:       "Browser CPU/network and operator responsiveness.",
			DocsURL:       guideURL,
		},
		"ui_workers_refresh_seconds": {
			Key:           "ui_workers_refresh_seconds",
			Title:         "Workers refresh interval (seconds)",
			What:          "Polling interval for workers page data.",
			Why:           "Controls worker-state freshness and API load.",
			StandardValue: intStandard(defaults.UIWorkersRefreshSecond, "seconds"),
			IfIncreased:   "Higher interval reduces load but can feel stale.",
			IfDecreased:   "Lower interval improves freshness with more API load.",
			Monitor:       "Workers page request volume and perceived staleness.",
			DocsURL:       guideURL,
		},
		"ui_pools_refresh_seconds": {
			Key:           "ui_pools_refresh_seconds",
			Title:         "Pools refresh interval (seconds)",
			What:          "Polling interval for pool capacity and desired state.",
			Why:           "Keeps scaling visibility current without over-polling.",
			StandardValue: intStandard(defaults.UIPoolsRefreshSecond, "seconds"),
			IfIncreased:   "Higher interval reduces load but delays pool updates.",
			IfDecreased:   "Lower interval increases real-time visibility and load.",
			Monitor:       "Pools page API calls and update lag.",
			DocsURL:       guideURL,
		},
		"ui_alerts_refresh_seconds": {
			Key:           "ui_alerts_refresh_seconds",
			Title:         "Alerts refresh interval (seconds)",
			What:          "Polling interval for alerts/incidents timeline.",
			Why:           "Controls freshness for incident triage.",
			StandardValue: intStandard(defaults.UIAlertsRefreshSecond, "seconds"),
			IfIncreased:   "Higher interval reduces load but delays alert visibility.",
			IfDecreased:   "Lower interval surfaces alerts faster with more load.",
			Monitor:       "Alert page request volume and incident visibility lag.",
			DocsURL:       guideURL,
		},
		"provider_policy_controls": {
			Key:           "provider_policy_controls",
			Title:         "Provider Policy Controls",
			What:          "Manual provider mode changes and policy reload actions.",
			Why:           "Gives operators immediate control during provider incidents.",
			StandardValue: "Use manual override only during active incidents.",
			IfIncreased:   "More manual overrides can stabilize incidents quickly.",
			IfDecreased:   "Fewer manual actions rely on automatic controls.",
			Monitor:       "Provider mode timeline and incident recovery speed.",
			DocsURL:       guideURL,
		},
		"policy_rollout_controls": {
			Key:           "policy_rollout_controls",
			Title:         "Policy Rollout Controls",
			What:          "Validate, promote, and rollback policy versions.",
			Why:           "Ensures rollout actions remain auditable and safe.",
			StandardValue: "Validate first, then promote with canary.",
			IfIncreased:   "More frequent rollouts improve iteration speed.",
			IfDecreased:   "Slower rollouts reduce change risk.",
			Monitor:       "Rollout history, validation status, and rollback events.",
			DocsURL:       guideURL,
		},
	}

	toggleKeys := map[string]bool{
		"alerts_enabled":                  true,
		"auto_actions_enabled":            true,
		"autoscale_enabled":               true,
		"provider_policy_engine_enabled":  true,
		"adaptive_retry_enabled":          true,
		"provider_autoprotect_enabled":    true,
		"policy_canary_autopilot_enabled": true,
	}

	actionKeys := map[string]bool{
		"provider_policy_controls": true,
		"policy_rollout_controls":  true,
	}
	numericBounds := runtimeSettingNumericBounds()

	for key, tip := range tips {
		if tip.RecommendedLabel == "" {
			tip.RecommendedLabel = "Recommended"
		}
		if tip.RecommendedValue == "" {
			tip.RecommendedValue = tip.StandardValue
		}
		if tip.ChangeUp == "" {
			tip.ChangeUp = tip.IfIncreased
		}
		if tip.ChangeDown == "" {
			tip.ChangeDown = tip.IfDecreased
		}
		if toggleKeys[key] {
			tip.ChangeKind = "toggle"
			if changeUp, changeDown, ok := runtimeHelpToggleChangeMessages(key); ok {
				tip.ChangeUp = changeUp
				tip.ChangeDown = changeDown
			}
			if strings.EqualFold(tip.RecommendedValue, "Enabled") {
				tip.RecommendedTone = "success"
			} else {
				tip.RecommendedTone = "neutral"
			}
		} else if actionKeys[key] {
			tip.ChangeKind = "action"
			tip.RecommendedTone = "warning"
		} else {
			tip.ChangeKind = "numeric"
			tip.RecommendedTone = "neutral"
		}
		tip.RiskLevel = runtimeHelpRiskLevel(key)
		if min, max, label, ok := runtimeHelpRecommendedRange(key, defaults); ok {
			tip.HasRecommendedRange = true
			tip.RecommendedRangeMin = min
			tip.RecommendedRangeMax = max
			tip.RecommendedRangeLabel = label
			if tip.ChangeKind == "numeric" {
				limits, hasLimits := numericBounds[key]
				if hasLimits {
					if cMin, cMax, cLabel, cautionOk := deriveCautionRange(min, max, limits); cautionOk {
						tip.HasCautionRange = true
						tip.CautionRangeMin = cMin
						tip.CautionRangeMax = cMax
						tip.CautionRangeLabel = cLabel
					}
				}
			}
		}
		tips[key] = tip
	}

	return tips
}

func deriveCautionRange(safeMin float64, safeMax float64, limits numericRangeLimit) (float64, float64, string, bool) {
	if safeMax < safeMin {
		return 0, 0, "", false
	}

	cautionMin := safeMin * 0.70
	cautionMax := safeMax * 1.30

	if almostEqualFloat(safeMin, safeMax) {
		delta := maxFloatSetting(1, safeMin*0.30)
		cautionMin = safeMin - delta
		cautionMax = safeMax + delta
	}

	if cautionMin < limits.Min {
		cautionMin = limits.Min
	}
	if limits.HasMax && cautionMax > limits.Max {
		cautionMax = limits.Max
	}
	if cautionMax < cautionMin {
		cautionMax = cautionMin
	}

	return cautionMin, cautionMax, formatRangeLabel(cautionMin, cautionMax), true
}

func formatRangeLabel(min float64, max float64) string {
	if almostEqualFloat(min, float64(int(min))) && almostEqualFloat(max, float64(int(max))) {
		return fmt.Sprintf("%d-%d", int(min), int(max))
	}

	return fmt.Sprintf("%.2f-%.2f", min, max)
}

func almostEqualFloat(a float64, b float64) bool {
	diff := a - b
	if diff < 0 {
		diff = -diff
	}

	return diff < 0.0001
}

func maxFloatSetting(a float64, b float64) float64 {
	if a >= b {
		return a
	}
	return b
}

func runtimeHelpToggleChangeMessages(key string) (string, string, bool) {
	switch key {
	case "alerts_enabled":
		return "Incidents are detected and notifications are sent.", "Incidents are not alerted automatically; use manual checks.", true
	case "auto_actions_enabled":
		return "Automatic safety actions can quarantine or drain unhealthy workers.", "Ops team must run mitigation actions manually.", true
	case "autoscale_enabled":
		return "Pool desired counts adjust to load within configured guardrails.", "Pool sizes stay fixed until changed manually.", true
	case "provider_policy_engine_enabled":
		return "Provider-specific SMTP rules are applied for decisions and pacing.", "Generic fallback policy is used for all providers.", true
	case "adaptive_retry_enabled":
		return "Retry windows adapt by provider behavior and tempfail patterns.", "Static retry behavior is used.", true
	case "provider_autoprotect_enabled":
		return "Provider mode can shift automatically during degradation.", "Provider mode changes require manual operator action.", true
	case "policy_canary_autopilot_enabled":
		return "Canary rollout progression and rollback are evaluated automatically.", "Canary rollout remains manual.", true
	default:
		return "", "", false
	}
}

func runtimeHelpRiskLevel(key string) string {
	switch key {
	case "alerts_enabled", "ui_overview_live_interval_seconds", "ui_workers_refresh_seconds", "ui_pools_refresh_seconds", "ui_alerts_refresh_seconds":
		return "low"
	case "provider_policy_controls", "policy_rollout_controls", "auto_actions_enabled", "autoscale_enabled", "provider_policy_engine_enabled", "adaptive_retry_enabled", "provider_autoprotect_enabled", "policy_canary_autopilot_enabled":
		return "high"
	default:
		return "medium"
	}
}

func runtimeHelpRecommendedRange(key string, defaults RuntimeSettings) (float64, float64, string, bool) {
	switch key {
	case "alert_error_rate_threshold":
		return expandRangeFloat(defaults.AlertErrorRateThreshold, 0.75, 1.25, 0, 1000000, 2, "")
	case "alert_heartbeat_grace_seconds":
		return expandRangeInt(defaults.AlertHeartbeatGraceSecond, 0.7, 1.4, 1, 86400, " sec")
	case "alert_cooldown_seconds":
		return expandRangeInt(defaults.AlertCooldownSecond, 0.7, 1.4, 1, 86400, " sec")
	case "alert_check_interval_seconds":
		return expandRangeInt(defaults.AlertCheckIntervalSecond, 0.7, 1.4, 5, 300, " sec")
	case "stale_worker_ttl_seconds":
		return expandRangeInt(defaults.StaleWorkerTTLSecond, 0.7, 1.3, 60, 172800, " sec")
	case "stuck_desired_grace_seconds":
		return expandRangeInt(defaults.StuckDesiredGraceSecond, 0.7, 1.4, 30, 7200, " sec")
	case "quarantine_error_rate_threshold":
		return expandRangeFloat(defaults.QuarantineErrorRateThreshold, 0.8, 1.2, 0, 1000000, 2, "")
	case "provider_tempfail_warn_rate":
		return expandRangeFloat(defaults.ProviderTempfailWarnRate, 0.85, 1.15, 0, 1, 2, "")
	case "provider_tempfail_critical_rate":
		return expandRangeFloat(defaults.ProviderTempfailCriticalRate, 0.85, 1.15, 0, 1, 2, "")
	case "provider_reject_warn_rate":
		return expandRangeFloat(defaults.ProviderRejectWarnRate, 0.85, 1.15, 0, 1, 2, "")
	case "provider_reject_critical_rate":
		return expandRangeFloat(defaults.ProviderRejectCriticalRate, 0.85, 1.15, 0, 1, 2, "")
	case "provider_unknown_warn_rate":
		return expandRangeFloat(defaults.ProviderUnknownWarnRate, 0.85, 1.15, 0, 1, 2, "")
	case "provider_unknown_critical_rate":
		return expandRangeFloat(defaults.ProviderUnknownCriticalRate, 0.85, 1.15, 0, 1, 2, "")
	case "autoscale_interval_seconds":
		return expandRangeInt(defaults.AutoscaleIntervalSecond, 0.8, 1.25, 5, 600, " sec")
	case "autoscale_cooldown_seconds":
		return expandRangeInt(defaults.AutoscaleCooldownSecond, 0.8, 1.25, 10, 3600, " sec")
	case "autoscale_canary_percent":
		return expandRangeInt(defaults.AutoscaleCanaryPercent, 0.5, 1.0, 1, 100, "%")
	case "autoscale_min_desired":
		min := defaults.AutoscaleMinDesired - 1
		if min < 0 {
			min = 0
		}
		max := defaults.AutoscaleMinDesired + 2
		return float64(min), float64(max), fmt.Sprintf("%d-%d workers", min, max), true
	case "autoscale_max_desired":
		min := defaults.AutoscaleMaxDesired - 2
		if min < defaults.AutoscaleMinDesired {
			min = defaults.AutoscaleMinDesired
		}
		max := defaults.AutoscaleMaxDesired + 3
		return float64(min), float64(max), fmt.Sprintf("%d-%d workers", min, max), true
	case "policy_canary_window_minutes":
		return expandRangeInt(defaults.PolicyCanaryWindowMinutes, 0.8, 1.25, 1, 240, " min")
	case "policy_canary_required_health_windows":
		return expandRangeInt(defaults.PolicyCanaryRequiredHealthWindows, 0.75, 1.25, 1, 20, " windows")
	case "policy_canary_min_provider_workers":
		return expandRangeInt(defaults.PolicyCanaryMinProviderWorkers, 0.75, 1.5, 1, 1000, " workers")
	case "policy_canary_unknown_regression_threshold":
		return expandRangeFloat(defaults.PolicyCanaryUnknownRegressionThreshold, 0.8, 1.2, 0, 1, 2, "")
	case "policy_canary_tempfail_recovery_drop_threshold":
		return expandRangeFloat(defaults.PolicyCanaryTempfailRecoveryDropThreshold, 0.8, 1.2, 0, 1, 2, "")
	case "policy_canary_policy_block_spike_threshold":
		return expandRangeFloat(defaults.PolicyCanaryPolicyBlockSpikeThreshold, 0.8, 1.2, 0, 1, 2, "")
	case "ui_overview_live_interval_seconds":
		return expandRangeInt(defaults.UIOverviewLiveIntervalSecond, 0.8, 1.3, 2, 60, " sec")
	case "ui_workers_refresh_seconds":
		return expandRangeInt(defaults.UIWorkersRefreshSecond, 0.8, 1.3, 2, 120, " sec")
	case "ui_pools_refresh_seconds":
		return expandRangeInt(defaults.UIPoolsRefreshSecond, 0.8, 1.3, 2, 120, " sec")
	case "ui_alerts_refresh_seconds":
		return expandRangeInt(defaults.UIAlertsRefreshSecond, 0.8, 1.3, 5, 300, " sec")
	default:
		return 0, 0, "", false
	}
}

func expandRangeFloat(base float64, lowerFactor float64, upperFactor float64, hardMin float64, hardMax float64, precision int, suffix string) (float64, float64, string, bool) {
	min := base * lowerFactor
	max := base * upperFactor
	if base <= 0 {
		min = hardMin
		max = hardMin
	}
	if min < hardMin {
		min = hardMin
	}
	if max > hardMax {
		max = hardMax
	}
	if max < min {
		max = min
	}
	format := fmt.Sprintf("%%.%df", precision)
	return min, max, fmt.Sprintf(format+"-"+format+"%s", min, max, suffix), true
}

func expandRangeInt(base int, lowerFactor float64, upperFactor float64, hardMin int, hardMax int, suffix string) (float64, float64, string, bool) {
	min := int(float64(base) * lowerFactor)
	max := int(float64(base) * upperFactor)
	if base <= 0 {
		min = hardMin
		max = hardMin
	}
	if min < hardMin {
		min = hardMin
	}
	if max > hardMax {
		max = hardMax
	}
	if max < min {
		max = min
	}
	return float64(min), float64(max), fmt.Sprintf("%d-%d%s", min, max, suffix), true
}

func runtimeSettingsGuideURL(docsBaseURL string) string {
	base := strings.TrimRight(strings.TrimSpace(docsBaseURL), "/")
	if base == "" {
		return "/internal/docs/go/runtime-settings"
	}
	if strings.HasSuffix(base, "/go/runtime-settings") {
		return base
	}
	if strings.HasSuffix(base, "/internal/docs") {
		return base + "/go/runtime-settings"
	}
	return base + "/go/runtime-settings"
}

func boolStandard(enabled bool) string {
	if enabled {
		return "Enabled"
	}
	return "Disabled"
}

func intStandard(value int, unit string) string {
	return fmt.Sprintf("%d %s", value, unit)
}

func percentStandard(value int) string {
	return fmt.Sprintf("%d%%", value)
}

func rateStandard(value float64) string {
	return fmt.Sprintf("%.2f", value)
}
