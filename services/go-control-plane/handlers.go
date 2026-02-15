package main

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
)

func (s *Server) handleHeartbeat(w http.ResponseWriter, r *http.Request) {
	var payload HeartbeatRequest
	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	desiredState, err := s.store.UpsertHeartbeat(r.Context(), payload)
	if err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	response := HeartbeatResponse{
		DesiredState: desiredState,
		Commands:     []string{},
	}
	writeJSON(w, http.StatusOK, response)
}

func (s *Server) handleWorkers(w http.ResponseWriter, r *http.Request) {
	workers, err := s.store.GetWorkers(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, WorkersResponse{Data: workers})
}

func (s *Server) handleSetDesired(state string) http.HandlerFunc {
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

		writeJSON(w, http.StatusOK, map[string]string{"desired_state": state})
	}
}

func (s *Server) handlePools(w http.ResponseWriter, r *http.Request) {
	pools, err := s.store.GetPools(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, PoolsResponse{Data: pools})
}

func (s *Server) handleScalePool(w http.ResponseWriter, r *http.Request) {
	pool := chi.URLParam(r, "pool")
	if pool == "" {
		writeError(w, http.StatusBadRequest, "pool is required")
		return
	}

	var payload ScalePoolRequest
	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	if err := s.store.SetPoolDesiredCount(r.Context(), pool, payload.Desired); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ScalePoolResponse{Pool: pool, Desired: payload.Desired})
}

func (s *Server) handleReadiness(w http.ResponseWriter, r *http.Request) {
	type readiness struct {
		Status    string `json:"status"`
		Redis     string `json:"redis"`
		MySQL     string `json:"mysql"`
		Timestamp string `json:"timestamp"`
	}

	ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
	defer cancel()

	response := readiness{
		Status:    "ready",
		Redis:     "ok",
		MySQL:     "skipped",
		Timestamp: time.Now().UTC().Format(time.RFC3339),
	}

	if err := s.store.Ping(ctx); err != nil {
		response.Status = "not_ready"
		response.Redis = err.Error()
	}

	if s.snapshots != nil {
		if err := s.snapshots.Ping(ctx); err != nil {
			response.Status = "not_ready"
			response.MySQL = err.Error()
		} else {
			response.MySQL = "ok"
		}
	}

	if response.Status != "ready" {
		writeJSON(w, http.StatusServiceUnavailable, response)
		return
	}

	writeJSON(w, http.StatusOK, response)
}

func (s *Server) handleIncidents(w http.ResponseWriter, r *http.Request) {
	includeResolved := r.URL.Query().Get("include_resolved") == "1"
	limit := 100
	if value := r.URL.Query().Get("limit"); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	records, err := s.store.ListIncidents(r.Context(), limit, includeResolved)
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, IncidentsResponse{Data: records})
}

func (s *Server) handleAlertsRecords(w http.ResponseWriter, r *http.Request) {
	limit := 200
	if value := r.URL.Query().Get("limit"); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	if s.snapshots == nil {
		writeJSON(w, http.StatusOK, map[string]interface{}{
			"data":        []AlertRecord{},
			"has_storage": false,
		})
		return
	}

	alerts, err := s.snapshots.GetRecentAlerts(r.Context(), limit)
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data":        alerts,
		"has_storage": true,
	})
}

func (s *Server) handleSLO(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": map[string]interface{}{
			"timestamp":                 time.Now().UTC().Format(time.RFC3339),
			"incident_count":            stats.IncidentCount,
			"probe_unknown_rate_avg":    stats.ProbeUnknownRate,
			"probe_tempfail_rate_avg":   stats.ProbeTempfailRate,
			"probe_reject_rate_avg":     stats.ProbeRejectRate,
			"screening_processed_total": stats.ScreeningProcessedTotal,
			"probe_processed_total":     stats.ProbeProcessedTotal,
		},
	})
}

func (s *Server) handleProvidersHealth(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderHealthResponse{Data: stats.ProviderHealth})
}

func (s *Server) handleProvidersQuality(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderQualityResponse{Data: providerQualityFromHealth(stats.ProviderHealth)})
}

