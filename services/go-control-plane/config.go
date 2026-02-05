package main

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

type Config struct {
	Port               string
	RedisAddr          string
	RedisPassword      string
	RedisDB            int
	ControlPlaneToken  string
	HeartbeatTTL       time.Duration
	ShutdownTimeoutSec int
}

func LoadConfig() (Config, error) {
	var cfg Config

	cfg.Port = os.Getenv("PORT")
	cfg.RedisAddr = os.Getenv("REDIS_ADDR")
	cfg.RedisPassword = os.Getenv("REDIS_PASSWORD")
	cfg.ControlPlaneToken = os.Getenv("CONTROL_PLANE_TOKEN")

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

	heartbeatTTL := 60
	if value := os.Getenv("HEARTBEAT_TTL_SECONDS"); value != "" {
		parsed, err := strconv.Atoi(value)
		if err != nil {
			return cfg, fmt.Errorf("HEARTBEAT_TTL_SECONDS must be an integer")
		}
		heartbeatTTL = parsed
	}
	cfg.HeartbeatTTL = time.Duration(heartbeatTTL) * time.Second

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
