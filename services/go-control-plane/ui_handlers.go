package main

import (
	"context"
	"encoding/json"
	"fmt"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

type OverviewData struct {
	BasePageData
	WorkerCount            int
	PoolCount              int
	DesiredTotal           int
	ErrorRateTotal         float64
	ErrorRateAverage       float64
	IncidentCount          int
	ProbeUnknownRate       float64
	ProbeTempfailRate      float64
	ProbeRejectRate        float64
	LaravelFallbackWorkers int
	ProviderHealth         []ProviderHealthSummary
	ProviderAccuracy       []ProviderAccuracyCalibrationSummary
	UnknownClusters        []ProviderUnknownClusterSummary
	PolicyShadowRuns       []PolicyShadowRunRecord
	ProviderPolicies       ProviderPoliciesData
	RoutingQuality         RoutingQualitySummary
	Pools                  []PoolSummary
	ChartLabels            []string
	ChartOnline            []int
	ChartDesired           []int
	HistoryLabels          []string
	HistoryWorkers         []int
	HistoryDesired         []int
	HasHistory             bool
}

type WorkersPageData struct {
	BasePageData
	WorkerCount         int
	Workers             []WorkerSummary
	PollIntervalSeconds int
}

type PoolsPageData struct {
	BasePageData
	PoolCount           int
	Pools               []PoolSummary
	PollIntervalSeconds int
}

type AlertsPageData struct {
	BasePageData
	HasStorage          bool
	AlertCount          int
	Alerts              []AlertRecord
	Incidents           []IncidentRecord
	ActiveIncidentCount int
	PollIntervalSeconds int
}

type SettingsPageData struct {
	BasePageData
	Saved                                     bool
	RolledBack                                bool
	HasRollbackSnapshot                       bool
	Settings                                  RuntimeSettings
	DefaultSettings                           RuntimeSettings
	ProviderHealth                            []ProviderHealthSummary
	ProviderPolicies                          ProviderPoliciesData
	PolicyVersions                            []SMTPPolicyVersionRecord
	PolicyRollouts                            []SMTPPolicyRolloutRecord
	PolicyPayloadStrictValidationEnabled      bool
	PolicyCanaryAutopilotEnabled              bool
	PolicyCanaryWindowMinutes                 int
	PolicyCanaryRequiredHealthWindows         int
	PolicyCanaryUnknownRegressionThreshold    float64
	PolicyCanaryTempfailRecoveryDropThreshold float64
	PolicyCanaryPolicyBlockSpikeThreshold     float64
	RuntimeHelp                               map[string]SettingHelpTip
}

type LivePayload struct {
	Timestamp              string                  `json:"timestamp"`
	WorkerCount            int                     `json:"worker_count"`
	PoolCount              int                     `json:"pool_count"`
	DesiredTotal           int                     `json:"desired_total"`
	ErrorRateTotal         float64                 `json:"error_rate_total"`
	ErrorRateAverage       float64                 `json:"error_rate_average"`
	Pools                  []PoolSummary           `json:"pools"`
	IncidentCount          int                     `json:"incident_count"`
	ProbeUnknownRate       float64                 `json:"probe_unknown_rate"`
	ProbeTempfailRate      float64                 `json:"probe_tempfail_rate"`
	ProbeRejectRate        float64                 `json:"probe_reject_rate"`
	LaravelFallbackWorkers int                     `json:"laravel_fallback_workers"`
	ActivePolicyVersion    string                  `json:"active_policy_version"`
	ProviderHealth         []ProviderHealthSummary `json:"provider_health,omitempty"`
	RoutingQuality         RoutingQualitySummary   `json:"routing_quality"`
	AlertsEnabled          bool                    `json:"alerts_enabled"`
	AutoActionsEnabled     bool                    `json:"auto_actions_enabled"`
	AutoscaleEnabled       bool                    `json:"autoscale_enabled"`
}

func (s *Server) handleUIRedirect(w http.ResponseWriter, r *http.Request) {
	http.Redirect(w, r, "/verifier-engine-room/overview", http.StatusFound)
}

func (s *Server) runtimeSettings(ctx context.Context) RuntimeSettings {
	defaults := defaultRuntimeSettings(s.cfg)
	if s.store == nil {
		return defaults
	}

	settings, err := s.store.GetRuntimeSettings(ctx, defaults)
	if err != nil {
		return defaults
	}

	return settings
}

func (s *Server) docsURL() string {
	override := strings.TrimSpace(s.cfg.OpsDocsURL)
	if override != "" {
		return override
	}

	base := strings.TrimRight(strings.TrimSpace(s.cfg.LaravelAPIBaseURL), "/")
	if base == "" {
		return ""
	}

	return base + "/internal/docs"
}

func (s *Server) handleUILegacyRedirect(path string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, path, http.StatusFound)
	}
}

