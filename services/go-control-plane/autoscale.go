package main

import (
	"context"
	"log"
	"sort"
	"time"
)

type AutoScaleService struct {
	store       *Store
	cfg         Config
	instanceID  string
	stopChannel chan struct{}
	ticker      *time.Ticker
	lastAction  map[string]time.Time
	lastRunAt   time.Time
}

func NewAutoScaleService(store *Store, cfg Config, instanceID string) *AutoScaleService {
	return &AutoScaleService{
		store:       store,
		cfg:         cfg,
		instanceID:  instanceID,
		stopChannel: make(chan struct{}),
		lastAction:  map[string]time.Time{},
		ticker:      time.NewTicker(5 * time.Second),
	}
}

func (s *AutoScaleService) Start() {
	go func() {
		defer s.ticker.Stop()

		for {
			select {
			case <-s.ticker.C:
				s.run()
			case <-s.stopChannel:
				return
			}
		}
	}()
}

func (s *AutoScaleService) Stop() {
	close(s.stopChannel)
}

func (s *AutoScaleService) run() {
	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	defaults := defaultRuntimeSettings(s.cfg)
	settings, err := s.store.GetRuntimeSettings(ctx, defaults)
	if err != nil {
		settings = defaults
	}
	if !settings.AutoscaleEnabled {
		return
	}
	if !s.shouldRun(ctx) {
		return
	}
	if !s.runDue(settings) {
		return
	}

	pools, err := s.store.GetPools(ctx)
	if err != nil {
		log.Printf("autoscale: failed to load pools: %v", err)
		return
	}
	if len(pools) == 0 {
		return
	}

	workers, err := s.store.GetWorkers(ctx)
	if err != nil {
		log.Printf("autoscale: failed to load workers: %v", err)
		return
	}

	busyByPool := map[string]int{}
	for _, worker := range workers {
		if worker.Pool == "" {
			continue
		}
		if worker.CurrentChunkID != "" || worker.CurrentJobID != "" {
			busyByPool[worker.Pool]++
		}
	}

	minDesired := settings.AutoscaleMinDesired
	if minDesired < 0 {
		minDesired = 0
	}
	maxDesired := settings.AutoscaleMaxDesired
	if maxDesired < minDesired {
		maxDesired = minDesired
	}

	canary := settings.AutoscaleCanaryPercent
	if canary <= 0 {
		return
	}
	if canary > 100 {
		canary = 100
	}

	sort.Slice(pools, func(i, j int) bool {
		return pools[i].Pool < pools[j].Pool
	})

	targetPools := canaryLimit(len(pools), canary)
	for i := 0; i < targetPools; i++ {
		pool := pools[i]
		if pool.Pool == "" {
			continue
		}

		busy := busyByPool[pool.Pool]
		target := calculatePoolTargetDesired(pool, busy, minDesired, maxDesired)
		onlineForRatio := pool.Online
		if onlineForRatio < 1 {
			onlineForRatio = 1
		}
		busyRatio := float64(busy) / float64(onlineForRatio)

		if target == pool.Desired {
			continue
		}

		if !s.cooldownElapsed(pool.Pool, settings) {
			continue
		}

		if err := s.store.SetPoolDesiredCount(ctx, pool.Pool, target); err != nil {
			log.Printf("autoscale: failed to set desired for pool %s: %v", pool.Pool, err)
			continue
		}

		s.lastAction[pool.Pool] = time.Now().UTC()
		log.Printf(
			"autoscale: adjusted pool=%s desired=%d->%d busy_ratio=%.2f health=%.2f canary=%d",
			pool.Pool,
			pool.Desired,
			target,
			busyRatio,
			pool.HealthScore,
			canary,
		)
	}
}

func (s *AutoScaleService) runDue(settings RuntimeSettings) bool {
	intervalSeconds := settings.AutoscaleIntervalSecond
	if intervalSeconds <= 0 {
		intervalSeconds = 30
	}

	interval := time.Duration(intervalSeconds) * time.Second
	now := time.Now().UTC()
	if !s.lastRunAt.IsZero() && now.Sub(s.lastRunAt) < interval {
		return false
	}

	s.lastRunAt = now
	return true
}

func (s *AutoScaleService) shouldRun(ctx context.Context) bool {
	if !s.cfg.LeaderLockEnabled {
		return true
	}

	ok, err := s.store.HoldLeaderLease(ctx, "autoscale", s.instanceID, s.cfg.LeaderLockTTL)
	if err != nil {
		log.Printf("autoscale: leader lock error: %v", err)
		return false
	}

	return ok
}

func (s *AutoScaleService) cooldownElapsed(pool string, settings RuntimeSettings) bool {
	cooldown := time.Duration(settings.AutoscaleCooldownSecond) * time.Second
	if cooldown <= 0 {
		cooldown = 120 * time.Second
	}

	last, ok := s.lastAction[pool]
	if !ok {
		return true
	}

	return time.Since(last) >= cooldown
}

func canaryLimit(total int, percent int) int {
	if total <= 0 {
		return 0
	}
	if percent >= 100 {
		return total
	}
	if percent <= 0 {
		return 0
	}

	limit := (total * percent) / 100
	if limit < 1 {
		return 1
	}

	return limit
}

func calculatePoolTargetDesired(pool PoolSummary, busy, minDesired, maxDesired int) int {
	target := pool.Desired
	if target < minDesired {
		target = minDesired
	}

	onlineForRatio := pool.Online
	if onlineForRatio < 1 {
		onlineForRatio = 1
	}

	busyRatio := float64(busy) / float64(onlineForRatio)
	healthScore := pool.HealthScore
	underCapacity := pool.Online < pool.Desired

	switch {
	case healthScore > 0 && healthScore < 0.20 && target > minDesired:
		target--
	case busyRatio >= 0.85 && target < maxDesired && healthScore >= 0.25:
		target++
	case busyRatio <= 0.25 && target > minDesired && !underCapacity && pool.Online >= target:
		target--
	}

	return target
}
