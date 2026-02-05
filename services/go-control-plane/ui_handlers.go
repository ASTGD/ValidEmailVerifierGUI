package main

import (
	"net/http"
	"sort"
	"strconv"

	"github.com/go-chi/chi/v5"
)

type OverviewData struct {
	BasePageData
	WorkerCount    int
	PoolCount      int
	DesiredTotal   int
	Pools          []PoolSummary
	ChartLabels    []string
	ChartOnline    []int
	ChartDesired   []int
	HistoryLabels  []string
	HistoryWorkers []int
	HistoryDesired []int
	HasHistory     bool
}

type WorkersPageData struct {
	BasePageData
	WorkerCount int
	Workers     []WorkerSummary
}

type PoolsPageData struct {
	BasePageData
	PoolCount int
	Pools     []PoolSummary
}

func (s *Server) handleUIRedirect(w http.ResponseWriter, r *http.Request) {
	http.Redirect(w, r, "/verifier-engine-room/overview", http.StatusFound)
}

func (s *Server) handleUIOverview(w http.ResponseWriter, r *http.Request) {
	workers, _ := s.store.GetWorkers(r.Context())
	pools, _ := s.store.GetPools(r.Context())

	sort.Slice(pools, func(i, j int) bool {
		return pools[i].Pool < pools[j].Pool
	})

	desiredTotal := 0
	labels := make([]string, 0, len(pools))
	online := make([]int, 0, len(pools))
	desired := make([]int, 0, len(pools))

	for _, pool := range pools {
		labels = append(labels, pool.Pool)
		online = append(online, pool.Online)
		desired = append(desired, pool.Desired)
		desiredTotal += pool.Desired
	}

	data := OverviewData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Overview",
			Subtitle:        "Phase 2 dashboard",
			ActiveNav:       "overview",
			ContentTemplate: "overview",
			BasePath:        "/verifier-engine-room",
		},
		WorkerCount:  len(workers),
		PoolCount:    len(pools),
		DesiredTotal: desiredTotal,
		Pools:        pools,
		ChartLabels:  labels,
		ChartOnline:  online,
		ChartDesired: desired,
	}

	if s.snapshots != nil {
		points, err := s.snapshots.GetWorkerSnapshots(r.Context(), 120)
		if err == nil && len(points) > 0 {
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
	workers, err := s.store.GetWorkers(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	sort.Slice(workers, func(i, j int) bool {
		return workers[i].WorkerID < workers[j].WorkerID
	})

	data := WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
		},
		WorkerCount: len(workers),
		Workers:     workers,
	}

	s.views.Render(w, data)
}

func (s *Server) handleUIPools(w http.ResponseWriter, r *http.Request) {
	pools, err := s.store.GetPools(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	sort.Slice(pools, func(i, j int) bool {
		return pools[i].Pool < pools[j].Pool
	})

	data := PoolsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Pools",
			Subtitle:        "Scale worker pools",
			ActiveNav:       "pools",
			ContentTemplate: "pools",
			BasePath:        "/verifier-engine-room",
		},
		PoolCount: len(pools),
		Pools:     pools,
	}

	s.views.Render(w, data)
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

		http.Redirect(w, r, "/ui/workers", http.StatusSeeOther)
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

	http.Redirect(w, r, "/ui/pools", http.StatusSeeOther)
}
