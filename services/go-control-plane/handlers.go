package main

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
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
