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

	b.WriteString("# HELP go_control_plane_routing_retry_claims_total Retry claims observed from worker routing telemetry.\n")
	b.WriteString("# TYPE go_control_plane_routing_retry_claims_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_retry_claims_total %d\n", stats.RoutingQuality.RetryClaimsTotal)

	b.WriteString("# HELP go_control_plane_routing_retry_anti_affinity_success_total Retry claims that avoided previous worker/pool.\n")
	b.WriteString("# TYPE go_control_plane_routing_retry_anti_affinity_success_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_retry_anti_affinity_success_total %d\n", stats.RoutingQuality.RetryAntiAffinitySuccessTotal)

	b.WriteString("# HELP go_control_plane_routing_same_worker_avoid_total Retry claims that avoided same worker.\n")
	b.WriteString("# TYPE go_control_plane_routing_same_worker_avoid_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_same_worker_avoid_total %d\n", stats.RoutingQuality.SameWorkerAvoidTotal)

	b.WriteString("# HELP go_control_plane_routing_same_pool_avoid_total Retry claims that avoided same pool.\n")
	b.WriteString("# TYPE go_control_plane_routing_same_pool_avoid_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_same_pool_avoid_total %d\n", stats.RoutingQuality.SamePoolAvoidTotal)

	b.WriteString("# HELP go_control_plane_routing_provider_affinity_hit_total Claims that matched provider affinity.\n")
	b.WriteString("# TYPE go_control_plane_routing_provider_affinity_hit_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_provider_affinity_hit_total %d\n", stats.RoutingQuality.ProviderAffinityHitTotal)

	b.WriteString("# HELP go_control_plane_routing_fallback_claim_total Claims that used fallback routing.\n")
	b.WriteString("# TYPE go_control_plane_routing_fallback_claim_total gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_fallback_claim_total %d\n", stats.RoutingQuality.FallbackClaimTotal)

	b.WriteString("# HELP go_control_plane_routing_anti_affinity_success_rate Anti-affinity success rate on retries.\n")
	b.WriteString("# TYPE go_control_plane_routing_anti_affinity_success_rate gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_anti_affinity_success_rate %s\n", formatPromFloat(stats.RoutingQuality.AntiAffinitySuccessRate))

	b.WriteString("# HELP go_control_plane_routing_provider_affinity_hit_rate Provider affinity hit rate.\n")
	b.WriteString("# TYPE go_control_plane_routing_provider_affinity_hit_rate gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_provider_affinity_hit_rate %s\n", formatPromFloat(stats.RoutingQuality.ProviderAffinityHitRate))

	b.WriteString("# HELP go_control_plane_routing_retry_fallback_rate Retry fallback rate.\n")
	b.WriteString("# TYPE go_control_plane_routing_retry_fallback_rate gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_retry_fallback_rate %s\n", formatPromFloat(stats.RoutingQuality.RetryFallbackRate))

	b.WriteString("# HELP go_control_plane_routing_top_pool_share Highest desired-capacity pool share.\n")
	b.WriteString("# TYPE go_control_plane_routing_top_pool_share gauge\n")
	fmt.Fprintf(&b, "go_control_plane_routing_top_pool_share %s\n", formatPromFloat(stats.RoutingQuality.TopPoolShare))

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

	if s.laravelEngineClient != nil {
		snapshot := s.laravelEngineClient.MetricsSnapshot()

		b.WriteString("# HELP engine_internal_api_requests_total Go-to-Laravel internal API requests by action and status class.\n")
		b.WriteString("# TYPE engine_internal_api_requests_total counter\n")
		for key, count := range snapshot.RequestsByActionClass {
			parts := strings.SplitN(key, "|", 2)
			actionLabel := promLabelValue(parts[0])
			statusClass := "unknown"
			if len(parts) > 1 {
				statusClass = promLabelValue(parts[1])
			}
			fmt.Fprintf(&b, "engine_internal_api_requests_total{action=\"%s\",status_class=\"%s\"} %d\n", actionLabel, statusClass, count)
		}

		b.WriteString("# HELP engine_internal_api_failures_total Go-to-Laravel internal API failures by action/status/error_code.\n")
		b.WriteString("# TYPE engine_internal_api_failures_total counter\n")
		for key, count := range snapshot.FailuresByActionClass {
			parts := strings.SplitN(key, "|", 2)
			actionLabel := promLabelValue(parts[0])
			statusClass := "unknown"
			if len(parts) > 1 {
				statusClass = promLabelValue(parts[1])
			}
			fmt.Fprintf(&b, "engine_internal_api_failures_total{action=\"%s\",status_class=\"%s\"} %d\n", actionLabel, statusClass, count)
		}
		for key, count := range snapshot.FailureByCode {
			parts := strings.SplitN(key, "|", 2)
			actionLabel := promLabelValue(parts[0])
			errorCode := "unknown"
			if len(parts) > 1 {
				errorCode = promLabelValue(parts[1])
			}
			fmt.Fprintf(&b, "engine_internal_api_failures_total{action=\"%s\",status_class=\"error\",error_code=\"%s\"} %d\n", actionLabel, errorCode, count)
		}

		b.WriteString("# HELP engine_provision_bundle_duration_ms Total duration for provisioning bundle API calls in milliseconds.\n")
		b.WriteString("# TYPE engine_provision_bundle_duration_ms summary\n")
		fmt.Fprintf(&b, "engine_provision_bundle_duration_ms_sum %s\n", formatPromFloat(snapshot.ProvisionDurationSumMS))
		fmt.Fprintf(&b, "engine_provision_bundle_duration_ms_count %d\n", snapshot.ProvisionDurationCount)
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
