package main

import (
	"context"
	"encoding/json"
	"fmt"
	"strconv"
	"time"

	"github.com/redis/go-redis/v9"
)

type Store struct {
	rdb          *redis.Client
	heartbeatTTL time.Duration
}

func NewStore(rdb *redis.Client, ttl time.Duration) *Store {
	return &Store{
		rdb:          rdb,
		heartbeatTTL: ttl,
	}
}

func (s *Store) UpsertHeartbeat(ctx context.Context, req HeartbeatRequest) (string, error) {
	if req.WorkerID == "" {
		return "", fmt.Errorf("worker_id is required")
	}
	status := normalizeStatus(req.Status)
	if status == "" {
		return "", fmt.Errorf("invalid status")
	}

	desiredKey := workerKey(req.WorkerID, "desired_state")
	desiredState, err := s.rdb.Get(ctx, desiredKey).Result()
	if err == redis.Nil || desiredState == "" {
		desiredState = "running"
	} else if err != nil {
		return "", err
	}

	meta := workerMeta{
		WorkerID:       req.WorkerID,
		Host:           req.Host,
		IPAddress:      req.IPAddress,
		Version:        req.Version,
		Pool:           req.Pool,
		Tags:           req.Tags,
		CurrentJobID:   req.CurrentJobID,
		CurrentChunkID: req.CurrentChunkID,
	}
	metaJSON, err := json.Marshal(meta)
	if err != nil {
		return "", err
	}
	metricsJSON := []byte("{}")
	if req.Metrics != nil {
		payload, marshalErr := json.Marshal(req.Metrics)
		if marshalErr != nil {
			return "", marshalErr
		}
		metricsJSON = payload
	}

	now := time.Now().UTC().Format(time.RFC3339)

	pipe := s.rdb.Pipeline()
	pipe.SAdd(ctx, "workers:active", req.WorkerID)
	pipe.Set(ctx, workerKey(req.WorkerID, "status"), status, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "heartbeat"), now, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "meta"), metaJSON, s.heartbeatTTL)
	pipe.Set(ctx, workerKey(req.WorkerID, "metrics"), metricsJSON, s.heartbeatTTL)
	if req.Pool != "" {
		pipe.Set(ctx, workerKey(req.WorkerID, "pool"), req.Pool, s.heartbeatTTL)
		pipe.SAdd(ctx, "pools:known", req.Pool)
	}
	_, err = pipe.Exec(ctx)
	if err != nil {
		return "", err
	}

	return desiredState, nil
}

func (s *Store) SetDesiredState(ctx context.Context, workerID string, state string) error {
	if workerID == "" {
		return fmt.Errorf("worker id is required")
	}
	normalized := normalizeDesiredState(state)
	if normalized == "" {
		return fmt.Errorf("invalid desired state")
	}
	return s.rdb.Set(ctx, workerKey(workerID, "desired_state"), normalized, 0).Err()
}

func (s *Store) GetWorkers(ctx context.Context) ([]WorkerSummary, error) {
	ids, err := s.rdb.SMembers(ctx, "workers:active").Result()
	if err != nil {
		return nil, err
	}

	results := make([]WorkerSummary, 0, len(ids))
	for _, id := range ids {
		heartbeat, err := s.rdb.Get(ctx, workerKey(id, "heartbeat")).Result()
		if err == redis.Nil {
			_ = s.rdb.SRem(ctx, "workers:active", id).Err()
			continue
		}
		if err != nil {
			return nil, err
		}

		status, _ := s.rdb.Get(ctx, workerKey(id, "status")).Result()
		desired, _ := s.rdb.Get(ctx, workerKey(id, "desired_state")).Result()
		if desired == "" {
			desired = "running"
		}

		metaJSON, _ := s.rdb.Get(ctx, workerKey(id, "meta")).Result()
		var meta workerMeta
		if metaJSON != "" {
			_ = json.Unmarshal([]byte(metaJSON), &meta)
		}

		pool := meta.Pool
		if pool == "" {
			pool, _ = s.rdb.Get(ctx, workerKey(id, "pool")).Result()
		}

		results = append(results, WorkerSummary{
			WorkerID:       id,
			Host:           meta.Host,
			IPAddress:      meta.IPAddress,
			Version:        meta.Version,
			Pool:           pool,
			Status:         defaultString(status, "unknown"),
			DesiredState:   desired,
			LastHeartbeat:  heartbeat,
			CurrentJobID:   meta.CurrentJobID,
			CurrentChunkID: meta.CurrentChunkID,
		})
	}

	return results, nil
}

func (s *Store) GetPools(ctx context.Context) ([]PoolSummary, error) {
	pools, err := s.rdb.SMembers(ctx, "pools:known").Result()
	if err != nil {
		return nil, err
	}

	workers, err := s.GetWorkers(ctx)
	if err != nil {
		return nil, err
	}

	onlineCounts := make(map[string]int)
	for _, worker := range workers {
		if worker.Pool == "" {
			continue
		}
		onlineCounts[worker.Pool]++
	}

	results := make([]PoolSummary, 0, len(pools))
	for _, pool := range pools {
		desired := 0
		value, err := s.rdb.Get(ctx, poolKey(pool, "desired_count")).Result()
		if err == nil && value != "" {
			if parsed, parseErr := parseInt(value); parseErr == nil {
				desired = parsed
			}
		}
		results = append(results, PoolSummary{
			Pool:    pool,
			Online:  onlineCounts[pool],
			Desired: desired,
		})
	}

	return results, nil
}

func (s *Store) SetPoolDesiredCount(ctx context.Context, pool string, desired int) error {
	if pool == "" {
		return fmt.Errorf("pool is required")
	}
	if desired < 0 {
		return fmt.Errorf("desired must be >= 0")
	}
	pipe := s.rdb.Pipeline()
	pipe.Set(ctx, poolKey(pool, "desired_count"), desired, 0)
	pipe.SAdd(ctx, "pools:known", pool)
	_, err := pipe.Exec(ctx)
	return err
}

func workerKey(workerID string, field string) string {
	return fmt.Sprintf("worker:%s:%s", workerID, field)
}

func poolKey(pool string, field string) string {
	return fmt.Sprintf("pool:%s:%s", pool, field)
}

func defaultString(value, fallback string) string {
	if value == "" {
		return fallback
	}
	return value
}

func normalizeStatus(status string) string {
	switch status {
	case "running", "paused", "draining", "stopped":
		return status
	case "":
		return "running"
	default:
		return ""
	}
}

func normalizeDesiredState(state string) string {
	switch state {
	case "running", "paused", "draining", "stopped":
		return state
	case "resume":
		return "running"
	default:
		return ""
	}
}

func parseInt(value string) (int, error) {
	parsed, err := strconv.Atoi(value)
	if err != nil {
		return 0, err
	}
	return parsed, nil
}