func (s *Server) handleUIOverview(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	labels := make([]string, 0, len(stats.Pools))
	online := make([]int, 0, len(stats.Pools))
	desired := make([]int, 0, len(stats.Pools))
	for _, pool := range stats.Pools {
		labels = append(labels, pool.Pool)
		online = append(online, pool.Online)
		desired = append(desired, pool.Desired)
	}

	data := OverviewData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Overview",
			Subtitle:        "Live telemetry stream",
			ActiveNav:       "overview",
			ContentTemplate: "overview",
			BasePath:        "/verifier-engine-room",
			DocsURL:         s.docsURL(),
			LiveStreamPath:  "/verifier-engine-room/events",
		},
		WorkerCount:            stats.WorkerCount,
		PoolCount:              stats.PoolCount,
		DesiredTotal:           stats.DesiredTotal,
		ErrorRateTotal:         stats.ErrorRateTotal,
		ErrorRateAverage:       stats.ErrorRateAverage,
		IncidentCount:          stats.IncidentCount,
		ProbeUnknownRate:       stats.ProbeUnknownRate,
		ProbeTempfailRate:      stats.ProbeTempfailRate,
		ProbeRejectRate:        stats.ProbeRejectRate,
		LaravelFallbackWorkers: stats.LaravelFallbackWorkers,
		ProviderHealth:         stats.ProviderHealth,
		ProviderAccuracy:       stats.ProviderAccuracy,
		UnknownClusters:        stats.UnknownClusters,
		PolicyShadowRuns:       stats.PolicyShadowRuns,
		ProviderPolicies:       stats.ProviderPolicies,
		RoutingQuality:         stats.RoutingQuality,
		Pools:                  stats.Pools,
		ChartLabels:            labels,
		ChartOnline:            online,
		ChartDesired:           desired,
	}

	if s.snapshots != nil {
		points, snapshotsErr := s.snapshots.GetWorkerSnapshots(r.Context(), 120)
		if snapshotsErr == nil && len(points) > 0 {
			historyLabels := make([]string, 0, len(points))
			historyWorkers := make([]int, 0, len(points))
			historyDesired := make([]int, 0, len(points))
			for _, point := range points {
				historyLabels = append(historyLabels, point.CapturedAt.Format("15:04"))
				historyWorkers = append(historyWorkers, point.TotalWorkers)
				historyDesired = append(historyDesired, point.DesiredTotal)
			}
			data.HistoryLabels = historyLabels
			data.HistoryWorkers = historyWorkers
			data.HistoryDesired = historyDesired
			data.HasHistory = true
		}
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIWorkers(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	settings := s.runtimeSettings(r.Context())

	data := WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
			DocsURL:         s.docsURL(),
		},
		WorkerCount:         stats.WorkerCount,
		Workers:             stats.Workers,
		PollIntervalSeconds: settings.UIWorkersRefreshSecond,
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIPools(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	settings := s.runtimeSettings(r.Context())

	data := PoolsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Pools",
			Subtitle:        "Scale worker pools",
			ActiveNav:       "pools",
			ContentTemplate: "pools",
			BasePath:        "/verifier-engine-room",
			DocsURL:         s.docsURL(),
		},
		PoolCount:           stats.PoolCount,
		Pools:               stats.Pools,
		PollIntervalSeconds: settings.UIPoolsRefreshSecond,
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIAlerts(w http.ResponseWriter, r *http.Request) {
	settings := s.runtimeSettings(r.Context())
	data := AlertsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Alerts",
			Subtitle:        "Recent control plane alerts",
			ActiveNav:       "alerts",
			ContentTemplate: "alerts",
			BasePath:        "/verifier-engine-room",
			DocsURL:         s.docsURL(),
		},
		HasStorage:          s.snapshots != nil,
		PollIntervalSeconds: settings.UIAlertsRefreshSecond,
	}

	if s.snapshots != nil {
		alerts, err := s.snapshots.GetRecentAlerts(r.Context(), 200)
		if err != nil {
			writeError(w, http.StatusInternalServerError, err.Error())
			return
		}
		data.Alerts = alerts
		data.AlertCount = len(alerts)
	}

	incidents, err := s.store.ListIncidents(r.Context(), 100, true)
	if err == nil {
		data.Incidents = incidents
		for _, incident := range incidents {
			if incident.Status == "active" {
				data.ActiveIncidentCount++
			}
		}
	}

	s.views.Render(w, data)
}

