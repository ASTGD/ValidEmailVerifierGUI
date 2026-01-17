package main

import (
    "context"
    "fmt"
    "os"
    "os/signal"
    "strconv"
    "syscall"
    "time"

    "engine-worker-go/internal/api"
    "engine-worker-go/internal/worker"
)

func main() {
    baseURL := mustEnv("ENGINE_API_BASE_URL")
    token := mustEnv("ENGINE_API_TOKEN")

    workerID := envOr("WORKER_ID", hostname())
    serverName := envOr("ENGINE_SERVER_NAME", workerID)
    serverIP := mustEnv("ENGINE_SERVER_IP")
    serverEnv := os.Getenv("ENGINE_SERVER_ENV")
    serverRegion := os.Getenv("ENGINE_SERVER_REGION")

    pollInterval := time.Duration(envInt("POLL_INTERVAL_SECONDS", 5)) * time.Second
    heartbeatInterval := time.Duration(envInt("HEARTBEAT_INTERVAL_SECONDS", 30)) * time.Second
    maxConcurrency := envInt("MAX_CONCURRENCY", 1)

    var leaseSeconds *int
    if val := os.Getenv("LEASE_SECONDS"); val != "" {
        parsed, err := strconv.Atoi(val)
        if err != nil {
            fmt.Printf("invalid LEASE_SECONDS: %v\n", err)
            os.Exit(1)
        }
        leaseSeconds = &parsed
    }

    client := api.NewClient(baseURL, token)

    cfg := worker.Config{
        PollInterval:      pollInterval,
        HeartbeatInterval: heartbeatInterval,
        LeaseSeconds:      leaseSeconds,
        MaxConcurrency:    maxConcurrency,
        WorkerID:          workerID,
        Server: api.EngineServerPayload{
            Name:        serverName,
            IPAddress:   serverIP,
            Environment: serverEnv,
            Region:      serverRegion,
        },
    }

    ctx, cancel := context.WithCancel(context.Background())
    defer cancel()

    sigs := make(chan os.Signal, 1)
    signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM)
    go func() {
        <-sigs
        fmt.Println("shutting down worker...")
        cancel()
    }()

    w := worker.New(client, cfg)
    if err := w.Run(ctx); err != nil && err != context.Canceled {
        fmt.Printf("worker stopped with error: %v\n", err)
        os.Exit(1)
    }
}

func mustEnv(key string) string {
    value := os.Getenv(key)
    if value == "" {
        fmt.Printf("%s is required\n", key)
        os.Exit(1)
    }

    return value
}

func envOr(key, fallback string) string {
    if value := os.Getenv(key); value != "" {
        return value
    }

    return fallback
}

func envInt(key string, fallback int) int {
    if value := os.Getenv(key); value != "" {
        parsed, err := strconv.Atoi(value)
        if err == nil {
            return parsed
        }
    }

    return fallback
}

func hostname() string {
    name, err := os.Hostname()
    if err != nil {
        return "worker"
    }

    return name
}
