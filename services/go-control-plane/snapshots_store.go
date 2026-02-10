package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"time"
)

type SnapshotStore struct {
	db *sql.DB
}

type WorkerSnapshot struct {
	CapturedAt   time.Time
	TotalWorkers int
	PoolCount    int
	DesiredTotal int
}

type WorkerSnapshotPoint struct {
	CapturedAt   time.Time
	TotalWorkers int
	DesiredTotal int
}

type AlertRecord struct {
	Type      string    `json:"type"`
	Severity  string    `json:"severity"`
	Message   string    `json:"message"`
	Context   string    `json:"context"`
	CreatedAt time.Time `json:"created_at"`
}

func NewSnapshotStore(db *sql.DB) *SnapshotStore {
	return &SnapshotStore{db: db}
}

func (s *SnapshotStore) Ping(ctx context.Context) error {
	if s == nil || s.db == nil {
		return nil
	}

	return s.db.PingContext(ctx)
}

func (s *SnapshotStore) SaveWorkerSnapshot(ctx context.Context, snapshot WorkerSnapshot) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT INTO go_worker_snapshots (captured_at, total_workers, pool_count, desired_total, created_at)
		VALUES (?, ?, ?, ?, ?)
	`, snapshot.CapturedAt, snapshot.TotalWorkers, snapshot.PoolCount, snapshot.DesiredTotal, snapshot.CapturedAt)
	return err
}

func (s *SnapshotStore) SavePoolSnapshots(ctx context.Context, pools []PoolSummary, capturedAt time.Time) error {
	if len(pools) == 0 {
		return nil
	}

	stmt, err := s.db.PrepareContext(ctx, `
		INSERT INTO go_pool_snapshots (captured_at, pool, online, desired, created_at)
		VALUES (?, ?, ?, ?, ?)
	`)
	if err != nil {
		return err
	}
	defer stmt.Close()

	for _, pool := range pools {
		if _, err := stmt.ExecContext(ctx, capturedAt, pool.Pool, pool.Online, pool.Desired, capturedAt); err != nil {
			return err
		}
	}

	return nil
}

func (s *SnapshotStore) GetWorkerSnapshots(ctx context.Context, limit int) ([]WorkerSnapshotPoint, error) {
	if limit <= 0 {
		limit = 60
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT captured_at, total_workers, desired_total
		FROM go_worker_snapshots
		ORDER BY captured_at DESC
		LIMIT ?
	`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var points []WorkerSnapshotPoint
	for rows.Next() {
		var point WorkerSnapshotPoint
		if err := rows.Scan(&point.CapturedAt, &point.TotalWorkers, &point.DesiredTotal); err != nil {
			return nil, err
		}
		points = append(points, point)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}

	// reverse to ascending order
	for i, j := 0, len(points)-1; i < j; i, j = i+1, j-1 {
		points[i], points[j] = points[j], points[i]
	}

	return points, nil
}

func (s *SnapshotStore) SaveAlert(ctx context.Context, alert AlertEvent) error {
	contextJSON := ""
	if alert.Context != nil {
		payload, err := json.Marshal(alert.Context)
		if err == nil {
			contextJSON = string(payload)
		}
	}

	createdAt := alert.CreatedAt
	if createdAt.IsZero() {
		createdAt = time.Now().UTC()
	}

	_, err := s.db.ExecContext(ctx, `
		INSERT INTO go_alerts (type, severity, message, context, created_at)
		VALUES (?, ?, ?, ?, ?)
	`, alert.Type, alert.Severity, alert.Message, contextJSON, createdAt)
	return err
}

func (s *SnapshotStore) GetRecentAlerts(ctx context.Context, limit int) ([]AlertRecord, error) {
	if limit <= 0 {
		limit = 100
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT type, severity, message, context, created_at
		FROM go_alerts
		ORDER BY created_at DESC
		LIMIT ?
	`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	alerts := make([]AlertRecord, 0, limit)
	for rows.Next() {
		var record AlertRecord
		if err := rows.Scan(&record.Type, &record.Severity, &record.Message, &record.Context, &record.CreatedAt); err != nil {
			return nil, err
		}
		alerts = append(alerts, record)
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	return alerts, nil
}
