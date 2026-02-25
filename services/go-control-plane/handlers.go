package main

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"net/http"
	"sort"
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

func (s *Server) handleProvidersAccuracyCalibration(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderAccuracyCalibrationResponse{
		Data: providerAccuracyCalibrationFromHealth(stats.ProviderHealth),
	})
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

func (s *Server) handleProvidersUnknownClusters(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderUnknownClusterResponse{
		Data: providerUnknownClustersFromWorkers(stats.Workers),
	})
}

func (s *Server) handleProvidersUnknownReasons(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, ProviderUnknownReasonResponse{
		Data: providerUnknownReasonConcentrationFromWorkers(stats.Workers),
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

	data := stats.ProviderPolicies
	poolSlug := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("pool")))
	if poolSlug == "" {
		poolSlug = strings.ToLower(strings.TrimSpace(r.URL.Query().Get("worker_pool")))
	}
	if poolSlug != "" {
		data.Modes = s.applyPoolProviderModes(r.Context(), data.Modes, poolSlug)
	}

	writeJSON(w, http.StatusOK, ProviderPoliciesResponse{Data: data})
}

func (s *Server) applyPoolProviderModes(ctx context.Context, modes []ProviderModeState, poolSlug string) []ProviderModeState {
	if s.laravelEngineClient == nil {
		return modes
	}

	pools, err := s.laravelEngineClient.ListPools(ctx)
	if err != nil {
		return modes
	}

	profiles := map[string]string{}
	for _, pool := range pools {
		if !pool.IsActive {
			continue
		}
		if strings.ToLower(strings.TrimSpace(pool.Slug)) != poolSlug {
			continue
		}

		for provider, profile := range pool.ProviderProfiles {
			normalizedProvider := strings.ToLower(strings.TrimSpace(provider))
			switch normalizedProvider {
			case "generic", "gmail", "microsoft", "yahoo":
				profiles[normalizedProvider] = providerModeFromPoolProfile(profile)
			}
		}
		break
	}

	if len(profiles) == 0 {
		return modes
	}

	output := make([]ProviderModeState, 0, len(modes)+len(profiles))
	seen := map[string]struct{}{}
	for _, mode := range modes {
		provider := strings.ToLower(strings.TrimSpace(mode.Provider))
		if provider == "" {
			continue
		}
		seen[provider] = struct{}{}

		overrideMode, hasOverride := profiles[provider]
		if !hasOverride || strings.EqualFold(strings.TrimSpace(mode.Source), "manual") {
			output = append(output, mode)
			continue
		}

		updated := mode
		updated.Mode = overrideMode
		updated.Source = "pool_profile"
		output = append(output, updated)
	}

	for provider, overrideMode := range profiles {
		if _, exists := seen[provider]; exists {
			continue
		}
		output = append(output, ProviderModeState{
			Provider: provider,
			Mode:     overrideMode,
			Source:   "pool_profile",
		})
	}

	sort.SliceStable(output, func(i, j int) bool {
		return output[i].Provider < output[j].Provider
	})

	return output
}

