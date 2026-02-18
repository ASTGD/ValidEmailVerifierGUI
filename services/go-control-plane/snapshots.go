package main

import (
	"context"
	"log"
	"time"
)

type SnapshotService struct {
	instanceID  string
	store       *Store
	snapshots   *SnapshotStore
	cfg         Config
	interval    time.Duration
	stopChannel chan struct{}
}

func NewSnapshotService(store *Store, snapshots *SnapshotStore, cfg Config, instanceID string) *SnapshotService {
	interval := cfg.SnapshotInterval
	if interval <= 0 {
		interval = 60 * time.Second
	}

	return &SnapshotService{
		instanceID:  instanceID,
		store:       store,
		snapshots:   snapshots,
		cfg:         cfg,
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

	if !s.shouldRun(ctx) {
		return
	}

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

func (s *SnapshotService) shouldRun(ctx context.Context) bool {
	if !s.cfg.LeaderLockEnabled {
		return true
	}

	ok, err := s.store.HoldLeaderLease(ctx, "snapshots", s.instanceID, s.cfg.LeaderLockTTL)
	if err != nil {
		log.Printf("snapshot: leader lock error: %v", err)
		return false
	}

	return ok
}
