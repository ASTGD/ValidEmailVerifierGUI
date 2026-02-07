package main

import (
	"fmt"
	"net/http"
	"strconv"
	"strings"
)

func (s *Server) handleMetrics(w http.ResponseWriter, r *http.Request) {
	stats, err := s.collectControlPlaneStats(r.Context())
	if err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	w.Header().Set("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
	w.WriteHeader(http.StatusOK)

	var b strings.Builder
	b.WriteString("# HELP go_control_plane_workers_active Active workers with fresh heartbeat.\n")
	b.WriteString("# TYPE go_control_plane_workers_active gauge\n")
	fmt.Fprintf(&b, "go_control_plane_workers_active %d\n", stats.WorkerCount)

	b.WriteString("# HELP go_control_plane_pools_known Number of known worker pools.\n")
	b.WriteString("# TYPE go_control_plane_pools_known gauge\n")
	fmt.Fprintf(&b, "go_control_plane_pools_known %d\n", stats.PoolCount)

	b.WriteString("# HELP go_control_plane_desired_workers_total Desired worker total across all pools.\n")
	b.WriteString("# TYPE go_control_plane_desired_workers_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_desired_workers_total %d\n", stats.DesiredTotal)

	b.WriteString("# HELP go_control_plane_error_rate_total_per_min Total worker error rate per minute.\n")
	b.WriteString("# TYPE go_control_plane_error_rate_total_per_min gauge\n")
	fmt.Fprintf(&b, "go_control_plane_error_rate_total_per_min %s\n", formatPromFloat(stats.ErrorRateTotal))

	b.WriteString("# HELP go_control_plane_error_rate_avg_per_min Average worker error rate per minute.\n")
	b.WriteString("# TYPE go_control_plane_error_rate_avg_per_min gauge\n")
	fmt.Fprintf(&b, "go_control_plane_error_rate_avg_per_min %s\n", formatPromFloat(stats.ErrorRateAverage))

	b.WriteString("# HELP go_control_plane_pool_online_workers Online workers per pool.\n")
	b.WriteString("# TYPE go_control_plane_pool_online_workers gauge\n")
	b.WriteString("# HELP go_control_plane_pool_desired_workers Desired workers per pool.\n")
	b.WriteString("# TYPE go_control_plane_pool_desired_workers gauge\n")
	for _, pool := range stats.Pools {
		poolLabel := promLabelValue(pool.Pool)
		fmt.Fprintf(&b, "go_control_plane_pool_online_workers{pool=\"%s\"} %d\n", poolLabel, pool.Online)
		fmt.Fprintf(&b, "go_control_plane_pool_desired_workers{pool=\"%s\"} %d\n", poolLabel, pool.Desired)
	}

	b.WriteString("# HELP go_control_plane_worker_error_rate_per_min Worker error rate per minute.\n")
	b.WriteString("# TYPE go_control_plane_worker_error_rate_per_min gauge\n")
	for _, worker := range stats.Workers {
		rate := stats.WorkerErrorRates[worker.WorkerID]
		workerLabel := promLabelValue(worker.WorkerID)
		poolLabel := promLabelValue(worker.Pool)
		fmt.Fprintf(&b, "go_control_plane_worker_error_rate_per_min{worker_id=\"%s\",pool=\"%s\"} %s\n", workerLabel, poolLabel, formatPromFloat(rate))
	}

	_, _ = w.Write([]byte(b.String()))
}

func formatPromFloat(value float64) string {
	return strconv.FormatFloat(value, 'f', -1, 64)
}

func promLabelValue(value string) string {
	escaped := strings.ReplaceAll(value, "\\", "\\\\")
	escaped = strings.ReplaceAll(escaped, "\"", "\\\"")
	escaped = strings.ReplaceAll(escaped, "\n", "\\n")
	return escaped
}