func providerModeFromPoolProfile(profile string) string {
	switch strings.ToLower(strings.TrimSpace(profile)) {
	case "low_hit":
		return "cautious"
	case "warmup":
		return "degraded_probe"
	default:
		return "normal"
	}
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

func (s *Server) handleRoutingEffectiveness(w http.ResponseWriter, r *http.Request) {
	s.handleProbeRoutingEffectiveness(w, r)
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

	if err := s.guardPolicyPromoteByShadowCompare(r.Context(), payload.Version); err != nil {
		writeError(w, http.StatusUnprocessableEntity, err.Error())
		return
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

func (s *Server) handlePolicyShadowEvaluate(w http.ResponseWriter, r *http.Request) {
	var payload PolicyShadowEvaluateRequest
	if err := decodeJSON(r, &payload); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}

	candidateVersion := strings.TrimSpace(payload.CandidateVersion)
	if candidateVersion == "" {
		writeError(w, http.StatusBadRequest, "candidate_version is required")
		return
	}

	if strings.TrimSpace(payload.TriggeredBy) == "" {
		payload.TriggeredBy = "api"
	}

	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	_, activeVersion, err := s.store.ListSMTPPolicyVersions(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	thresholds := thresholdsFromRuntimeSettings(stats.Settings)
	drift := providerQualityDriftFromHealth(stats.ProviderHealth, thresholds)
	filteredDrift := filterProviderDrift(drift, payload.Providers)

	results := make([]PolicyShadowEvaluateResult, 0, len(filteredDrift))
	for _, row := range filteredDrift {
		tempfailRecovery := 1.0 - row.UnknownRate
		if tempfailRecovery < 0 {
			tempfailRecovery = 0
		}
		if tempfailRecovery > 1 {
			tempfailRecovery = 1
		}

		results = append(results, PolicyShadowEvaluateResult{
			Provider:            row.Provider,
			UnknownRate:         row.UnknownRate,
			TempfailRecoveryPct: tempfailRecovery * 100,
			PolicyBlockedRate:   row.PolicyBlockedRate,
			Recommendation:      row.DriftRecommendation,
		})
	}

	sort.Slice(results, func(i, j int) bool {
		return results[i].Provider < results[j].Provider
	})

	response := PolicyShadowEvaluateResponse{}
	response.Data.CandidateVersion = candidateVersion
	response.Data.ActiveVersion = activeVersion
	response.Data.TriggeredBy = payload.TriggeredBy
	response.Data.Notes = strings.TrimSpace(payload.Notes)
	response.Data.EvaluatedAt = time.Now().UTC().Format(time.RFC3339)
	response.Data.Results = results

	summary := buildPolicyShadowRunSummary(results)
	providers := make([]string, 0, len(results))
	for _, result := range results {
		provider := strings.ToLower(strings.TrimSpace(result.Provider))
		if provider == "" {
			continue
		}
		providers = append(providers, provider)
	}
	shadowRun := PolicyShadowRunRecord{
		RunUUID:          newPolicyShadowRunUUID(),
		CandidateVersion: candidateVersion,
		ActiveVersion:    activeVersion,
		TriggeredBy:      payload.TriggeredBy,
		Notes:            strings.TrimSpace(payload.Notes),
		EvaluatedAt:      response.Data.EvaluatedAt,
		Providers:        providers,
		Results:          results,
		Summary:          summary,
	}
	if persistErr := s.store.AppendPolicyShadowRun(r.Context(), shadowRun); persistErr != nil {
		writeError(w, http.StatusInternalServerError, persistErr.Error())
		return
	}

	writeJSON(w, http.StatusOK, response)
}

func (s *Server) handlePolicyShadowRuns(w http.ResponseWriter, r *http.Request) {
	limit := 20
	if value := strings.TrimSpace(r.URL.Query().Get("limit")); value != "" {
		if parsed, err := strconv.Atoi(value); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	runs, err := s.store.ListPolicyShadowRuns(r.Context(), limit)
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	writeJSON(w, http.StatusOK, PolicyShadowRunListResponse{Data: runs})
}

func (s *Server) handlePolicyShadowCompare(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	candidateVersion := strings.TrimSpace(r.URL.Query().Get("candidate_version"))
	compare := s.buildPolicyShadowCompare(stats, candidateVersion)
	writeJSON(w, http.StatusOK, PolicyShadowCompareResponse{Data: compare})
}

func (s *Server) handleDecisionsTrace(w http.ResponseWriter, r *http.Request) {
	if s.laravelEngineClient == nil {
		writeError(w, http.StatusServiceUnavailable, "laravel internal api is not configured")
		return
	}

	query := r.URL.Query()
	filter := LaravelDecisionTraceFilter{
		Provider:      strings.TrimSpace(query.Get("provider")),
		DecisionClass: strings.TrimSpace(query.Get("decision_class")),
		ReasonTag:     strings.TrimSpace(query.Get("reason_tag")),
		PolicyVersion: strings.TrimSpace(query.Get("policy_version")),
		Limit:         25,
	}
	if value := strings.TrimSpace(query.Get("limit")); value != "" {
		parsed, parseErr := strconv.Atoi(value)
		if parseErr != nil || parsed < 1 || parsed > 100 {
			writeError(w, http.StatusBadRequest, "limit must be between 1 and 100")
			return
		}
		filter.Limit = parsed
	}
	if value := strings.TrimSpace(query.Get("before_id")); value != "" {
		parsed, parseErr := strconv.ParseInt(value, 10, 64)
		if parseErr != nil || parsed < 1 {
			writeError(w, http.StatusBadRequest, "before_id must be a positive integer")
			return
		}
		filter.BeforeID = parsed
	}

	result, err := s.laravelEngineClient.ListDecisionTraces(r.Context(), filter)
	if err != nil {
		laravelErr := &LaravelAPIError{}
		if errors.As(err, &laravelErr) {
			status := laravelErr.StatusCode
			if status < 400 {
				status = http.StatusBadGateway
			}
			writeError(w, status, laravelErr.Error())
			return
		}
		writeError(w, http.StatusBadGateway, err.Error())
		return
	}

	response := DecisionTraceResponse{
		Data: result.Records,
	}
	response.Meta.Limit = filter.Limit
	response.Meta.NextBeforeID = result.NextBeforeID

	writeJSON(w, http.StatusOK, response)
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

func providerAccuracyCalibrationFromHealth(health []ProviderHealthSummary) []ProviderAccuracyCalibrationSummary {
	calibrations := make([]ProviderAccuracyCalibrationSummary, 0, len(health))

	for _, provider := range health {
		tempfailRecovery := 1.0 - provider.UnknownRate
		if tempfailRecovery < 0 {
			tempfailRecovery = 0
		}
		if tempfailRecovery > 1 {
			tempfailRecovery = 1
		}

		confidence := "high"
		if provider.Workers < 2 {
			confidence = "low"
		} else if provider.Workers < 4 {
			confidence = "medium"
		}

		calibrations = append(calibrations, ProviderAccuracyCalibrationSummary{
			Provider:              provider.Provider,
			Workers:               provider.Workers,
			UnknownRate:           provider.UnknownRate,
			TempfailRecoveryPct:   tempfailRecovery * 100,
			PolicyBlockedRate:     provider.PolicyBlockedRate,
			CalibrationConfidence: confidence,
		})
	}

	sort.Slice(calibrations, func(i, j int) bool {
		return calibrations[i].Provider < calibrations[j].Provider
	})

	return calibrations
}

func providerUnknownClustersFromWorkers(workers []WorkerSummary) []ProviderUnknownClusterSummary {
	type providerTagKey struct {
		provider string
		tag      string
	}

	counts := make(map[providerTagKey]int64)
	sampleWorkers := make(map[providerTagKey]map[string]struct{})

	for _, worker := range workers {
		if len(worker.UnknownReasonTags) == 0 {
			continue
		}

		uniqueProviders := make(map[string]struct{}, len(worker.ProviderMetrics))
		for _, metric := range worker.ProviderMetrics {
			name := strings.ToLower(strings.TrimSpace(metric.Provider))
			if name == "" {
				continue
			}
			uniqueProviders[name] = struct{}{}
		}

		providers := make([]string, 0, 1)
		switch len(uniqueProviders) {
		case 0:
			providers = append(providers, "generic")
		case 1:
			for provider := range uniqueProviders {
				providers = append(providers, provider)
			}
		default:
			// Unknown reason tags are worker-level totals, so keep multi-provider workers generic.
			providers = append(providers, "generic")
		}

		for tag, value := range worker.UnknownReasonTags {
			normalizedTag := strings.ToLower(strings.TrimSpace(tag))
			if normalizedTag == "" || value <= 0 {
				continue
			}

			for _, provider := range providers {
				key := providerTagKey{provider: provider, tag: normalizedTag}
				counts[key] += value
				if sampleWorkers[key] == nil {
					sampleWorkers[key] = make(map[string]struct{})
				}
				sampleWorkers[key][worker.WorkerID] = struct{}{}
			}
		}
	}

	results := make([]ProviderUnknownClusterSummary, 0, len(counts))
	for key, value := range counts {
		results = append(results, ProviderUnknownClusterSummary{
			Provider:      key.provider,
			Tag:           key.tag,
			Count:         value,
			SampleWorkers: len(sampleWorkers[key]),
		})
	}

	sort.Slice(results, func(i, j int) bool {
		if results[i].Provider == results[j].Provider {
			if results[i].Count == results[j].Count {
				return results[i].Tag < results[j].Tag
			}
			return results[i].Count > results[j].Count
		}
		return results[i].Provider < results[j].Provider
	})

	if len(results) > 100 {
		return results[:100]
	}

	return results
}

func providerUnknownReasonConcentrationFromWorkers(workers []WorkerSummary) []ProviderUnknownReasonSummary {
	clusters := providerUnknownClustersFromWorkers(workers)
	if len(clusters) == 0 {
		return []ProviderUnknownReasonSummary{}
	}

	providerTotals := make(map[string]int64, len(clusters))
	for _, cluster := range clusters {
		if cluster.Count <= 0 {
			continue
		}
		providerTotals[cluster.Provider] += cluster.Count
	}

	reasons := make([]ProviderUnknownReasonSummary, 0, len(clusters))
	for _, cluster := range clusters {
		if cluster.Count <= 0 {
			continue
		}

		total := providerTotals[cluster.Provider]
		share := 0.0
		if total > 0 {
			share = float64(cluster.Count) / float64(total)
		}

		reasons = append(reasons, ProviderUnknownReasonSummary{
			Provider:      cluster.Provider,
			Tag:           cluster.Tag,
			Count:         cluster.Count,
			Share:         share,
			SharePercent:  share * 100,
			SampleWorkers: cluster.SampleWorkers,
		})
	}

	sort.Slice(reasons, func(i, j int) bool {
		if reasons[i].Provider == reasons[j].Provider {
			if reasons[i].Share == reasons[j].Share {
				if reasons[i].Count == reasons[j].Count {
					return reasons[i].Tag < reasons[j].Tag
				}
				return reasons[i].Count > reasons[j].Count
			}
			return reasons[i].Share > reasons[j].Share
		}
		return reasons[i].Provider < reasons[j].Provider
	})

	if len(reasons) > 100 {
		return reasons[:100]
	}

	return reasons
}

func (s *Server) guardPolicyPromoteByShadowCompare(ctx context.Context, candidateVersion string) error {
	if !s.cfg.PolicyShadowPromoteGateEnforced {
		return nil
	}

	stats, err := s.collectControlPlaneStats(ctx)
	if err != nil {
		return err
	}

	compare := s.buildPolicyShadowCompare(stats, candidateVersion)
	if compare.Eligible {
		return nil
	}

	if len(compare.Violations) == 0 {
		return errors.New("policy promotion blocked by shadow compare gate")
	}

	return errors.New("policy promotion blocked: " + strings.Join(compare.Violations, "; "))
}

func (s *Server) buildPolicyShadowCompare(stats ControlPlaneStats, candidateVersion string) PolicyShadowCompareData {
	unknownThreshold := stats.Settings.PolicyCanaryUnknownRegressionThreshold
	if unknownThreshold <= 0 {
		unknownThreshold = s.cfg.PolicyCanaryUnknownRegressionThreshold
	}
	if unknownThreshold <= 0 {
		unknownThreshold = 0.05
	}

	tempfailDropThreshold := stats.Settings.PolicyCanaryTempfailRecoveryDropThreshold
	if tempfailDropThreshold <= 0 {
		tempfailDropThreshold = s.cfg.PolicyCanaryTempfailRecoveryDropThreshold
	}
	if tempfailDropThreshold <= 0 {
		tempfailDropThreshold = 0.10
	}

	policyBlockSpikeThreshold := stats.Settings.PolicyCanaryPolicyBlockSpikeThreshold
	if policyBlockSpikeThreshold <= 0 {
		policyBlockSpikeThreshold = s.cfg.PolicyCanaryPolicyBlockSpikeThreshold
	}
	if policyBlockSpikeThreshold <= 0 {
		policyBlockSpikeThreshold = 0.10
	}

	compare := PolicyShadowCompareData{
		CandidateVersion: strings.TrimSpace(candidateVersion),
		ActiveVersion:    strings.TrimSpace(stats.ProviderPolicies.ActiveVersion),
		Eligible:         true,
		GateMode:         "warn_only",
		Thresholds: PolicyShadowCompareThresholds{
			UnknownRegressionThreshold:    unknownThreshold,
			TempfailRecoveryDropThreshold: tempfailDropThreshold,
			PolicyBlockSpikeThreshold:     policyBlockSpikeThreshold,
		},
		Violations: []string{},
		Providers:  []PolicyShadowCompareProviderSummary{},
	}
	if s.cfg.PolicyShadowPromoteGateEnforced {
		compare.GateMode = "enforce"
	}

	if strings.TrimSpace(compare.CandidateVersion) == "" && len(stats.PolicyShadowRuns) > 0 {
		compare.CandidateVersion = strings.TrimSpace(stats.PolicyShadowRuns[0].CandidateVersion)
		if compare.ActiveVersion == "" {
			compare.ActiveVersion = strings.TrimSpace(stats.PolicyShadowRuns[0].ActiveVersion)
		}
	}

	if strings.TrimSpace(compare.CandidateVersion) == "" {
		compare.Eligible = false
		compare.Violations = append(compare.Violations, "candidate_version_missing")
		return compare
	}

	latestRun := latestShadowRunForCandidate(stats.PolicyShadowRuns, compare.CandidateVersion, compare.ActiveVersion)
	if latestRun == nil {
		compare.Eligible = false
		compare.Violations = append(compare.Violations, "shadow_run_missing")
		return compare
	}

	compare.EvaluatedAt = strings.TrimSpace(latestRun.EvaluatedAt)
	liveByProvider := make(map[string]ProviderHealthSummary, len(stats.ProviderHealth))
	for _, provider := range stats.ProviderHealth {
		name := strings.ToLower(strings.TrimSpace(provider.Provider))
		if name == "" {
			continue
		}
		liveByProvider[name] = provider
	}

	tempfailDropThresholdPct := tempfailDropThreshold * 100

	for _, result := range latestRun.Results {
		provider := strings.ToLower(strings.TrimSpace(result.Provider))
		if provider == "" {
			continue
		}

		live, hasLive := liveByProvider[provider]
		liveUnknownRate := live.UnknownRate
		livePolicyBlockedRate := live.PolicyBlockedRate
		if !hasLive {
			liveUnknownRate = stats.Settings.ProviderUnknownWarnRate
			if liveUnknownRate <= 0 {
				liveUnknownRate = 0.20
			}
			livePolicyBlockedRate = 0
		}

		liveTempfailRecovery := 1.0 - liveUnknownRate
		if liveTempfailRecovery < 0 {
			liveTempfailRecovery = 0
		}
		if liveTempfailRecovery > 1 {
			liveTempfailRecovery = 1
		}
		liveTempfailRecoveryPct := liveTempfailRecovery * 100

		row := PolicyShadowCompareProviderSummary{
			Provider:               provider,
			UnknownRateShadow:      result.UnknownRate,
			UnknownRateLive:        liveUnknownRate,
			UnknownDelta:           result.UnknownRate - liveUnknownRate,
			TempfailRecoveryShadow: result.TempfailRecoveryPct,
			TempfailRecoveryLive:   liveTempfailRecoveryPct,
			TempfailRecoveryDelta:  result.TempfailRecoveryPct - liveTempfailRecoveryPct,
			PolicyBlockedShadow:    result.PolicyBlockedRate,
			PolicyBlockedLive:      livePolicyBlockedRate,
			PolicyBlockedDelta:     result.PolicyBlockedRate - livePolicyBlockedRate,
			Recommendation:         strings.TrimSpace(result.Recommendation),
		}
		compare.Providers = append(compare.Providers, row)

		if row.UnknownDelta > unknownThreshold {
			compare.Eligible = false
			compare.Violations = append(compare.Violations, provider+":unknown_delta_exceeded")
		}
		if row.TempfailRecoveryDelta < -tempfailDropThresholdPct {
			compare.Eligible = false
			compare.Violations = append(compare.Violations, provider+":tempfail_recovery_drop_exceeded")
		}
		if row.PolicyBlockedDelta > policyBlockSpikeThreshold {
			compare.Eligible = false
			compare.Violations = append(compare.Violations, provider+":policy_block_delta_exceeded")
		}
		if strings.EqualFold(row.Recommendation, "rollback_candidate") {
			compare.Eligible = false
			compare.Violations = append(compare.Violations, provider+":rollback_candidate")
		}
	}

	sort.Strings(compare.Violations)
	sort.Slice(compare.Providers, func(i, j int) bool {
		return compare.Providers[i].Provider < compare.Providers[j].Provider
	})

	if len(compare.Providers) == 0 {
		compare.Eligible = false
		compare.Violations = append(compare.Violations, "shadow_run_empty")
	}

	return compare
}

func latestShadowRunForCandidate(runs []PolicyShadowRunRecord, candidateVersion string, activeVersion string) *PolicyShadowRunRecord {
	candidateVersion = strings.TrimSpace(candidateVersion)
	activeVersion = strings.TrimSpace(activeVersion)
	for index := range runs {
		run := &runs[index]
		if strings.TrimSpace(run.CandidateVersion) != candidateVersion {
			continue
		}
		if activeVersion != "" && strings.TrimSpace(run.ActiveVersion) != "" && strings.TrimSpace(run.ActiveVersion) != activeVersion {
			continue
		}
		return run
	}

	return nil
}

func filterProviderDrift(
	drift []ProviderQualityDriftSummary,
	providers []string,
) []ProviderQualityDriftSummary {
	if len(providers) == 0 {
		return drift
	}

	allowed := make(map[string]struct{}, len(providers))
	for _, provider := range providers {
		normalized := strings.ToLower(strings.TrimSpace(provider))
		if normalized == "" {
			continue
		}
		allowed[normalized] = struct{}{}
	}
	if len(allowed) == 0 {
		return drift
	}

	filtered := make([]ProviderQualityDriftSummary, 0, len(drift))
	for _, row := range drift {
		if _, ok := allowed[strings.ToLower(strings.TrimSpace(row.Provider))]; ok {
			filtered = append(filtered, row)
		}
	}

	return filtered
}

func buildPolicyShadowRunSummary(results []PolicyShadowEvaluateResult) PolicyShadowRunSummary {
	summary := PolicyShadowRunSummary{
		HighestRiskRecommendation: "stable",
	}
	if len(results) == 0 {
		return summary
	}

	var unknownSum float64
	var tempfailRecoverySum float64
	var policyBlockedSum float64
	highestSeverity := 0
	for _, result := range results {
		unknownSum += result.UnknownRate
		tempfailRecoverySum += result.TempfailRecoveryPct
		policyBlockedSum += result.PolicyBlockedRate

		severity := recommendationSeverity(result.Recommendation)
		if severity > highestSeverity {
			highestSeverity = severity
			summary.HighestRiskRecommendation = result.Recommendation
		}
	}

	denominator := float64(len(results))
	summary.ProviderCount = len(results)
	summary.UnknownRateAvg = unknownSum / denominator
	summary.TempfailRecoveryPctAvg = tempfailRecoverySum / denominator
	summary.PolicyBlockedRateAvg = policyBlockedSum / denominator

	return summary
}

func recommendationSeverity(recommendation string) int {
	switch strings.ToLower(strings.TrimSpace(recommendation)) {
	case "rollback_candidate":
		return 3
	case "set_cautious":
		return 2
	case "stable":
		return 1
	default:
		return 0
	}
}

func newPolicyShadowRunUUID() string {
	buffer := make([]byte, 16)
	if _, err := rand.Read(buffer); err != nil {
		return strconv.FormatInt(time.Now().UTC().UnixNano(), 10)
	}

	hexValue := hex.EncodeToString(buffer)
	return strings.Join([]string{
		hexValue[0:8],
		hexValue[8:12],
		hexValue[12:16],
		hexValue[16:20],
		hexValue[20:32],
	}, "-")
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