func (s *Server) handleUISettings(w http.ResponseWriter, r *http.Request) {
	defaults := defaultRuntimeSettings(s.cfg)
	settings := s.runtimeSettings(r.Context())

	stats, statsErr := s.collectControlPlaneStats(r.Context())
	providerHealth := make([]ProviderHealthSummary, 0)
	providerPolicies := ProviderPoliciesData{
		PolicyEngineEnabled:  settings.ProviderPolicyEngineEnabled,
		AdaptiveRetryEnabled: settings.AdaptiveRetryEnabled,
		AutoProtectEnabled:   settings.ProviderAutoprotectEnabled,
		Modes:                []ProviderModeState{},
	}
	if statsErr == nil {
		providerHealth = stats.ProviderHealth
		providerPolicies = stats.ProviderPolicies
	}
	hasRollbackSnapshot := false
	if s.store != nil {
		hasRollbackSnapshot, _ = s.store.HasRuntimeSettingsSnapshot(r.Context())
	}

	data := SettingsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Settings",
			Subtitle:        "Runtime controls (alerts, safety, autoscale)",
			ActiveNav:       "settings",
			ContentTemplate: "settings",
			BasePath:        "/verifier-engine-room",
			DocsURL:         s.docsURL(),
		},
		Saved:                                     r.URL.Query().Get("saved") == "1",
		RolledBack:                                r.URL.Query().Get("rolled_back") == "1",
		HasRollbackSnapshot:                       hasRollbackSnapshot,
		Settings:                                  settings,
		DefaultSettings:                           defaults,
		ProviderHealth:                            providerHealth,
		ProviderPolicies:                          providerPolicies,
		PolicyVersions:                            []SMTPPolicyVersionRecord{},
		PolicyRollouts:                            []SMTPPolicyRolloutRecord{},
		PolicyPayloadStrictValidationEnabled:      s.cfg.PolicyPayloadStrictValidationEnabled,
		PolicyCanaryAutopilotEnabled:              settings.PolicyCanaryAutopilotEnabled,
		PolicyCanaryWindowMinutes:                 settings.PolicyCanaryWindowMinutes,
		PolicyCanaryRequiredHealthWindows:         settings.PolicyCanaryRequiredHealthWindows,
		PolicyCanaryUnknownRegressionThreshold:    settings.PolicyCanaryUnknownRegressionThreshold,
		PolicyCanaryTempfailRecoveryDropThreshold: settings.PolicyCanaryTempfailRecoveryDropThreshold,
		PolicyCanaryPolicyBlockSpikeThreshold:     settings.PolicyCanaryPolicyBlockSpikeThreshold,
		RuntimeHelp:                               buildRuntimeSettingsHelp(defaults, s.docsURL()),
	}

	if versions, _, versionsErr := s.store.ListSMTPPolicyVersions(r.Context()); versionsErr == nil {
		data.PolicyVersions = versions
	}
	if history, historyErr := s.store.GetSMTPPolicyRolloutHistory(r.Context(), 20); historyErr == nil {
		data.PolicyRollouts = history
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIUpdateSettings(w http.ResponseWriter, r *http.Request) {
	defaults := defaultRuntimeSettings(s.cfg)

	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	parseIntRange := func(name string, min int, max int, message string) (int, bool) {
		value, err := strconv.Atoi(r.FormValue(name))
		if err != nil || value < min || value > max {
			writeError(w, http.StatusBadRequest, message)
			return 0, false
		}

		return value, true
	}

	parseFloatRange := func(name string, min float64, max float64, message string) (float64, bool) {
		value, err := strconv.ParseFloat(r.FormValue(name), 64)
		if err != nil || math.IsNaN(value) || math.IsInf(value, 0) || value < min || value > max {
			writeError(w, http.StatusBadRequest, message)
			return 0, false
		}

		return value, true
	}

	threshold, ok := parseFloatRange("alert_error_rate_threshold", 0, 1000000, "alert_error_rate_threshold must be >= 0")
	if !ok {
		return
	}

	grace, ok := parseIntRange("alert_heartbeat_grace_seconds", 1, 86400, "alert_heartbeat_grace_seconds must be between 1 and 86400")
	if !ok {
		return
	}

	cooldown, ok := parseIntRange("alert_cooldown_seconds", 1, 86400, "alert_cooldown_seconds must be between 1 and 86400")
	if !ok {
		return
	}

	alertCheckInterval, ok := parseIntRange("alert_check_interval_seconds", 5, 600, "alert_check_interval_seconds must be between 5 and 600")
	if !ok {
		return
	}

	staleWorkerTTL, ok := parseIntRange("stale_worker_ttl_seconds", 60, 2592000, "stale_worker_ttl_seconds must be between 60 and 2592000")
	if !ok {
		return
	}

	stuckDesiredGrace, ok := parseIntRange("stuck_desired_grace_seconds", 30, 86400, "stuck_desired_grace_seconds must be between 30 and 86400")
	if !ok {
		return
	}

	autoscaleInterval, ok := parseIntRange("autoscale_interval_seconds", 5, 600, "autoscale_interval_seconds must be between 5 and 600")
	if !ok {
		return
	}

	autoscaleCooldown, ok := parseIntRange("autoscale_cooldown_seconds", 10, 86400, "autoscale_cooldown_seconds must be between 10 and 86400")
	if !ok {
		return
	}

	autoscaleMinDesired, ok := parseIntRange("autoscale_min_desired", 0, 1000, "autoscale_min_desired must be between 0 and 1000")
	if !ok {
		return
	}

	autoscaleMaxDesired, ok := parseIntRange("autoscale_max_desired", autoscaleMinDesired, 1000, "autoscale_max_desired must be >= autoscale_min_desired and <= 1000")
	if !ok {
		return
	}

	autoscaleCanary, ok := parseIntRange("autoscale_canary_percent", 1, 100, "autoscale_canary_percent must be between 1 and 100")
	if !ok {
		return
	}

	quarantineThreshold, ok := parseFloatRange("quarantine_error_rate_threshold", 0, 1000000, "quarantine_error_rate_threshold must be >= 0")
	if !ok {
		return
	}

	providerTempfailWarnRate, ok := parseFloatRange("provider_tempfail_warn_rate", 0, 1, "provider_tempfail_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerTempfailCriticalRate, ok := parseFloatRange("provider_tempfail_critical_rate", providerTempfailWarnRate, 1, "provider_tempfail_critical_rate must be >= provider_tempfail_warn_rate and <= 1")
	if !ok {
		return
	}

	providerRejectWarnRate, ok := parseFloatRange("provider_reject_warn_rate", 0, 1, "provider_reject_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerRejectCriticalRate, ok := parseFloatRange("provider_reject_critical_rate", providerRejectWarnRate, 1, "provider_reject_critical_rate must be >= provider_reject_warn_rate and <= 1")
	if !ok {
		return
	}

	providerUnknownWarnRate, ok := parseFloatRange("provider_unknown_warn_rate", 0, 1, "provider_unknown_warn_rate must be between 0 and 1")
	if !ok {
		return
	}

	providerUnknownCriticalRate, ok := parseFloatRange("provider_unknown_critical_rate", providerUnknownWarnRate, 1, "provider_unknown_critical_rate must be >= provider_unknown_warn_rate and <= 1")
	if !ok {
		return
	}

	policyCanaryWindowMinutes, ok := parseIntRange("policy_canary_window_minutes", 1, 240, "policy_canary_window_minutes must be between 1 and 240")
	if !ok {
		return
	}

	policyCanaryRequiredHealthWindows, ok := parseIntRange("policy_canary_required_health_windows", 1, 20, "policy_canary_required_health_windows must be between 1 and 20")
	if !ok {
		return
	}

	policyCanaryUnknownRegressionThreshold, ok := parseFloatRange("policy_canary_unknown_regression_threshold", 0, 1, "policy_canary_unknown_regression_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryTempfailRecoveryDropThreshold, ok := parseFloatRange("policy_canary_tempfail_recovery_drop_threshold", 0, 1, "policy_canary_tempfail_recovery_drop_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryPolicyBlockSpikeThreshold, ok := parseFloatRange("policy_canary_policy_block_spike_threshold", 0, 1, "policy_canary_policy_block_spike_threshold must be between 0 and 1")
	if !ok {
		return
	}

	policyCanaryMinProviderWorkers, ok := parseIntRange("policy_canary_min_provider_workers", 1, 1000, "policy_canary_min_provider_workers must be between 1 and 1000")
	if !ok {
		return
	}

	overviewLiveInterval, ok := parseIntRange("ui_overview_live_interval_seconds", 2, 60, "ui_overview_live_interval_seconds must be between 2 and 60")
	if !ok {
		return
	}

	workersRefreshInterval, ok := parseIntRange("ui_workers_refresh_seconds", 2, 120, "ui_workers_refresh_seconds must be between 2 and 120")
	if !ok {
		return
	}

	poolsRefreshInterval, ok := parseIntRange("ui_pools_refresh_seconds", 2, 120, "ui_pools_refresh_seconds must be between 2 and 120")
	if !ok {
		return
	}

	alertsRefreshInterval, ok := parseIntRange("ui_alerts_refresh_seconds", 5, 300, "ui_alerts_refresh_seconds must be between 5 and 300")
	if !ok {
		return
	}

	settings := RuntimeSettings{
		AlertsEnabled:                             r.FormValue("alerts_enabled") != "",
		AutoActionsEnabled:                        r.FormValue("auto_actions_enabled") != "",
		ProviderPolicyEngineEnabled:               r.FormValue("provider_policy_engine_enabled") != "",
		AdaptiveRetryEnabled:                      r.FormValue("adaptive_retry_enabled") != "",
		ProviderAutoprotectEnabled:                r.FormValue("provider_autoprotect_enabled") != "",
		AlertErrorRateThreshold:                   threshold,
		AlertHeartbeatGraceSecond:                 grace,
		AlertCooldownSecond:                       cooldown,
		AlertCheckIntervalSecond:                  alertCheckInterval,
		StaleWorkerTTLSecond:                      staleWorkerTTL,
		StuckDesiredGraceSecond:                   stuckDesiredGrace,
		AutoscaleEnabled:                          r.FormValue("autoscale_enabled") != "",
		AutoscaleIntervalSecond:                   autoscaleInterval,
		AutoscaleCooldownSecond:                   autoscaleCooldown,
		AutoscaleMinDesired:                       autoscaleMinDesired,
		AutoscaleMaxDesired:                       autoscaleMaxDesired,
		AutoscaleCanaryPercent:                    autoscaleCanary,
		QuarantineErrorRateThreshold:              quarantineThreshold,
		ProviderTempfailWarnRate:                  providerTempfailWarnRate,
		ProviderTempfailCriticalRate:              providerTempfailCriticalRate,
		ProviderRejectWarnRate:                    providerRejectWarnRate,
		ProviderRejectCriticalRate:                providerRejectCriticalRate,
		ProviderUnknownWarnRate:                   providerUnknownWarnRate,
		ProviderUnknownCriticalRate:               providerUnknownCriticalRate,
		PolicyCanaryAutopilotEnabled:              r.FormValue("policy_canary_autopilot_enabled") != "",
		PolicyCanaryWindowMinutes:                 policyCanaryWindowMinutes,
		PolicyCanaryRequiredHealthWindows:         policyCanaryRequiredHealthWindows,
		PolicyCanaryUnknownRegressionThreshold:    policyCanaryUnknownRegressionThreshold,
		PolicyCanaryTempfailRecoveryDropThreshold: policyCanaryTempfailRecoveryDropThreshold,
		PolicyCanaryPolicyBlockSpikeThreshold:     policyCanaryPolicyBlockSpikeThreshold,
		PolicyCanaryMinProviderWorkers:            policyCanaryMinProviderWorkers,
		UIOverviewLiveIntervalSecond:              overviewLiveInterval,
		UIWorkersRefreshSecond:                    workersRefreshInterval,
		UIPoolsRefreshSecond:                      poolsRefreshInterval,
		UIAlertsRefreshSecond:                     alertsRefreshInterval,
	}

	if err := validateRuntimeSettingsRisk(defaults, settings); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}

	if s.store == nil {
		writeError(w, http.StatusInternalServerError, "store is not configured")
		return
	}

	if err := s.store.SaveRuntimeSettings(r.Context(), settings); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func formatRuntimeSettingValue(value float64) string {
	if math.Abs(value-math.Round(value)) < 0.0001 {
		return strconv.Itoa(int(math.Round(value)))
	}

	return fmt.Sprintf("%.2f", value)
}

func validateRuntimeSettingsRisk(defaults RuntimeSettings, settings RuntimeSettings) error {
	if key, tip, value, found := firstUnsafeRuntimeSetting(defaults, settings); found {
		return fmt.Errorf(
			"%s is unsafe at %s (safe: %s, caution: %s)",
			key,
			formatRuntimeSettingValue(value),
			tip.RecommendedRangeLabel,
			tip.CautionRangeLabel,
		)
	}

	return nil
}

func (s *Server) handleUIRollbackSettings(w http.ResponseWriter, r *http.Request) {
	if s.store == nil {
		writeError(w, http.StatusInternalServerError, "store is not configured")
		return
	}

	if err := s.store.RollbackRuntimeSettings(r.Context()); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1&rolled_back=1", http.StatusSeeOther)
}

func (s *Server) handleUIProviderMode(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	provider := chi.URLParam(r, "provider")
	mode := strings.TrimSpace(r.FormValue("mode"))
	if _, err := s.store.SetProviderMode(r.Context(), provider, mode, "manual"); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIProviderPoliciesReload(w http.ResponseWriter, r *http.Request) {
	if _, err := s.store.MarkProviderPoliciesReloaded(r.Context()); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyValidate(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	version := strings.TrimSpace(r.FormValue("policy_version"))
	if version == "" {
		writeError(w, http.StatusBadRequest, "policy_version is required")
		return
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if _, err := s.store.ValidateSMTPPolicyVersion(r.Context(), version, uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyPromote(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	version := strings.TrimSpace(r.FormValue("policy_version"))
	if version == "" {
		writeError(w, http.StatusBadRequest, "policy_version is required")
		return
	}

	canaryPercent := 100
	if value := strings.TrimSpace(r.FormValue("canary_percent")); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil || parsed < 1 || parsed > 100 {
			writeError(w, http.StatusBadRequest, "canary_percent must be between 1 and 100")
			return
		}
		canaryPercent = parsed
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if _, err := s.store.PromoteSMTPPolicyVersion(r.Context(), version, canaryPercent, uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func (s *Server) handleUIPolicyRollback(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	notes := strings.TrimSpace(r.FormValue("notes"))
	if _, err := s.store.RollbackSMTPPolicyVersion(r.Context(), uiTriggeredBy(r), notes); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
}

func uiTriggeredBy(r *http.Request) string {
	username, _, ok := r.BasicAuth()
	if !ok {
		return "ui"
	}

	username = strings.TrimSpace(username)
	if username == "" {
		return "ui"
	}

	return username
}

func (s *Server) handleUIEvents(w http.ResponseWriter, r *http.Request) {
	flusher, ok := w.(http.Flusher)
	if !ok {
		writeError(w, http.StatusInternalServerError, "streaming not supported")
		return
	}

	// Clear per-request write deadline for long-lived SSE stream while keeping finite server WriteTimeout.
	if controller := http.NewResponseController(w); controller != nil {
		_ = controller.SetWriteDeadline(time.Time{})
	}

	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")

	s.pushLiveEvent(w, r)
	flusher.Flush()

	settings := s.runtimeSettings(r.Context())
	interval := settings.UIOverviewLiveIntervalSecond
	if interval <= 0 {
		interval = 5
	}

	ticker := time.NewTicker(time.Duration(interval) * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-r.Context().Done():
			return
		case <-ticker.C:
			s.pushLiveEvent(w, r)
			flusher.Flush()
		}
	}
}

func (s *Server) pushLiveEvent(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
	defer cancel()

	stats, err := s.collectControlPlaneStats(ctx)
	if err != nil {
		return
	}

	payload := LivePayload{
		Timestamp:              time.Now().UTC().Format(time.RFC3339),
		WorkerCount:            stats.WorkerCount,
		PoolCount:              stats.PoolCount,
		DesiredTotal:           stats.DesiredTotal,
		ErrorRateTotal:         stats.ErrorRateTotal,
		ErrorRateAverage:       stats.ErrorRateAverage,
		Pools:                  stats.Pools,
		IncidentCount:          stats.IncidentCount,
		ProbeUnknownRate:       stats.ProbeUnknownRate,
		ProbeTempfailRate:      stats.ProbeTempfailRate,
		ProbeRejectRate:        stats.ProbeRejectRate,
		LaravelFallbackWorkers: stats.LaravelFallbackWorkers,
		ActivePolicyVersion:    stats.ProviderPolicies.ActiveVersion,
		ProviderHealth:         stats.ProviderHealth,
		RoutingQuality:         stats.RoutingQuality,
		AlertsEnabled:          stats.Settings.AlertsEnabled,
		AutoActionsEnabled:     stats.Settings.AutoActionsEnabled,
		AutoscaleEnabled:       stats.Settings.AutoscaleEnabled,
	}

	data, err := json.Marshal(payload)
	if err != nil {
		return
	}

	_, _ = fmt.Fprintf(w, "event: stats\ndata: %s\n\n", data)
}

func (s *Server) handleUISetDesired(state string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workerID := chi.URLParam(r, "workerID")
		if workerID == "" {
			writeError(w, http.StatusBadRequest, "workerID is required")
			return
		}

		if err := s.store.SetDesiredState(r.Context(), workerID, state); err != nil {
			writeError(w, http.StatusBadRequest, err.Error())
			return
		}

		http.Redirect(w, r, "/verifier-engine-room/workers", http.StatusSeeOther)
	}
}

func (s *Server) handleUIQuarantine(enabled bool) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workerID := chi.URLParam(r, "workerID")
		if workerID == "" {
			writeError(w, http.StatusBadRequest, "workerID is required")
			return
		}

		reason := ""
		if enabled {
			reason = "manual_ui_action"
		}

		if err := s.store.SetWorkerQuarantined(r.Context(), workerID, enabled, reason); err != nil {
			writeError(w, http.StatusBadRequest, err.Error())
			return
		}

		http.Redirect(w, r, "/verifier-engine-room/workers", http.StatusSeeOther)
	}
}

func (s *Server) handleUIScalePool(w http.ResponseWriter, r *http.Request) {
	pool := chi.URLParam(r, "pool")
	if pool == "" {
		writeError(w, http.StatusBadRequest, "pool is required")
		return
	}

	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	desiredValue := r.FormValue("desired")
	desired, err := strconv.Atoi(desiredValue)
	if err != nil {
		writeError(w, http.StatusBadRequest, "desired must be a number")
		return
	}

	if err := s.store.SetPoolDesiredCount(r.Context(), pool, desired); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/pools", http.StatusSeeOther)
}
