package main

import (
	"encoding/json"
	"net/http"

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

func decodeJSON(r *http.Request, target interface{}) error {
	decoder := json.NewDecoder(r.Body)
	decoder.DisallowUnknownFields()
	return decoder.Decode(target)
}
