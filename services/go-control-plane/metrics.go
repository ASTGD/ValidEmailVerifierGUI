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
	b.WriteString("# HELP go_control_plane_pool_health_score Pool health score from worker hints.\n")
	b.WriteString("# TYPE go_control_plane_pool_health_score gauge\n")
	for _, pool := range stats.Pools {
		poolLabel := promLabelValue(pool.Pool)
		fmt.Fprintf(&b, "go_control_plane_pool_online_workers{pool=\"%s\"} %d\n", poolLabel, pool.Online)
		fmt.Fprintf(&b, "go_control_plane_pool_desired_workers{pool=\"%s\"} %d\n", poolLabel, pool.Desired)
		fmt.Fprintf(&b, "go_control_plane_pool_health_score{pool=\"%s\"} %s\n", poolLabel, formatPromFloat(pool.HealthScore))
	}

	b.WriteString("# HELP go_control_plane_worker_error_rate_per_min Worker error rate per minute.\n")
	b.WriteString("# TYPE go_control_plane_worker_error_rate_per_min gauge\n")
	b.WriteString("# HELP go_control_plane_worker_smtp_tempfail_rate Worker SMTP tempfail rate.\n")
	b.WriteString("# TYPE go_control_plane_worker_smtp_tempfail_rate gauge\n")
	b.WriteString("# HELP go_control_plane_worker_smtp_reject_rate Worker SMTP reject rate.\n")
	b.WriteString("# TYPE go_control_plane_worker_smtp_reject_rate gauge\n")
	b.WriteString("# HELP go_control_plane_worker_smtp_unknown_rate Worker SMTP unknown rate.\n")
	b.WriteString("# TYPE go_control_plane_worker_smtp_unknown_rate gauge\n")
	for _, worker := range stats.Workers {
		rate := stats.WorkerErrorRates[worker.WorkerID]
		workerLabel := promLabelValue(worker.WorkerID)
		poolLabel := promLabelValue(worker.Pool)
		fmt.Fprintf(&b, "go_control_plane_worker_error_rate_per_min{worker_id=\"%s\",pool=\"%s\"} %s\n", workerLabel, poolLabel, formatPromFloat(rate))
		if worker.SMTPMetrics != nil {
			fmt.Fprintf(&b, "go_control_plane_worker_smtp_tempfail_rate{worker_id=\"%s\",pool=\"%s\"} %s\n", workerLabel, poolLabel, formatPromFloat(worker.SMTPMetrics.TempfailRate))
			fmt.Fprintf(&b, "go_control_plane_worker_smtp_reject_rate{worker_id=\"%s\",pool=\"%s\"} %s\n", workerLabel, poolLabel, formatPromFloat(worker.SMTPMetrics.RejectRate))
			fmt.Fprintf(&b, "go_control_plane_worker_smtp_unknown_rate{worker_id=\"%s\",pool=\"%s\"} %s\n", workerLabel, poolLabel, formatPromFloat(worker.SMTPMetrics.UnknownRate))
		}
	}

	b.WriteString("# HELP go_control_plane_incidents_active Number of active incidents.\n")
	b.WriteString("# TYPE go_control_plane_incidents_active gauge\n")
	fmt.Fprintf(&b, "go_control_plane_incidents_active %d\n", stats.IncidentCount)

	b.WriteString("# HELP go_control_plane_probe_unknown_rate_avg Average probe unknown rate across workers.\n")
	b.WriteString("# TYPE go_control_plane_probe_unknown_rate_avg gauge\n")
	fmt.Fprintf(&b, "go_control_plane_probe_unknown_rate_avg %s\n", formatPromFloat(stats.ProbeUnknownRate))

	b.WriteString("# HELP go_control_plane_probe_tempfail_rate_avg Average probe tempfail rate across workers.\n")
	b.WriteString("# TYPE go_control_plane_probe_tempfail_rate_avg gauge\n")
	fmt.Fprintf(&b, "go_control_plane_probe_tempfail_rate_avg %s\n", formatPromFloat(stats.ProbeTempfailRate))

	b.WriteString("# HELP go_control_plane_probe_reject_rate_avg Average probe reject rate across workers.\n")
	b.WriteString("# TYPE go_control_plane_probe_reject_rate_avg gauge\n")
	fmt.Fprintf(&b, "go_control_plane_probe_reject_rate_avg %s\n", formatPromFloat(stats.ProbeRejectRate))

	b.WriteString("# HELP go_control_plane_provider_tempfail_rate Provider tempfail rate.\n")
	b.WriteString("# TYPE go_control_plane_provider_tempfail_rate gauge\n")
	b.WriteString("# HELP go_control_plane_provider_reject_rate Provider reject rate.\n")
	b.WriteString("# TYPE go_control_plane_provider_reject_rate gauge\n")
	b.WriteString("# HELP go_control_plane_provider_unknown_rate Provider unknown rate.\n")
	b.WriteString("# TYPE go_control_plane_provider_unknown_rate gauge\n")
	b.WriteString("# HELP go_control_plane_provider_policy_blocked_rate Provider policy blocked rate.\n")
	b.WriteString("# TYPE go_control_plane_provider_policy_blocked_rate gauge\n")
	b.WriteString("# HELP go_control_plane_provider_avg_retry_after_seconds Provider average retry-after seconds.\n")
	b.WriteString("# TYPE go_control_plane_provider_avg_retry_after_seconds gauge\n")
	b.WriteString("# HELP go_control_plane_provider_workers Provider worker count reporting telemetry.\n")
	b.WriteString("# TYPE go_control_plane_provider_workers gauge\n")
	for _, provider := range stats.ProviderHealth {
		providerLabel := promLabelValue(provider.Provider)
		modeLabel := promLabelValue(provider.Mode)
		statusLabel := promLabelValue(provider.Status)
		fmt.Fprintf(&b, "go_control_plane_provider_tempfail_rate{provider=\"%s\",mode=\"%s\",status=\"%s\"} %s\n", providerLabel, modeLabel, statusLabel, formatPromFloat(provider.TempfailRate))
		fmt.Fprintf(&b, "go_control_plane_provider_reject_rate{provider=\"%s\",mode=\"%s\",status=\"%s\"} %s\n", providerLabel, modeLabel, statusLabel, formatPromFloat(provider.RejectRate))
		fmt.Fprintf(&b, "go_control_plane_provider_unknown_rate{provider=\"%s\",mode=\"%s\",status=\"%s\"} %s\n", providerLabel, modeLabel, statusLabel, formatPromFloat(provider.UnknownRate))
		fmt.Fprintf(&b, "go_control_plane_provider_policy_blocked_rate{provider=\"%s\",mode=\"%s\",status=\"%s\"} %s\n", providerLabel, modeLabel, statusLabel, formatPromFloat(provider.PolicyBlockedRate))
		fmt.Fprintf(&b, "go_control_plane_provider_avg_retry_after_seconds{provider=\"%s\",mode=\"%s\",status=\"%s\"} %s\n", providerLabel, modeLabel, statusLabel, formatPromFloat(provider.AvgRetryAfter))
		fmt.Fprintf(&b, "go_control_plane_provider_workers{provider=\"%s\",mode=\"%s\",status=\"%s\"} %d\n", providerLabel, modeLabel, statusLabel, provider.Workers)
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
