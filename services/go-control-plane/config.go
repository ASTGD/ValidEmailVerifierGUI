package main

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	Port                    string
	RedisAddr               string
	RedisPassword           string
	RedisDB                 int
	MySQLDSN                string
	SnapshotInterval        time.Duration
	ControlPlaneToken       string
	HeartbeatTTL            time.Duration
	ShutdownTimeoutSec      int
	AlertsEnabled           bool
	AlertCheckInterval      time.Duration
	AlertHeartbeatGrace     time.Duration
	AlertCooldown           time.Duration
	AlertErrorRateThreshold float64
	AutoActionsEnabled      bool
	SlackWebhookURL         string
	SMTPHost                string
	SMTPPort                int
	SMTPUsername            string
	SMTPPassword            string
	SMTPFrom                string
	SMTPTo                  []string
}

func LoadConfig() (Config, error) {
	var cfg Config

	cfg.Port = os.Getenv("PORT")
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
