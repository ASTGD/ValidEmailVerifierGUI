package main

import (
	"context"
	"log"
	"time"
)

type SnapshotService struct {
	store       *Store
	snapshots   *SnapshotStore
	interval    time.Duration
	stopChannel chan struct{}
}

func NewSnapshotService(store *Store, snapshots *SnapshotStore, interval time.Duration) *SnapshotService {
	return &SnapshotService{
		store:       store,
		snapshots:   snapshots,
		interval:    interval,
		stopChannel: make(chan struct{}),
	}
}

func (s *SnapshotService) Start() {
	if s.snapshots == nil || s.interval <= 0 {
		return
	}

	ticker := time.NewTicker(s.interval)
	go func() {
		defer ticker.Stop()
		for {
			select {
			case <-ticker.C:
				s.capture()
			case <-s.stopChannel:
				return
			}
		}
	}()

	// Capture once on startup.
	s.capture()
}

func (s *SnapshotService) Stop() {
	close(s.stopChannel)
}

func (s *SnapshotService) capture() {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("snapshot: failed to load workers: %v", err)
		return
	}

	pools, err := s.store.GetPools(ctx)
	if err != nil {
		log.Printf("snapshot: failed to load pools: %v", err)
		return
	}

	desiredTotal := 0
	for _, pool := range pools {
		desiredTotal += pool.Desired
	}

	snapshot := WorkerSnapshot{
		CapturedAt:   time.Now().UTC(),
		TotalWorkers: len(workers),
		PoolCount:    len(pools),
		DesiredTotal: desiredTotal,
	}

	if err := s.snapshots.SaveWorkerSnapshot(ctx, snapshot); err != nil {
		log.Printf("snapshot: failed to save worker snapshot: %v", err)
		return
	}

	if err := s.snapshots.SavePoolSnapshots(ctx, pools, snapshot.CapturedAt); err != nil {
		log.Printf("snapshot: failed to save pool snapshots: %v", err)
		return
	}
}