func (s *Server) handleProvidersQualityDrift(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	settings := s.runtimeSettings(r.Context())

	writeJSON(w, http.StatusOK, ProviderQualityDriftResponse{
		Data: providerQualityDriftFromHealth(stats.ProviderHealth, thresholdsFromRuntimeSettings(settings)),
	})
}

func (s *Server) handleProvidersRetryEffectiveness(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderRetryEffectivenessResponse{
		Data: providerRetryEffectivenessFromHealth(stats.ProviderHealth),
	})
}

func (s *Server) handleProviderMode(w http.ResponseWriter, r *http.Request) {
	provider := chi.URLParam(r, "provider")
	if provider == "" {
		writeError(w, http.StatusBadRequest, "provider is required")
		return
	}

	var payload struct {
		Mode string `json:"mode"`
	}
	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	modeState, err := s.store.SetProviderMode(r.Context(), provider, payload.Mode, "manual")
	if err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": modeState,
	})
}

func (s *Server) handleProviderPolicies(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderPoliciesResponse{Data: stats.ProviderPolicies})
}

func (s *Server) handleProviderModeSemantics(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": defaultProviderModeSemantics(),
	})
}

func (s *Server) handleProviderRoutingQuality(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": stats.RoutingQuality,
	})
}

func (s *Server) handleProbeRoutingEffectiveness(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": stats.RoutingQuality,
	})
}

func (s *Server) handleProviderPoliciesReload(w http.ResponseWriter, r *http.Request) {
	state, err := s.store.MarkProviderPoliciesReloaded(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": map[string]interface{}{
			"last_reload_at": state.LastReloadAt,
			"reload_count":   state.ReloadCount,
		},
	})
}

func (s *Server) handlePolicyVersions(w http.ResponseWriter, r *http.Request) {
	versions, activeVersion, err := s.store.ListSMTPPolicyVersions(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, SMTPPolicyVersionsResponse{
		Data:          versions,
		ActiveVersion: activeVersion,
	})
}

func (s *Server) handlePolicyPromote(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		Version       string `json:"version"`
		CanaryPercent int    `json:"canary_percent"`
		TriggeredBy   string `json:"triggered_by"`
		Notes         string `json:"notes"`
	}

	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	if strings.TrimSpace(payload.TriggeredBy) == "" {
		payload.TriggeredBy = "api"
	}

	record, err := s.store.PromoteSMTPPolicyVersion(
		r.Context(),
		payload.Version,
		payload.CanaryPercent,
		payload.TriggeredBy,
		payload.Notes,
	)
	if err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": record,
	})
}

func (s *Server) handlePolicyValidate(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		Version     string `json:"version"`
		TriggeredBy string `json:"triggered_by"`
		Notes       string `json:"notes"`
	}

	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	if strings.TrimSpace(payload.TriggeredBy) == "" {
		payload.TriggeredBy = "api"
	}

	record, err := s.store.ValidateSMTPPolicyVersion(r.Context(), payload.Version, payload.TriggeredBy, payload.Notes)
	if err != nil {
		writeJSON(w, http.StatusUnprocessableEntity, map[string]interface{}{
			"error": err.Error(),
			"data":  record,
		})
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": record,
	})
}

func (s *Server) handlePolicyRollback(w http.ResponseWriter, r *http.Request) {
	var payload struct {
		TriggeredBy string `json:"triggered_by"`
		Notes       string `json:"notes"`
	}

	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	if strings.TrimSpace(payload.TriggeredBy) == "" {
		payload.TriggeredBy = "api"
	}

	record, err := s.store.RollbackSMTPPolicyVersion(r.Context(), payload.TriggeredBy, payload.Notes)
	if err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, map[string]interface{}{
		"data": record,
	})
}

func (s *Server) handleQuarantineWorker(enabled bool) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		workerID := chi.URLParam(r, "workerID")
		if workerID == "" {
			writeError(w, http.StatusBadRequest, "workerID is required")
			return
		}

		reason := ""
		if enabled {
			reason = "manual_api_action"
		}

		if err := s.store.SetWorkerQuarantined(r.Context(), workerID, enabled, reason); err != nil {
			writeError(w, http.StatusBadRequest, err.Error())
			return
		}

		state := "off"
		if enabled {
			state = "on"
		}

		writeJSON(w, http.StatusOK, map[string]string{
			"worker_id":   workerID,
			"quarantine":  state,
			"desired_set": map[bool]string{true: "draining", false: "running"}[enabled],
		})
	}
}

