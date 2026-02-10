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
	WorkerCount       int
	PoolCount         int
	DesiredTotal      int
	ErrorRateTotal    float64
	ErrorRateAverage  float64
	IncidentCount     int
	ProbeUnknownRate  float64
	ProbeTempfailRate float64
	ProbeRejectRate   float64
	ProviderHealth    []ProviderHealthSummary
	Pools             []PoolSummary
	ChartLabels       []string
	ChartOnline       []int
	ChartDesired      []int
	HistoryLabels     []string
	HistoryWorkers    []int
	HistoryDesired    []int
	HasHistory        bool
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
	Saved            bool
	Settings         RuntimeSettings
	ProviderHealth   []ProviderHealthSummary
	ProviderPolicies ProviderPoliciesData
	PolicyVersions   []SMTPPolicyVersionRecord
	PolicyRollouts   []SMTPPolicyRolloutRecord
}

type LivePayload struct {
	Timestamp          string                  `json:"timestamp"`
	WorkerCount        int                     `json:"worker_count"`
	PoolCount          int                     `json:"pool_count"`
	DesiredTotal       int                     `json:"desired_total"`
	ErrorRateTotal     float64                 `json:"error_rate_total"`
	ErrorRateAverage   float64                 `json:"error_rate_average"`
	Pools              []PoolSummary           `json:"pools"`
	IncidentCount      int                     `json:"incident_count"`
	ProbeUnknownRate   float64                 `json:"probe_unknown_rate"`
	ProbeTempfailRate  float64                 `json:"probe_tempfail_rate"`
	ProbeRejectRate    float64                 `json:"probe_reject_rate"`
	ProviderHealth     []ProviderHealthSummary `json:"provider_health,omitempty"`
	AlertsEnabled      bool                    `json:"alerts_enabled"`
	AutoActionsEnabled bool                    `json:"auto_actions_enabled"`
	AutoscaleEnabled   bool                    `json:"autoscale_enabled"`
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
			LiveStreamPath:  "/verifier-engine-room/events",
		},
		WorkerCount:       stats.WorkerCount,
		PoolCount:         stats.PoolCount,
		DesiredTotal:      stats.DesiredTotal,
		ErrorRateTotal:    stats.ErrorRateTotal,
		ErrorRateAverage:  stats.ErrorRateAverage,
		IncidentCount:     stats.IncidentCount,
		ProbeUnknownRate:  stats.ProbeUnknownRate,
		ProbeTempfailRate: stats.ProbeTempfailRate,
		ProbeRejectRate:   stats.ProbeRejectRate,
		ProviderHealth:    stats.ProviderHealth,
		Pools:             stats.Pools,
		ChartLabels:       labels,
		ChartOnline:       online,
		ChartDesired:      desired,
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

	data := SettingsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Settings",
			Subtitle:        "Runtime controls (alerts, safety, autoscale)",
			ActiveNav:       "settings",
			ContentTemplate: "settings",
			BasePath:        "/verifier-engine-room",
		},
		Saved:            r.URL.Query().Get("saved") == "1",
		Settings:         settings,
		ProviderHealth:   providerHealth,
		ProviderPolicies: providerPolicies,
		PolicyVersions:   []SMTPPolicyVersionRecord{},
		PolicyRollouts:   []SMTPPolicyRolloutRecord{},
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
	if err := r.ParseForm(); err != nil {
		writeError(w, http.StatusBadRequest, "invalid form")
		return
	}

	threshold, err := strconv.ParseFloat(r.FormValue("alert_error_rate_threshold"), 64)
	if err != nil || math.IsNaN(threshold) || math.IsInf(threshold, 0) || threshold < 0 {
		writeError(w, http.StatusBadRequest, "alert_error_rate_threshold must be >= 0")
		return
	}

	grace, err := strconv.Atoi(r.FormValue("alert_heartbeat_grace_seconds"))
	if err != nil || grace <= 0 {
		writeError(w, http.StatusBadRequest, "alert_heartbeat_grace_seconds must be > 0")
		return
	}

	cooldown, err := strconv.Atoi(r.FormValue("alert_cooldown_seconds"))
	if err != nil || cooldown <= 0 {
		writeError(w, http.StatusBadRequest, "alert_cooldown_seconds must be > 0")
		return
	}

	autoscaleCanary := s.cfg.AutoScaleCanaryPercent
	if autoscaleCanary <= 0 {
		autoscaleCanary = 100
	}
	if value := strings.TrimSpace(r.FormValue("autoscale_canary_percent")); value != "" {
		parsed, parseErr := strconv.Atoi(value)
		if parseErr != nil || parsed < 1 || parsed > 100 {
			writeError(w, http.StatusBadRequest, "autoscale_canary_percent must be between 1 and 100")
			return
		}
		autoscaleCanary = parsed
	}

	quarantineThreshold := s.cfg.QuarantineErrorRate
	if quarantineThreshold <= 0 {
		quarantineThreshold = threshold * 1.5
	}
	if value := strings.TrimSpace(r.FormValue("quarantine_error_rate_threshold")); value != "" {
		parsed, parseErr := strconv.ParseFloat(value, 64)
		if parseErr != nil || math.IsNaN(parsed) || math.IsInf(parsed, 0) || parsed < 0 {
			writeError(w, http.StatusBadRequest, "quarantine_error_rate_threshold must be >= 0")
			return
		}
		quarantineThreshold = parsed
	}

	overviewLiveInterval, err := strconv.Atoi(r.FormValue("ui_overview_live_interval_seconds"))
	if err != nil || overviewLiveInterval < 2 || overviewLiveInterval > 60 {
		writeError(w, http.StatusBadRequest, "ui_overview_live_interval_seconds must be between 2 and 60")
		return
	}

	workersRefreshInterval, err := strconv.Atoi(r.FormValue("ui_workers_refresh_seconds"))
	if err != nil || workersRefreshInterval < 2 || workersRefreshInterval > 120 {
		writeError(w, http.StatusBadRequest, "ui_workers_refresh_seconds must be between 2 and 120")
		return
	}

	poolsRefreshInterval, err := strconv.Atoi(r.FormValue("ui_pools_refresh_seconds"))
	if err != nil || poolsRefreshInterval < 2 || poolsRefreshInterval > 120 {
		writeError(w, http.StatusBadRequest, "ui_pools_refresh_seconds must be between 2 and 120")
		return
	}

	alertsRefreshInterval, err := strconv.Atoi(r.FormValue("ui_alerts_refresh_seconds"))
	if err != nil || alertsRefreshInterval < 5 || alertsRefreshInterval > 300 {
		writeError(w, http.StatusBadRequest, "ui_alerts_refresh_seconds must be between 5 and 300")
		return
	}

	settings := RuntimeSettings{
		AlertsEnabled:                r.FormValue("alerts_enabled") != "",
		AutoActionsEnabled:           r.FormValue("auto_actions_enabled") != "",
		ProviderPolicyEngineEnabled:  r.FormValue("provider_policy_engine_enabled") != "",
		AdaptiveRetryEnabled:         r.FormValue("adaptive_retry_enabled") != "",
		ProviderAutoprotectEnabled:   r.FormValue("provider_autoprotect_enabled") != "",
		AlertErrorRateThreshold:      threshold,
		AlertHeartbeatGraceSecond:    grace,
		AlertCooldownSecond:          cooldown,
		AutoscaleEnabled:             r.FormValue("autoscale_enabled") != "",
		AutoscaleCanaryPercent:       autoscaleCanary,
		QuarantineErrorRateThreshold: quarantineThreshold,
		UIOverviewLiveIntervalSecond: overviewLiveInterval,
		UIWorkersRefreshSecond:       workersRefreshInterval,
		UIPoolsRefreshSecond:         poolsRefreshInterval,
		UIAlertsRefreshSecond:        alertsRefreshInterval,
	}

	if err := s.store.SaveRuntimeSettings(r.Context(), settings); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
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
	if _, err := s.store.PromoteSMTPPolicyVersion(r.Context(), version, canaryPercent, "ui", notes); err != nil {
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
	if _, err := s.store.RollbackSMTPPolicyVersion(r.Context(), "ui", notes); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	http.Redirect(w, r, "/verifier-engine-room/settings?saved=1", http.StatusSeeOther)
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
		Timestamp:          time.Now().UTC().Format(time.RFC3339),
		WorkerCount:        stats.WorkerCount,
		PoolCount:          stats.PoolCount,
		DesiredTotal:       stats.DesiredTotal,
		ErrorRateTotal:     stats.ErrorRateTotal,
		ErrorRateAverage:   stats.ErrorRateAverage,
		Pools:              stats.Pools,
		IncidentCount:      stats.IncidentCount,
		ProbeUnknownRate:   stats.ProbeUnknownRate,
		ProbeTempfailRate:  stats.ProbeTempfailRate,
		ProbeRejectRate:    stats.ProbeRejectRate,
		ProviderHealth:     stats.ProviderHealth,
		AlertsEnabled:      stats.Settings.AlertsEnabled,
		AutoActionsEnabled: stats.Settings.AutoActionsEnabled,
		AutoscaleEnabled:   stats.Settings.AutoscaleEnabled,
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
