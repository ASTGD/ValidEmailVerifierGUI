package main

type WorkerMetrics struct {
	EmailsPerSec  float64 `json:"emails_per_sec,omitempty"`
	ErrorsPerMin  float64 `json:"errors_per_min,omitempty"`
	CacheHitRate  float64 `json:"cache_hit_rate,omitempty"`
	AvgLatencyMS  float64 `json:"avg_latency_ms,omitempty"`
	BounceRate    float64 `json:"bounce_rate,omitempty"`
	UnknownRate   float64 `json:"unknown_rate,omitempty"`
}

type HeartbeatRequest struct {
	WorkerID       string        `json:"worker_id"`
	Host           string        `json:"host,omitempty"`
	IPAddress      string        `json:"ip_address,omitempty"`
	Version        string        `json:"version,omitempty"`
	Pool           string        `json:"pool,omitempty"`
	Tags           []string      `json:"tags,omitempty"`
	Status         string        `json:"status"`
	CurrentJobID   string        `json:"current_job_id,omitempty"`
	CurrentChunkID string        `json:"current_chunk_id,omitempty"`
	Metrics        *WorkerMetrics `json:"metrics,omitempty"`
}

type HeartbeatResponse struct {
	DesiredState string   `json:"desired_state"`
	Commands     []string `json:"commands"`
}

type WorkerSummary struct {
	WorkerID       string `json:"worker_id"`
	Host           string `json:"host,omitempty"`
	IPAddress      string `json:"ip_address,omitempty"`
	Version        string `json:"version,omitempty"`
	Pool           string `json:"pool,omitempty"`
	Status         string `json:"status"`
	DesiredState   string `json:"desired_state"`
	LastHeartbeat  string `json:"last_heartbeat_at"`
	CurrentJobID   string `json:"current_job_id,omitempty"`
	CurrentChunkID string `json:"current_chunk_id,omitempty"`
}

type WorkersResponse struct {
	Data []WorkerSummary `json:"data"`
}

type PoolSummary struct {
	Pool    string `json:"pool"`
	Online  int    `json:"online"`
	Desired int    `json:"desired"`
}

type PoolsResponse struct {
	Data []PoolSummary `json:"data"`
}

type ScalePoolRequest struct {
	Desired int `json:"desired"`
}

type ScalePoolResponse struct {
	Pool    string `json:"pool"`
	Desired int    `json:"desired"`
}

type workerMeta struct {
	WorkerID       string   `json:"worker_id"`
	Host           string   `json:"host,omitempty"`
	IPAddress      string   `json:"ip_address,omitempty"`
	Version        string   `json:"version,omitempty"`
	Pool           string   `json:"pool,omitempty"`
	Tags           []string `json:"tags,omitempty"`
	CurrentJobID   string   `json:"current_job_id,omitempty"`
	CurrentChunkID string   `json:"current_chunk_id,omitempty"`
}