func decodeJSON(r *http.Request, target interface{}) error {
	decoder := json.NewDecoder(r.Body)
	decoder.DisallowUnknownFields()
	return decoder.Decode(target)
}

func providerQualityFromHealth(health []ProviderHealthSummary) []ProviderQualitySummary {
	quality := make([]ProviderQualitySummary, 0, len(health))

	for _, provider := range health {
		tempfailRecovery := 1.0 - provider.UnknownRate
		if tempfailRecovery < 0 {
			tempfailRecovery = 0
		}
		if tempfailRecovery > 1 {
			tempfailRecovery = 1
		}

		retryWaste := provider.UnknownRate
		if retryWaste < 0 {
			retryWaste = 0
		}
		if retryWaste > 1 {
			retryWaste = 1
		}

		quality = append(quality, ProviderQualitySummary{
			Provider:            provider.Provider,
			Mode:                provider.Mode,
			Status:              provider.Status,
			TempfailRate:        provider.TempfailRate,
			RejectRate:          provider.RejectRate,
			UnknownRate:         provider.UnknownRate,
			PolicyBlockedRate:   provider.PolicyBlockedRate,
			TempfailRecoveryPct: tempfailRecovery * 100,
			RetryWastePct:       retryWaste * 100,
			Workers:             provider.Workers,
		})
	}

	return quality
}

func providerRetryEffectivenessFromHealth(health []ProviderHealthSummary) []ProviderRetryEffectivenessSummary {
	effectiveness := make([]ProviderRetryEffectivenessSummary, 0, len(health))

	for _, provider := range health {
		tempfailRecovery := 1.0 - provider.UnknownRate
		if tempfailRecovery < 0 {
			tempfailRecovery = 0
		}
		if tempfailRecovery > 1 {
			tempfailRecovery = 1
		}

		retryWaste := provider.UnknownRate
		if retryWaste < 0 {
			retryWaste = 0
		}
		if retryWaste > 1 {
			retryWaste = 1
		}

		effectiveness = append(effectiveness, ProviderRetryEffectivenessSummary{
			Provider:            provider.Provider,
			Mode:                provider.Mode,
			Status:              provider.Status,
			TempfailRate:        provider.TempfailRate,
			TempfailRecoveryPct: tempfailRecovery * 100,
			RetryWastePct:       retryWaste * 100,
			AvgRetryAfter:       provider.AvgRetryAfter,
			Workers:             provider.Workers,
		})
	}

	return effectiveness
}

func providerQualityDriftFromHealth(
	health []ProviderHealthSummary,
	thresholds providerHealthThresholds,
) []ProviderQualityDriftSummary {
	drift := make([]ProviderQualityDriftSummary, 0, len(health))

	for _, provider := range health {
		unknownDelta := provider.UnknownRate - thresholds.UnknownWarn
		tempfailDelta := provider.TempfailRate - thresholds.TempfailWarn
		recommendation := "stable"

		if provider.Status == "warning" || provider.Status == "critical" {
			recommendation = "set_cautious"
		}
		if provider.Status == "critical" {
			recommendation = "rollback_candidate"
		}

		drift = append(drift, ProviderQualityDriftSummary{
			Provider:            provider.Provider,
			Status:              provider.Status,
			UnknownRate:         provider.UnknownRate,
			UnknownBaseline:     thresholds.UnknownWarn,
			UnknownDelta:        unknownDelta,
			TempfailRate:        provider.TempfailRate,
			TempfailBaseline:    thresholds.TempfailWarn,
			TempfailDelta:       tempfailDelta,
			PolicyBlockedRate:   provider.PolicyBlockedRate,
			Mode:                provider.Mode,
			Workers:             provider.Workers,
			DriftRecommendation: recommendation,
		})
	}

	return drift
}
