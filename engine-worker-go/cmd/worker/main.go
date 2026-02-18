package main

import (
	"context"
	"fmt"
	"os"
	"os/signal"
	"strconv"
	"strings"
	"syscall"
	"time"

	workerdata "engine-worker-go/data"
	"engine-worker-go/internal/api"
	"engine-worker-go/internal/verifier"
	"engine-worker-go/internal/worker"
)

func main() {
	baseURL := mustEnv("ENGINE_API_BASE_URL")
	token := mustEnv("ENGINE_API_TOKEN")
	controlPlaneBaseURL := strings.TrimSpace(os.Getenv("CONTROL_PLANE_BASE_URL"))
	controlPlaneToken := strings.TrimSpace(os.Getenv("CONTROL_PLANE_TOKEN"))

	workerID := envOr("WORKER_ID", hostname())
	workerCapability := parseWorkerCapability(os.Getenv("WORKER_CAPABILITY"))
	serverName := envOr("ENGINE_SERVER_NAME", workerID)
	serverIP := mustEnv("ENGINE_SERVER_IP")
	serverEnv := os.Getenv("ENGINE_SERVER_ENV")
	serverRegion := os.Getenv("ENGINE_SERVER_REGION")
	workerPool := strings.TrimSpace(os.Getenv("WORKER_POOL"))
	providerAffinity := parseProviderAffinity(os.Getenv("WORKER_PROVIDER_AFFINITY"))
	trustTier := parseTrustTier(os.Getenv("WORKER_TRUST_TIER"))

	pollInterval := time.Duration(envInt("POLL_INTERVAL_SECONDS", 5)) * time.Second
	heartbeatInterval := time.Duration(envInt("HEARTBEAT_INTERVAL_SECONDS", 30)) * time.Second
	maxConcurrency := envInt("MAX_CONCURRENCY", 1)
	policyRefresh := time.Duration(envInt("POLICY_REFRESH_SECONDS", 300)) * time.Second
	heloName := envOr("HELO_NAME", hostname())
	dnsTimeout := envInt("DNS_TIMEOUT_MS", 2000)
	smtpConnectTimeout := envInt("SMTP_CONNECT_TIMEOUT_MS", 2000)
	smtpReadTimeout := envInt("SMTP_READ_TIMEOUT_MS", 2000)
	smtpEhloTimeout := envInt("SMTP_EHLO_TIMEOUT_MS", 2000)
	maxMxAttempts := envInt("MAX_MX_ATTEMPTS", 2)
	retryableNetworkRetries := envInt("RETRYABLE_NETWORK_RETRIES", 1)
	backoffMs := envInt("BACKOFF_MS_BASE", 200)
	perDomainConcurrency := envInt("PER_DOMAIN_CONCURRENCY", 2)
	smtpRateLimit := envInt("SMTP_RATE_LIMIT_PER_MINUTE", 0)
	providerPolicyEngineEnabled := envBool("PROVIDER_POLICY_ENGINE_ENABLED", false)
	adaptiveRetryEnabled := envBool("ADAPTIVE_RETRY_ENABLED", false)
	probeAttemptChainEnabled := envBool("PROBE_ATTEMPT_CHAIN_ENABLED", true)
	unknownReasonTaxonomyEnabled := envBool("UNKNOWN_REASON_TAXONOMY_ENABLED", true)
	controlPlaneHeartbeatEnabled := envBool(
		"CONTROL_PLANE_HEARTBEAT_ENABLED",
		controlPlaneBaseURL != "" && controlPlaneToken != "",
	)
	laravelHeartbeatEnabled := envBool("LARAVEL_HEARTBEAT_ENABLED", true)
	laravelHeartbeatEveryN := envInt("LARAVEL_HEARTBEAT_EVERY_N", 10)
	controlPlanePolicySyncEnabled := envBool(
		"CONTROL_PLANE_POLICY_SYNC_ENABLED",
		controlPlaneBaseURL != "" && controlPlaneToken != "",
	)
	roleAccounts := parseRoleAccounts(os.Getenv("ROLE_ACCOUNTS"))
	roleAccountsBehavior := parseRoleAccountsBehavior(os.Getenv("ROLE_ACCOUNTS_BEHAVIOR"))
	domainTypos := parseDomainTypos(os.Getenv("DOMAIN_TYPOS"))
	disposableDomains := parseDisposableDomains(workerdata.DisposableDomains)
	mailFromAddress := strings.TrimSpace(os.Getenv("MAIL_FROM_ADDRESS"))
	policyJSON := strings.TrimSpace(os.Getenv("PROVIDER_REPLY_POLICY_JSON"))

	var replyPolicyEngine *verifier.ProviderReplyPolicyEngine
	if providerPolicyEngineEnabled {
		replyPolicyEngine = verifier.DefaultProviderReplyPolicyEngine()
		if policyJSON != "" {
			parsed, parseErr := verifier.ParseProviderReplyPolicyEngineJSON(policyJSON)
			if parseErr != nil {
				fmt.Printf("invalid PROVIDER_REPLY_POLICY_JSON: %v\n", parseErr)
				os.Exit(1)
			}
			replyPolicyEngine = parsed
		}
		replyPolicyEngine.Enabled = true
	}

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

	var controlPlaneClient *api.ControlPlaneClient
	if controlPlaneBaseURL != "" && controlPlaneToken != "" {
		controlPlaneClient = api.NewControlPlaneClient(controlPlaneBaseURL, controlPlaneToken)
	}

	if controlPlaneHeartbeatEnabled && controlPlaneClient == nil {
		fmt.Println("CONTROL_PLANE_BASE_URL and CONTROL_PLANE_TOKEN are required when CONTROL_PLANE_HEARTBEAT_ENABLED=true")
		os.Exit(1)
	}

	if controlPlanePolicySyncEnabled && controlPlaneClient == nil {
		fmt.Println("CONTROL_PLANE_BASE_URL and CONTROL_PLANE_TOKEN are required when CONTROL_PLANE_POLICY_SYNC_ENABLED=true")
		os.Exit(1)
	}

	verifierConfig := verifier.Config{
		DNSTimeout:                  dnsTimeout,
		SMTPConnectTimeout:          smtpConnectTimeout,
		SMTPReadTimeout:             smtpReadTimeout,
		SMTPEhloTimeout:             smtpEhloTimeout,
		MaxMXAttempts:               maxMxAttempts,
		RetryableNetworkRetries:     retryableNetworkRetries,
		BackoffBaseMs:               backoffMs,
		HeloName:                    heloName,
		MailFromAddress:             mailFromAddress,
		PerDomainConcurrency:        perDomainConcurrency,
		SMTPRateLimitPerMinute:      smtpRateLimit,
		DisposableDomains:           disposableDomains,
		RoleAccounts:                roleAccounts,
		RoleAccountsBehavior:        roleAccountsBehavior,
		DomainTypos:                 domainTypos,
		ProviderPolicyEngineEnabled: providerPolicyEngineEnabled,
		AdaptiveRetryEnabled:        adaptiveRetryEnabled,
		ProviderReplyPolicyEngine:   replyPolicyEngine,
	}

	serverMeta := map[string]interface{}{}
	if workerPool != "" {
		serverMeta["pool"] = workerPool
	}
	if providerAffinity != "" {
		serverMeta["provider_affinity"] = providerAffinity
	}
	if trustTier != "" {
		serverMeta["trust_tier"] = trustTier
	}

	cfg := worker.Config{
		PollInterval:       pollInterval,
		HeartbeatInterval:  heartbeatInterval,
		LeaseSeconds:       leaseSeconds,
		MaxConcurrency:     maxConcurrency,
		PolicyRefresh:      policyRefresh,
		WorkerID:           workerID,
		WorkerCapability:   workerCapability,
		BaseVerifierConfig: verifierConfig,
		Server: api.EngineServerPayload{
			Name:        serverName,
			IPAddress:   serverIP,
			Environment: serverEnv,
			Region:      serverRegion,
			Meta:        serverMeta,
		},
		ControlPlaneClient:            controlPlaneClient,
		ControlPlaneHeartbeatEnabled:  controlPlaneHeartbeatEnabled,
		LaravelHeartbeatEnabled:       laravelHeartbeatEnabled,
		LaravelHeartbeatEveryN:        laravelHeartbeatEveryN,
		ControlPlanePolicySyncEnabled: controlPlanePolicySyncEnabled,
		ProbeAttemptChainEnabled:      probeAttemptChainEnabled,
		UnknownReasonTaxonomyEnabled:  unknownReasonTaxonomyEnabled,
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

func envBool(key string, fallback bool) bool {
	value := strings.ToLower(strings.TrimSpace(os.Getenv(key)))
	switch value {
	case "1", "true", "yes", "on":
		return true
	case "0", "false", "no", "off":
		return false
	case "":
		return fallback
	default:
		return fallback
	}
}

func hostname() string {
	name, err := os.Hostname()
	if err != nil {
		return "worker"
	}

	return name
}

func parseRoleAccounts(value string) map[string]struct{} {
	if value == "" {
		return mapFromSlice([]string{"info", "admin", "support", "sales", "contact", "hello", "hr"})
	}

	return mapFromSlice(strings.Split(value, ","))
}

func parseRoleAccountsBehavior(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "" {
		return "risky"
	}

	if value == "risky" || value == "allow" {
		return value
	}

	return "risky"
}

func parseDomainTypos(value string) map[string]string {
	output := map[string]string{}
	if value == "" {
		return output
	}

	for _, pair := range strings.Split(value, ",") {
		pair = strings.TrimSpace(pair)
		if pair == "" {
			continue
		}

		parts := strings.SplitN(pair, "=", 2)
		if len(parts) != 2 {
			continue
		}

		typo := strings.ToLower(strings.TrimSpace(parts[0]))
		suggestion := strings.ToLower(strings.TrimSpace(parts[1]))
		if typo == "" || suggestion == "" {
			continue
		}

		output[typo] = suggestion
	}

	return output
}

func parseWorkerCapability(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "screening" || value == "smtp_probe" || value == "all" {
		return value
	}

	return "all"
}

func parseProviderAffinity(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	switch value {
	case "gmail", "microsoft", "yahoo", "generic":
		return value
	default:
		return ""
	}
}

func parseTrustTier(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	switch value {
	case "bronze", "silver", "gold", "platinum":
		return value
	default:
		return ""
	}
}

func parseDisposableDomains(data string) map[string]struct{} {
	output := map[string]struct{}{}

	for _, line := range strings.Split(data, "\n") {
		line = strings.ToLower(strings.TrimSpace(line))
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		output[line] = struct{}{}
	}

	return output
}

func mapFromSlice(values []string) map[string]struct{} {
	output := map[string]struct{}{}

	for _, value := range values {
		value = strings.ToLower(strings.TrimSpace(value))
		if value == "" {
			continue
		}

		output[value] = struct{}{}
	}

	return output
}
