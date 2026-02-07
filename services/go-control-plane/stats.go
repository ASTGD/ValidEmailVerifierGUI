package main

import (
	"context"
	"sort"
)

type ControlPlaneStats struct {
	Workers          []WorkerSummary
	Pools            []PoolSummary
	WorkerCount      int
	PoolCount        int
	DesiredTotal     int
	ErrorRateTotal   float64
	ErrorRateAverage float64
	WorkerErrorRates map[string]float64
	Settings         RuntimeSettings
}

func (s *Server) collectControlPlaneStats(ctx context.Context) (ControlPlaneStats, error) {
	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		return ControlPlaneStats{}, err
	}

	pools, err := s.store.GetPools(ctx)
	if err != nil {
		return ControlPlaneStats{}, err
	}

	sort.Slice(workers, func(i, j int) bool {
		return workers[i].WorkerID < workers[j].WorkerID
	})

	sort.Slice(pools, func(i, j int) bool {
		return pools[i].Pool < pools[j].Pool
	})

	desiredTotal := 0
	for _, pool := range pools {
		desiredTotal += pool.Desired
	}

	workerErrorRates := make(map[string]float64, len(workers))
	errorTotal := 0.0
	for _, worker := range workers {
		metrics, metricsErr := s.store.GetWorkerMetrics(ctx, worker.WorkerID)
		if metricsErr != nil || metrics == nil {
			workerErrorRates[worker.WorkerID] = 0
			continue
		}

		workerErrorRates[worker.WorkerID] = metrics.ErrorsPerMin
		errorTotal += metrics.ErrorsPerMin
	}

	errorAvg := 0.0
	if len(workers) > 0 {
		errorAvg = errorTotal / float64(len(workers))
	}

	defaults := defaultRuntimeSettings(s.cfg)
	settings, settingsErr := s.store.GetRuntimeSettings(ctx, defaults)
	if settingsErr != nil {
		settings = defaults
	}

	return ControlPlaneStats{
		Workers:          workers,
		Pools:            pools,
		WorkerCount:      len(workers),
		PoolCount:        len(pools),
		DesiredTotal:     desiredTotal,
		ErrorRateTotal:   errorTotal,
		ErrorRateAverage: errorAvg,
		WorkerErrorRates: workerErrorRates,
		Settings:         settings,
	}, nil
}
