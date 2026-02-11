package main

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	Port                                      string
	SSEWriteTimeoutSec                        int
	InstanceID                                string
	RedisAddr                                 string
	RedisPassword                             string
	RedisDB                                   int
	MySQLDSN                                  string
	SnapshotInterval                          time.Duration
	ControlPlaneToken                         string
	HeartbeatTTL                              time.Duration
	ShutdownTimeoutSec                        int
	LeaderLockEnabled                         bool
	LeaderLockTTL                             time.Duration
	StaleWorkerTTL                            time.Duration
	StuckDesiredGrace                         time.Duration
	AlertsEnabled                             bool
	AlertCheckInterval                        time.Duration
	AlertHeartbeatGrace                       time.Duration
	AlertCooldown                             time.Duration
	AlertErrorRateThreshold                   float64
	AutoActionsEnabled                        bool
	AutoScaleEnabled                          bool
	AutoScaleInterval                         time.Duration
	AutoScaleCooldown                         time.Duration
	AutoScaleMinDesired                       int
	AutoScaleMaxDesired                       int
	AutoScaleCanaryPercent                    int
	QuarantineErrorRate                       float64
	ProviderPolicyEngineEnabled               bool
	AdaptiveRetryEnabled                      bool
	ProviderAutoprotectEnabled                bool
	ProviderTempfailWarnRate                  float64
	ProviderTempfailCriticalRate              float64
	ProviderRejectWarnRate                    float64
	ProviderRejectCriticalRate                float64
	ProviderUnknownWarnRate                   float64
	ProviderUnknownCriticalRate               float64
	SlackWebhookURL                           string
	SMTPHost                                  string
	SMTPPort                                  int
	SMTPUsername                              string
	SMTPPassword                              string
	SMTPFrom                                  string
	SMTPTo                                    []string
	LaravelAPIBaseURL                         string
	LaravelVerifierToken                      string
	PolicyPayloadStrictValidationEnabled      bool
	PolicyCanaryAutopilotEnabled              bool
	PolicyCanaryWindowMinutes                 int
	PolicyCanaryRequiredHealthWindows         int
	PolicyCanaryUnknownRegressionThreshold    float64
	PolicyCanaryTempfailRecoveryDropThreshold float64
	PolicyCanaryPolicyBlockSpikeThreshold     float64
}

