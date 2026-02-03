package main

import (
	"context"
	"errors"
	"fmt"
	"net"
	"os"
	"os/signal"
	"strconv"
	"strings"
	"syscall"
	"time"

	"engine-monitor-go/internal/api"
)

func main() {
	baseURL := mustEnv("MONITOR_API_BASE_URL")
	token := mustEnv("MONITOR_API_TOKEN")
	timeoutSeconds := envInt("MONITOR_TIMEOUT_SECONDS", 8)
	intervalOverride := envInt("MONITOR_INTERVAL_SECONDS", 0)

	client := api.NewClient(baseURL, token, time.Duration(timeoutSeconds)*time.Second)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	sigs := make(chan os.Signal, 1)
	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM)
	go func() {
		<-sigs
		fmt.Println("shutting down monitor...")
		cancel()
	}()

	for {
		if ctx.Err() != nil {
			return
		}

		config, err := client.FetchConfig(ctx)
		if err != nil {
			fmt.Printf("failed to fetch config: %v\n", err)
			sleepWithContext(ctx, time.Duration(intervalOverrideOrDefault(intervalOverride))*time.Second)
			continue
		}

		interval := time.Duration(config.IntervalMinutes) * time.Minute
		if intervalOverride > 0 {
			interval = time.Duration(intervalOverride) * time.Second
		}
		if interval <= 0 {
			interval = 5 * time.Minute
		}

		if !config.Enabled {
			sleepWithContext(ctx, interval)
			continue
		}

		if len(config.RBLList) == 0 {
			fmt.Println("no RBLs configured, skipping checks")
			sleepWithContext(ctx, interval)
			continue
		}

		resolver := buildResolver(config)

		servers, err := client.FetchServers(ctx)
		if err != nil {
			fmt.Printf("failed to fetch servers: %v\n", err)
			sleepWithContext(ctx, interval)
			continue
		}

		for _, server := range servers {
			if ctx.Err() != nil {
				return
			}

			if strings.TrimSpace(server.IPAddress) == "" {
				continue
			}

			results := checkServer(ctx, resolver, time.Duration(timeoutSeconds)*time.Second, server.IPAddress, config.RBLList)
			if len(results) == 0 {
				continue
			}

			payload := api.CheckPayload{
				ServerID:  server.ID,
				ServerIP:  server.IPAddress,
				CheckedAt: time.Now().UTC(),
				Results:   results,
			}

			if err := client.SubmitChecks(ctx, payload); err != nil {
				fmt.Printf("failed to submit checks for server %d: %v\n", server.ID, err)
			}
		}

		sleepWithContext(ctx, interval)
	}
}

func buildResolver(config api.Config) *net.Resolver {
	if strings.ToLower(config.ResolverMode) != "custom" || strings.TrimSpace(config.ResolverIP) == "" {
		return net.DefaultResolver
	}

	port := config.ResolverPort
	if port <= 0 || port > 65535 {
		port = 53
	}

	return &net.Resolver{
		PreferGo: true,
		Dial: func(ctx context.Context, network, address string) (net.Conn, error) {
			dialer := net.Dialer{}
			target := net.JoinHostPort(config.ResolverIP, strconv.Itoa(port))
			return dialer.DialContext(ctx, network, target)
		},
	}
}

func checkServer(ctx context.Context, resolver *net.Resolver, timeout time.Duration, ip string, rbls []string) []api.CheckResult {
	ip = strings.TrimSpace(ip)
	if ip == "" {
		return nil
	}

	parsed := net.ParseIP(ip)
	if parsed == nil || parsed.To4() == nil {
		if len(rbls) == 0 {
			return nil
		}

		return []api.CheckResult{
			{
				RBL:          rbls[0],
				Listed:       false,
				ErrorMessage: "invalid or unsupported IP",
			},
		}
	}

	results := make([]api.CheckResult, 0, len(rbls))
	for _, rbl := range rbls {
		rbl = strings.TrimSpace(rbl)
		if rbl == "" {
			continue
		}

		result := checkRBL(ctx, resolver, timeout, ip, rbl)
		results = append(results, result)
	}

	return results
}

func checkRBL(ctx context.Context, resolver *net.Resolver, timeout time.Duration, ip string, rbl string) api.CheckResult {
	query := fmt.Sprintf("%s.%s", reverseIPv4(ip), rbl)
	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	hosts, err := resolver.LookupHost(ctx, query)
	if err != nil {
		if isNotFound(err) {
			return api.CheckResult{
				RBL:    rbl,
				Listed: false,
			}
		}

		return api.CheckResult{
			RBL:          rbl,
			Listed:       false,
			ErrorMessage: err.Error(),
		}
	}

	response := strings.Join(hosts, ",")
	txts, txtErr := resolver.LookupTXT(ctx, query)
	if txtErr == nil && len(txts) > 0 {
		response = strings.TrimSpace(response + " | " + strings.Join(txts, ";"))
	}

	return api.CheckResult{
		RBL:      rbl,
		Listed:   true,
		Response: response,
	}
}

func reverseIPv4(ip string) string {
	parsed := net.ParseIP(ip).To4()
	if parsed == nil {
		return ""
	}

	return fmt.Sprintf("%d.%d.%d.%d", parsed[3], parsed[2], parsed[1], parsed[0])
}

func isNotFound(err error) bool {
	var dnsErr *net.DNSError
	if !errors.As(err, &dnsErr) {
		return false
	}

	return dnsErr.IsNotFound
}

func sleepWithContext(ctx context.Context, duration time.Duration) {
	if duration <= 0 {
		return
	}

	timer := time.NewTimer(duration)
	defer timer.Stop()

	select {
	case <-ctx.Done():
	case <-timer.C:
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

func envInt(key string, fallback int) int {
	if value := os.Getenv(key); value != "" {
		parsed, err := strconv.Atoi(value)
		if err == nil {
			return parsed
		}
	}

	return fallback
}

func intervalOverrideOrDefault(value int) int {
	if value > 0 {
		return value
	}

	return 300
}
