package main

import (
	"context"
	"database/sql"
	"fmt"
	"log"
	"os"

	_ "github.com/go-sql-driver/mysql"
	"github.com/redis/go-redis/v9"
)

func main() {
	cfg, err := LoadConfig()
	if err != nil {
		log.Fatalf("config error: %v", err)
	}

	rdb := redis.NewClient(&redis.Options{
		Addr:     cfg.RedisAddr,
		Password: cfg.RedisPassword,
		DB:       cfg.RedisDB,
	})

	if err := rdb.Ping(context.Background()).Err(); err != nil {
		log.Fatalf("redis connection failed: %v", err)
	}

	store := NewStore(rdb, cfg.HeartbeatTTL)
	store.SetPolicyPayloadValidator(NewLaravelPolicyPayloadValidator(cfg), cfg.PolicyPayloadStrictValidationEnabled)
	instanceID := cfg.InstanceID
	if instanceID == "" {
		host, _ := os.Hostname()
		if host == "" {
			host = "control-plane"
		}
		instanceID = fmt.Sprintf("%s-%d", host, os.Getpid())
	}

	var snapshotStore *SnapshotStore
	if cfg.MySQLDSN != "" {
		db, err := sql.Open("mysql", cfg.MySQLDSN)
		if err != nil {
			log.Fatalf("mysql connection failed: %v", err)
		}
		if err := db.Ping(); err != nil {
			log.Fatalf("mysql ping failed: %v", err)
		}
		snapshotStore = NewSnapshotStore(db)
	}

	server := NewServer(store, snapshotStore, cfg)

	if snapshotStore != nil {
		snapshotService := NewSnapshotService(store, snapshotStore, cfg, instanceID)
		snapshotService.Start()
	}

	slackNotifier := NewSlackNotifier(cfg.SlackWebhookURL)
	emailNotifier := NewEmailNotifier(cfg)
	notifier := NewMultiNotifier(slackNotifier, emailNotifier)
	alertService := NewAlertService(store, snapshotStore, cfg, notifier, instanceID)
	alertService.Start()

	autoScaleService := NewAutoScaleService(store, cfg, instanceID)
	autoScaleService.Start()

	policyAutopilotService := NewPolicyCanaryAutopilotService(store, cfg, instanceID)
	policyAutopilotService.Start()

	if err := server.ListenAndServe(); err != nil {
		log.Fatalf("server error: %v", err)
	}
}