func LoadConfig() (Config, error) {
	var cfg Config

	cfg.Port = os.Getenv("PORT")
	cfg.InstanceID = strings.TrimSpace(os.Getenv("CONTROL_PLANE_INSTANCE_ID"))
	cfg.RedisAddr = os.Getenv("REDIS_ADDR")
	cfg.RedisPassword = os.Getenv("REDIS_PASSWORD")
	cfg.ControlPlaneToken = os.Getenv("CONTROL_PLANE_TOKEN")
	cfg.MySQLDSN = os.Getenv("MYSQL_DSN")
	cfg.SlackWebhookURL = os.Getenv("SLACK_WEBHOOK_URL")
	cfg.SMTPHost = os.Getenv("SMTP_HOST")
	cfg.SMTPUsername = os.Getenv("SMTP_USERNAME")
	cfg.SMTPPassword = os.Getenv("SMTP_PASSWORD")
	cfg.SMTPFrom = os.Getenv("SMTP_FROM")
	cfg.SMTPTo = splitCSV(os.Getenv("SMTP_TO"))
	cfg.LaravelAPIBaseURL = strings.TrimSpace(os.Getenv("LARAVEL_API_BASE_URL"))
	cfg.LaravelVerifierToken = strings.TrimSpace(os.Getenv("LARAVEL_VERIFIER_TOKEN"))

	if cfg.Port == "" {
		return cfg, fmt.Errorf("PORT is required")
	}
	if cfg.RedisAddr == "" {
		return cfg, fmt.Errorf("REDIS_ADDR is required")
	}
	if cfg.ControlPlaneToken == "" {
		return cfg, fmt.Errorf("CONTROL_PLANE_TOKEN is required")
	}

	redisDB := 0
	if value := os.Getenv("REDIS_DB"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("REDIS_DB must be an integer")
		}
		redisDB = parsed
	}
	cfg.RedisDB = redisDB

	snapshotInterval := 60
	if value := os.Getenv("SNAPSHOT_INTERVAL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("SNAPSHOT_INTERVAL_SECONDS must be an integer")
		}
		if parsed > 0 {
			snapshotInterval = parsed
		}
	}
	cfg.SnapshotInterval = time.Duration(snapshotInterval) * time.Second

	heartbeatTTL := 60
	if value := os.Getenv("HEARTBEAT_TTL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("HEARTBEAT_TTL_SECONDS must be an integer")
		}
		heartbeatTTL = parsed
	}
	cfg.HeartbeatTTL = time.Duration(heartbeatTTL) * time.Second

	leaderTTL := 45
	if value := os.Getenv("LEADER_LOCK_TTL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("LEADER_LOCK_TTL_SECONDS must be an integer")
		}
		if parsed > 0 {
			leaderTTL = parsed
		}
	}
	cfg.LeaderLockTTL = time.Duration(leaderTTL) * time.Second
	cfg.LeaderLockEnabled = true
	if value := os.Getenv("LEADER_LOCK_ENABLED"); value != "" {
		cfg.LeaderLockEnabled = parseBool(value)
	}

	staleTTL := 86400
	if value := os.Getenv("STALE_WORKER_TTL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("STALE_WORKER_TTL_SECONDS must be an integer")
		}
		if parsed > 0 {
			staleTTL = parsed
		}
	}
	cfg.StaleWorkerTTL = time.Duration(staleTTL) * time.Second

	stuckGrace := 600
	if value := os.Getenv("STUCK_DESIRED_GRACE_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("STUCK_DESIRED_GRACE_SECONDS must be an integer")
		}
		if parsed > 0 {
			stuckGrace = parsed
		}
	}
	cfg.StuckDesiredGrace = time.Duration(stuckGrace) * time.Second

	alertCheckInterval := 30
	if value := os.Getenv("ALERT_CHECK_INTERVAL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("ALERT_CHECK_INTERVAL_SECONDS must be an integer")
		}
		if parsed > 0 {
			alertCheckInterval = parsed
		}
	}
	cfg.AlertCheckInterval = time.Duration(alertCheckInterval) * time.Second

	alertGrace := 120
	if value := os.Getenv("ALERT_HEARTBEAT_GRACE_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("ALERT_HEARTBEAT_GRACE_SECONDS must be an integer")
		}
		if parsed > 0 {
			alertGrace = parsed
		}
	}
	cfg.AlertHeartbeatGrace = time.Duration(alertGrace) * time.Second

	alertCooldown := 300
	if value := os.Getenv("ALERT_COOLDOWN_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("ALERT_COOLDOWN_SECONDS must be an integer")
		}
		if parsed > 0 {
			alertCooldown = parsed
		}
	}
	cfg.AlertCooldown = time.Duration(alertCooldown) * time.Second

	if value := os.Getenv("ALERT_ERROR_RATE_THRESHOLD"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("ALERT_ERROR_RATE_THRESHOLD must be a number")
		}
		cfg.AlertErrorRateThreshold = parsed
	}

	cfg.AlertsEnabled = parseBool(os.Getenv("ALERTS_ENABLED"))
	cfg.AutoActionsEnabled = parseBool(os.Getenv("AUTO_ACTIONS_ENABLED"))
	cfg.ProviderPolicyEngineEnabled = parseBool(os.Getenv("PROVIDER_POLICY_ENGINE_ENABLED"))
	cfg.AdaptiveRetryEnabled = parseBool(os.Getenv("ADAPTIVE_RETRY_ENABLED"))
	cfg.ProviderAutoprotectEnabled = parseBool(os.Getenv("PROVIDER_AUTOPROTECT_ENABLED"))

	cfg.ProviderTempfailWarnRate = 0.30
	if value := os.Getenv("PROVIDER_TEMPFAIL_WARN_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_TEMPFAIL_WARN_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderTempfailWarnRate = parsed
		}
	}

	cfg.ProviderTempfailCriticalRate = 0.55
	if value := os.Getenv("PROVIDER_TEMPFAIL_CRITICAL_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_TEMPFAIL_CRITICAL_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderTempfailCriticalRate = parsed
		}
	}

	cfg.ProviderRejectWarnRate = 0.20
	if value := os.Getenv("PROVIDER_REJECT_WARN_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_REJECT_WARN_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderRejectWarnRate = parsed
		}
	}

	cfg.ProviderRejectCriticalRate = 0.40
	if value := os.Getenv("PROVIDER_REJECT_CRITICAL_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_REJECT_CRITICAL_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderRejectCriticalRate = parsed
		}
	}

	cfg.ProviderUnknownWarnRate = 0.20
	if value := os.Getenv("PROVIDER_UNKNOWN_WARN_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_UNKNOWN_WARN_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderUnknownWarnRate = parsed
		}
	}

	cfg.ProviderUnknownCriticalRate = 0.35
	if value := os.Getenv("PROVIDER_UNKNOWN_CRITICAL_RATE"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("PROVIDER_UNKNOWN_CRITICAL_RATE must be a number")
		}
		if parsed >= 0 {
			cfg.ProviderUnknownCriticalRate = parsed
		}
	}

	autoScaleInterval := 30
	if value := os.Getenv("AUTOSCALE_INTERVAL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("AUTOSCALE_INTERVAL_SECONDS must be an integer")
		}
		if parsed > 0 {
			autoScaleInterval = parsed
		}
	}
	cfg.AutoScaleInterval = time.Duration(autoScaleInterval) * time.Second
	cfg.AutoScaleEnabled = parseBool(os.Getenv("AUTOSCALE_ENABLED"))

	autoScaleCooldown := 120
	if value := os.Getenv("AUTOSCALE_COOLDOWN_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("AUTOSCALE_COOLDOWN_SECONDS must be an integer")
		}
		if parsed > 0 {
			autoScaleCooldown = parsed
		}
	}
	cfg.AutoScaleCooldown = time.Duration(autoScaleCooldown) * time.Second

	cfg.AutoScaleMinDesired = 1
	if value := os.Getenv("AUTOSCALE_MIN_DESIRED"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("AUTOSCALE_MIN_DESIRED must be an integer")
		}
		if parsed >= 0 {
			cfg.AutoScaleMinDesired = parsed
		}
	}

	cfg.AutoScaleMaxDesired = 4
	if value := os.Getenv("AUTOSCALE_MAX_DESIRED"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("AUTOSCALE_MAX_DESIRED must be an integer")
		}
		if parsed >= cfg.AutoScaleMinDesired {
			cfg.AutoScaleMaxDesired = parsed
		}
	}

	cfg.AutoScaleCanaryPercent = 100
	if value := os.Getenv("AUTOSCALE_CANARY_PERCENT"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("AUTOSCALE_CANARY_PERCENT must be an integer")
		}
		if parsed < 0 {
			parsed = 0
		}
		if parsed > 100 {
			parsed = 100
		}
		cfg.AutoScaleCanaryPercent = parsed
	}

	cfg.QuarantineErrorRate = cfg.AlertErrorRateThreshold * 1.5
	if value := os.Getenv("QUARANTINE_ERROR_RATE_THRESHOLD"); value != "" {
		parsed, err := strconv.ParseFloat(value, 64)
		if err != nil {
			return cfg, fmt.Errorf("QUARANTINE_ERROR_RATE_THRESHOLD must be a number")
		}
		if parsed >= 0 {
			cfg.QuarantineErrorRate = parsed
		}
	}

	cfg.SSEWriteTimeoutSec = 0
	cfg.PolicyPayloadStrictValidationEnabled = parseBoolWithDefault("POLICY_PAYLOAD_STRICT_VALIDATION_ENABLED", true)
	cfg.PolicyCanaryAutopilotEnabled = parseBoolWithDefault("POLICY_CANARY_AUTOPILOT_ENABLED", false)
	cfg.PolicyCanaryWindowMinutes = envInt("POLICY_CANARY_WINDOW_MINUTES", 15)
	if cfg.PolicyCanaryWindowMinutes < 1 {
		cfg.PolicyCanaryWindowMinutes = 15
	}
	cfg.PolicyCanaryRequiredHealthWindows = envInt("POLICY_CANARY_REQUIRED_HEALTH_WINDOWS", 4)
	if cfg.PolicyCanaryRequiredHealthWindows < 1 {
		cfg.PolicyCanaryRequiredHealthWindows = 4
	}
	cfg.PolicyCanaryUnknownRegressionThreshold = envFloat("POLICY_CANARY_UNKNOWN_REGRESSION_THRESHOLD", 0.05)
	if cfg.PolicyCanaryUnknownRegressionThreshold < 0 {
		cfg.PolicyCanaryUnknownRegressionThreshold = 0.05
	}
	cfg.PolicyCanaryTempfailRecoveryDropThreshold = envFloat("POLICY_CANARY_TEMPFAIL_RECOVERY_DROP_THRESHOLD", 0.10)
	if cfg.PolicyCanaryTempfailRecoveryDropThreshold < 0 {
		cfg.PolicyCanaryTempfailRecoveryDropThreshold = 0.10
	}
	cfg.PolicyCanaryPolicyBlockSpikeThreshold = envFloat("POLICY_CANARY_POLICY_BLOCK_SPIKE_THRESHOLD", 0.10)
	if cfg.PolicyCanaryPolicyBlockSpikeThreshold < 0 {
		cfg.PolicyCanaryPolicyBlockSpikeThreshold = 0.10
	}

	if value := os.Getenv("SMTP_PORT"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("SMTP_PORT must be an integer")
		}
		cfg.SMTPPort = parsed
	}

	shutdownTimeout := 10
	if value := os.Getenv("SHUTDOWN_TIMEOUT_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("SHUTDOWN_TIMEOUT_SECONDS must be an integer")
		}
		shutdownTimeout = parsed
	}
	cfg.ShutdownTimeoutSec = shutdownTimeout

	if value := os.Getenv("SSE_WRITE_TIMEOUT_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("SSE_WRITE_TIMEOUT_SECONDS must be an integer")
		}
		if parsed >= 0 {
			cfg.SSEWriteTimeoutSec = parsed
		}
	}

	return cfg, nil
}

func parseBool(value string) bool {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "1", "true", "yes", "on":
		return true
	default:
		return false
	}
}

func parseBoolWithDefault(key string, fallback bool) bool {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	return parseBool(value)
}

func envInt(key string, fallback int) int {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	parsed, err := strconv.Atoi(value)
	if err != nil {
		return fallback
	}

	return parsed
}

func envFloat(key string, fallback float64) float64 {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	parsed, err := strconv.ParseFloat(value, 64)
	if err != nil {
		return fallback
	}

	return parsed
}

func splitCSV(value string) []string {
	if value == "" {
		return nil
	}
	parts := strings.Split(value, ",")
	out := make([]string, 0, len(parts))
	for _, part := range parts {
		trimmed := strings.TrimSpace(part)
		if trimmed != "" {
			out = append(out, trimmed)
		}
	}
	return out
}
