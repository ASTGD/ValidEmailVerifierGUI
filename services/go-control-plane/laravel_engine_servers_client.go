package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"time"
)

type LaravelEngineServerRecord struct {
	ID                     int                             `json:"id"`
	Name                   string                          `json:"name"`
	IPAddress              string                          `json:"ip_address"`
	Environment            string                          `json:"environment"`
	Region                 string                          `json:"region"`
	LastHeartbeatAt        string                          `json:"last_heartbeat_at"`
	Status                 string                          `json:"status"`
	IsActive               bool                            `json:"is_active"`
	DrainMode              bool                            `json:"drain_mode"`
	MaxConcurrency         int                             `json:"max_concurrency"`
	HeloName               string                          `json:"helo_name"`
	MailFromAddress        string                          `json:"mail_from_address"`
	IdentityDomain         string                          `json:"identity_domain"`
	VerifierDomainID       int                             `json:"verifier_domain_id"`
	VerifierDomain         string                          `json:"verifier_domain"`
	WorkerPoolID           int                             `json:"worker_pool_id"`
	WorkerPoolSlug         string                          `json:"worker_pool_slug"`
	WorkerPoolName         string                          `json:"worker_pool_name"`
	Notes                  string                          `json:"notes"`
	ProcessControlMode     string                          `json:"process_control_mode"`
	AgentEnabled           bool                            `json:"agent_enabled"`
	AgentBaseURL           string                          `json:"agent_base_url"`
	AgentTimeoutSeconds    int                             `json:"agent_timeout_seconds"`
	AgentVerifyTLS         bool                            `json:"agent_verify_tls"`
	AgentServiceName       string                          `json:"agent_service_name"`
	LastAgentSeenAt        string                          `json:"last_agent_seen_at"`
	LastAgentError         string                          `json:"last_agent_error"`
	LatestProvisioningInfo *LaravelProvisioningBundleBrief `json:"latest_provisioning_bundle"`
	ProcessState           string                          `json:"process_state"`
	HeartbeatState         string                          `json:"heartbeat_state"`
	HeartbeatAgeSeconds    *int                            `json:"heartbeat_age_seconds"`
	LastTransitionAt       string                          `json:"last_transition_at"`
	LastCommandStatus      string                          `json:"last_command_status"`
	LastCommandRequestID   string                          `json:"last_command_request_id"`
	RuntimeMatchStatus     string                          `json:"-"`
	RuntimeMatchWorkerID   string                          `json:"-"`
	RuntimeMatchDetail     string                          `json:"-"`
	HeartbeatAgeText       string                          `json:"-"`
	UpdatedAtDisplay       string                          `json:"-"`
}

type LaravelEnginePoolRecord struct {
	ID                int               `json:"id"`
	Slug              string            `json:"slug"`
	Name              string            `json:"name"`
	Description       string            `json:"description"`
	IsActive          bool              `json:"is_active"`
	IsDefault         bool              `json:"is_default"`
	ProviderProfiles  map[string]string `json:"provider_profiles"`
	LinkedServersCount int              `json:"linked_servers_count"`
	CreatedAt         string            `json:"created_at"`
	UpdatedAt         string            `json:"updated_at"`
}

type LaravelProvisioningBundleBrief struct {
	BundleUUID string `json:"bundle_uuid"`
	ExpiresAt  string `json:"expires_at"`
	IsExpired  bool   `json:"is_expired"`
	CreatedAt  string `json:"created_at"`
}

type LaravelVerifierDomainOption struct {
	ID     int    `json:"id"`
	Domain string `json:"domain"`
}

type LaravelProvisioningBundleDetails struct {
	BundleUUID             string              `json:"bundle_uuid"`
	EngineServerID         int                 `json:"engine_server_id"`
	ExpiresAt              string              `json:"expires_at"`
	IsExpired              bool                `json:"is_expired"`
	DownloadURLs           LaravelDownloadURLs `json:"download_urls"`
	InstallCommandTemplate string              `json:"install_command_template"`
}

type LaravelDownloadURLs map[string]string

func (urls *LaravelDownloadURLs) UnmarshalJSON(payload []byte) error {
	trimmed := strings.TrimSpace(string(payload))
	if trimmed == "" || trimmed == "null" {
		*urls = LaravelDownloadURLs{}
		return nil
	}

	if strings.HasPrefix(trimmed, "[") {
		var ignored []interface{}
		if err := json.Unmarshal(payload, &ignored); err != nil {
			return err
		}
		*urls = LaravelDownloadURLs{}
		return nil
	}

	decoded := make(map[string]string)
	if err := json.Unmarshal(payload, &decoded); err != nil {
		return err
	}

	*urls = decoded
	return nil
}

type LaravelDecisionTraceFilter struct {
	Provider      string
	DecisionClass string
	ReasonTag     string
	PolicyVersion string
	Limit         int
	BeforeID      int64
}

type LaravelDecisionTraceRecord struct {
	ID                     int64                    `json:"id"`
	VerificationJobID      string                   `json:"verification_job_id"`
	VerificationJobChunkID string                   `json:"verification_job_chunk_id"`
	EmailHash              string                   `json:"email_hash"`
	Provider               string                   `json:"provider"`
	PolicyVersion          string                   `json:"policy_version"`
	MatchedRuleID          string                   `json:"matched_rule_id"`
	DecisionClass          string                   `json:"decision_class"`
	SMTPCode               string                   `json:"smtp_code"`
	EnhancedCode           string                   `json:"enhanced_code"`
	RetryStrategy          string                   `json:"retry_strategy"`
	ReasonTag              string                   `json:"reason_tag"`
	ConfidenceHint         string                   `json:"confidence_hint"`
	SessionStrategyID      string                   `json:"session_strategy_id"`
	AttemptRoute           map[string]interface{}   `json:"attempt_route"`
	AttemptChain           []map[string]interface{} `json:"attempt_chain"`
	ObservedAt             string                   `json:"observed_at"`
	CreatedAt              string                   `json:"created_at"`
}

type LaravelDecisionTraceResponse struct {
	Records      []LaravelDecisionTraceRecord
	NextBeforeID int64
}

type LaravelEngineServerUpsertPayload struct {
	Name                string `json:"name"`
	IPAddress           string `json:"ip_address"`
	Environment         string `json:"environment"`
	Region              string `json:"region"`
	IsActive            bool   `json:"is_active"`
	DrainMode           bool   `json:"drain_mode"`
	MaxConcurrency      *int   `json:"max_concurrency"`
	HeloName            string `json:"helo_name"`
	MailFromAddress     string `json:"mail_from_address"`
	VerifierDomainID    *int   `json:"verifier_domain_id"`
	WorkerPoolID        *int   `json:"worker_pool_id"`
	Notes               string `json:"notes"`
	ProcessControlMode  string `json:"process_control_mode"`
	AgentEnabled        bool   `json:"agent_enabled"`
	AgentBaseURL        string `json:"agent_base_url"`
	AgentTimeoutSeconds *int   `json:"agent_timeout_seconds"`
	AgentVerifyTLS      bool   `json:"agent_verify_tls"`
	AgentServiceName    string `json:"agent_service_name"`
}

type LaravelEngineServerCommandPayload struct {
	Action         string `json:"action"`
	Reason         string `json:"reason,omitempty"`
	IdempotencyKey string `json:"idempotency_key,omitempty"`
}

type LaravelEnginePoolUpsertPayload struct {
	Slug             string            `json:"slug"`
	Name             string            `json:"name"`
	Description      string            `json:"description"`
	IsActive         bool              `json:"is_active"`
	IsDefault        bool              `json:"is_default"`
	ProviderProfiles map[string]string `json:"provider_profiles"`
}

type LaravelEngineServerCommandRecord struct {
	ID             string `json:"id"`
	EngineServerID int    `json:"engine_server_id"`
	Action         string `json:"action"`
	Status         string `json:"status"`
	Source         string `json:"source"`
	RequestID      string `json:"request_id"`
	IdempotencyKey string `json:"idempotency_key"`
	AgentCommandID string `json:"agent_command_id"`
	Reason         string `json:"reason"`
	ErrorMessage   string `json:"error_message"`
	StartedAt      string `json:"started_at"`
	FinishedAt     string `json:"finished_at"`
	CreatedAt      string `json:"created_at"`
	UpdatedAt      string `json:"updated_at"`
}

type LaravelEngineServerClient struct {
	baseURL        string
	token          string
	client         *http.Client
	retryMax       int
	retryBackoffMS int
	metrics        internalAPIRequestMetrics
}

type internalAPIRequestMetrics struct {
	mu                 sync.Mutex
	requests           map[string]int64
	failures           map[string]int64
	failureByCode      map[string]int64
	durationTotalMS    map[string]float64
	provisionDuration  float64
	provisionDurations int64
}

type InternalAPIMetricsSnapshot struct {
	RequestsByActionClass  map[string]int64
	FailuresByActionClass  map[string]int64
	FailureByCode          map[string]int64
	ProvisionDurationSumMS float64
	ProvisionDurationCount int64
}

type LaravelAPIError struct {
	StatusCode int
	ErrorCode  string
	Message    string
	RequestID  string
	retryable  bool
}

func (e *LaravelAPIError) Error() string {
	message := strings.TrimSpace(e.Message)
	if message == "" {
		message = "laravel internal api error"
	}
	if strings.TrimSpace(e.RequestID) != "" {
		return fmt.Sprintf("%s (request id: %s)", message, e.RequestID)
	}

	return message
}

func (e *LaravelAPIError) Retryable() bool {
	return e.retryable
}

func NewLaravelEngineServerClient(cfg Config) *LaravelEngineServerClient {
	baseURL := strings.TrimRight(strings.TrimSpace(cfg.LaravelInternalAPIBaseURL), "/")
	token := strings.TrimSpace(cfg.LaravelInternalAPIToken)
	if baseURL == "" || token == "" {
		return nil
	}

	timeoutSeconds := cfg.LaravelInternalAPITimeoutSeconds
	if timeoutSeconds <= 0 {
		timeoutSeconds = 5
	}

	retryMax := cfg.LaravelInternalAPIRetryMax
	if retryMax < 0 {
		retryMax = 0
	}

	retryBackoffMS := cfg.LaravelInternalAPIRetryBackoffMS
	if retryBackoffMS < 0 {
		retryBackoffMS = 0
	}

	return &LaravelEngineServerClient{
		baseURL:        baseURL,
		token:          token,
		retryMax:       retryMax,
		retryBackoffMS: retryBackoffMS,
		client: &http.Client{
			Timeout: time.Duration(timeoutSeconds) * time.Second,
		},
		metrics: internalAPIRequestMetrics{
			requests:        make(map[string]int64),
			failures:        make(map[string]int64),
			failureByCode:   make(map[string]int64),
			durationTotalMS: make(map[string]float64),
		},
	}
}

func (c *LaravelEngineServerClient) ListServers(ctx context.Context) ([]LaravelEngineServerRecord, []LaravelVerifierDomainOption, error) {
	if c == nil {
		return nil, nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data struct {
			Servers         []LaravelEngineServerRecord   `json:"servers"`
			VerifierDomains []LaravelVerifierDomainOption `json:"verifier_domains"`
		} `json:"data"`
	}{}

	if err := c.doJSON(ctx, http.MethodGet, "/api/internal/engine-servers", nil, "", &responseBody); err != nil {
		return nil, nil, err
	}

	return responseBody.Data.Servers, responseBody.Data.VerifierDomains, nil
}

func (c *LaravelEngineServerClient) ListPools(ctx context.Context) ([]LaravelEnginePoolRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data []LaravelEnginePoolRecord `json:"data"`
	}{}

	if err := c.doJSON(ctx, http.MethodGet, "/api/internal/engine-pools", nil, "", &responseBody); err != nil {
		return nil, err
	}

	return responseBody.Data, nil
}

func (c *LaravelEngineServerClient) CreatePool(ctx context.Context, payload LaravelEnginePoolUpsertPayload, triggeredBy string) (*LaravelEnginePoolRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEnginePoolRecord `json:"data"`
	}{}
	if err := c.doJSON(ctx, http.MethodPost, "/api/internal/engine-pools", payload, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) UpdatePool(ctx context.Context, poolID int, payload LaravelEnginePoolUpsertPayload, triggeredBy string) (*LaravelEnginePoolRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEnginePoolRecord `json:"data"`
	}{}
	path := "/api/internal/engine-pools/" + url.PathEscape(strconv.Itoa(poolID))
	if err := c.doJSON(ctx, http.MethodPut, path, payload, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) ArchivePool(ctx context.Context, poolID int, triggeredBy string) (*LaravelEnginePoolRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEnginePoolRecord `json:"data"`
	}{}
	path := "/api/internal/engine-pools/" + url.PathEscape(strconv.Itoa(poolID)) + "/archive"
	if err := c.doJSON(ctx, http.MethodPost, path, map[string]any{}, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) SetDefaultPool(ctx context.Context, poolID int, triggeredBy string) (*LaravelEnginePoolRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEnginePoolRecord `json:"data"`
	}{}
	path := "/api/internal/engine-pools/" + url.PathEscape(strconv.Itoa(poolID)) + "/set-default"
	if err := c.doJSON(ctx, http.MethodPost, path, map[string]any{}, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) CreateServer(ctx context.Context, payload LaravelEngineServerUpsertPayload, triggeredBy string) (*LaravelEngineServerRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEngineServerRecord `json:"data"`
	}{}
	if err := c.doJSON(ctx, http.MethodPost, "/api/internal/engine-servers", payload, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) UpdateServer(ctx context.Context, serverID int, payload LaravelEngineServerUpsertPayload, triggeredBy string) (*LaravelEngineServerRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEngineServerRecord `json:"data"`
	}{}
	path := "/api/internal/engine-servers/" + url.PathEscape(strconv.Itoa(serverID))
	if err := c.doJSON(ctx, http.MethodPut, path, payload, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) DeleteServer(ctx context.Context, serverID int, triggeredBy string) (*LaravelEngineServerRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEngineServerRecord `json:"data"`
	}{}
	path := "/api/internal/engine-servers/" + url.PathEscape(strconv.Itoa(serverID))
	if err := c.doJSON(ctx, http.MethodDelete, path, nil, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) GenerateProvisioningBundle(ctx context.Context, serverID int, triggeredBy string) (*LaravelProvisioningBundleDetails, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelProvisioningBundleDetails `json:"data"`
	}{}
	path := "/api/internal/engine-servers/" + url.PathEscape(strconv.Itoa(serverID)) + "/provisioning-bundles"
	if err := c.doJSON(ctx, http.MethodPost, path, map[string]any{}, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) LatestProvisioningBundle(ctx context.Context, serverID int) (*LaravelProvisioningBundleDetails, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelProvisioningBundleDetails `json:"data"`
	}{}
	path := "/api/internal/engine-servers/" + url.PathEscape(strconv.Itoa(serverID)) + "/provisioning-bundles/latest"
	if err := c.doJSON(ctx, http.MethodGet, path, nil, "", &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) ExecuteServerCommand(
	ctx context.Context,
	serverID int,
	payload LaravelEngineServerCommandPayload,
	triggeredBy string,
) (*LaravelEngineServerCommandRecord, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	responseBody := struct {
		Data LaravelEngineServerCommandRecord `json:"data"`
	}{}
	path := "/api/internal/engine-servers/" + url.PathEscape(strconv.Itoa(serverID)) + "/commands"
	if err := c.doJSON(ctx, http.MethodPost, path, payload, triggeredBy, &responseBody); err != nil {
		return nil, err
	}

	return &responseBody.Data, nil
}

func (c *LaravelEngineServerClient) ListDecisionTraces(
	ctx context.Context,
	filter LaravelDecisionTraceFilter,
) (*LaravelDecisionTraceResponse, error) {
	if c == nil {
		return nil, fmt.Errorf("laravel engine server client is not configured")
	}

	params := url.Values{}
	if provider := strings.ToLower(strings.TrimSpace(filter.Provider)); provider != "" {
		params.Set("provider", provider)
	}
	if decisionClass := strings.ToLower(strings.TrimSpace(filter.DecisionClass)); decisionClass != "" {
		params.Set("decision_class", decisionClass)
	}
	if reasonTag := strings.ToLower(strings.TrimSpace(filter.ReasonTag)); reasonTag != "" {
		params.Set("reason_tag", reasonTag)
	}
	if policyVersion := strings.TrimSpace(filter.PolicyVersion); policyVersion != "" {
		params.Set("policy_version", policyVersion)
	}
	if filter.Limit > 0 {
		params.Set("limit", strconv.Itoa(filter.Limit))
	}
	if filter.BeforeID > 0 {
		params.Set("before_id", strconv.FormatInt(filter.BeforeID, 10))
	}

	path := "/api/internal/smtp-decision-traces"
	if encoded := params.Encode(); encoded != "" {
		path += "?" + encoded
	}

	responseBody := struct {
		Data []LaravelDecisionTraceRecord `json:"data"`
		Meta struct {
			NextBeforeID int64 `json:"next_before_id"`
		} `json:"meta"`
	}{}
	if err := c.doJSON(ctx, http.MethodGet, path, nil, "", &responseBody); err != nil {
		return nil, err
	}

	return &LaravelDecisionTraceResponse{
		Records:      responseBody.Data,
		NextBeforeID: responseBody.Meta.NextBeforeID,
	}, nil
}

func (c *LaravelEngineServerClient) doJSON(ctx context.Context, method string, path string, payload interface{}, triggeredBy string, target interface{}) error {
	totalAttempts := c.retryMax + 1
	if totalAttempts < 1 {
		totalAttempts = 1
	}

	var lastErr error
	for attempt := 0; attempt < totalAttempts; attempt++ {
		statusCode, err := c.doJSONOnce(ctx, method, path, payload, triggeredBy, target)
		if err == nil {
			return nil
		}

		lastErr = err
		apiErr := &LaravelAPIError{}
		retryableStatus := statusCode == http.StatusTooManyRequests || statusCode >= 500
		retryable := retryableStatus || errors.Is(err, context.DeadlineExceeded)
		if errors.As(err, &apiErr) {
			retryable = apiErr.Retryable()
		} else if errors.Is(err, context.Canceled) {
			retryable = false
		} else {
			retryable = true
		}

		if !retryable || attempt == totalAttempts-1 {
			return err
		}

		backoff := time.Duration((attempt+1)*c.retryBackoffMS) * time.Millisecond
		if backoff <= 0 {
			continue
		}

		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(backoff):
		}

	}

	if lastErr != nil {
		return lastErr
	}

	return fmt.Errorf("laravel internal api request failed")
}

func (c *LaravelEngineServerClient) doJSONOnce(
	ctx context.Context,
	method string,
	path string,
	payload interface{},
	triggeredBy string,
	target interface{},
) (int, error) {
	requestURL := c.baseURL + path
	action := internalAPIAction(method, path)

	var body []byte
	if payload != nil {
		encoded, err := json.Marshal(payload)
		if err != nil {
			return 0, err
		}
		body = encoded
	}

	startedAt := time.Now()
	var reader io.Reader
	if len(body) > 0 {
		reader = bytes.NewReader(body)
	}

	request, err := http.NewRequestWithContext(ctx, method, requestURL, reader)
	if err != nil {
		return 0, err
	}
	request.Header.Set("Accept", "application/json")
	request.Header.Set("Authorization", fmt.Sprintf("Bearer %s", c.token))
	request.Header.Set("X-Internal-Token", c.token)
	if len(body) > 0 {
		request.Header.Set("Content-Type", "application/json")
	}

	triggeredBy = strings.TrimSpace(triggeredBy)
	if triggeredBy != "" {
		request.Header.Set("X-Triggered-By", triggeredBy)
	}

	response, err := c.client.Do(request)
	if err != nil {
		durationMS := float64(time.Since(startedAt).Milliseconds())
		c.recordMetric(action, "network", true, "request_failed", durationMS, action == "provision_bundle")

		return 0, err
	}
	defer response.Body.Close()
	durationMS := float64(time.Since(startedAt).Milliseconds())

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		errorBody := struct {
			ErrorCode string                 `json:"error_code"`
			Error     string                 `json:"error"`
			Message   string                 `json:"message"`
			RequestID string                 `json:"request_id"`
			Errors    map[string]interface{} `json:"errors"`
		}{}
		_ = json.NewDecoder(response.Body).Decode(&errorBody)
		message := strings.TrimSpace(errorBody.Message)
		if message == "" {
			message = strings.TrimSpace(errorBody.Error)
		}
		if message == "" {
			message = fmt.Sprintf("laravel internal api status %d", response.StatusCode)
		}

		requestID := strings.TrimSpace(errorBody.RequestID)
		if requestID == "" {
			requestID = strings.TrimSpace(response.Header.Get("X-Request-Id"))
		}
		errorCode := strings.TrimSpace(errorBody.ErrorCode)
		if errorCode == "" {
			switch response.StatusCode {
			case http.StatusUnauthorized:
				errorCode = "unauthorized"
			case http.StatusForbidden:
				errorCode = "forbidden"
			case http.StatusUnprocessableEntity:
				errorCode = "validation_failed"
			case http.StatusTooManyRequests:
				errorCode = "rate_limited"
			default:
				if response.StatusCode >= 500 {
					errorCode = "upstream_error"
				} else {
					errorCode = "request_failed"
				}
			}
		}

		statusClass := classifyStatusCode(response.StatusCode)
		c.recordMetric(action, statusClass, true, errorCode, durationMS, action == "provision_bundle")

		return response.StatusCode, &LaravelAPIError{
			StatusCode: response.StatusCode,
			ErrorCode:  errorCode,
			Message:    message,
			RequestID:  requestID,
			retryable:  response.StatusCode == http.StatusTooManyRequests || response.StatusCode >= 500,
		}
	}

	if target == nil {
		statusClass := classifyStatusCode(response.StatusCode)
		c.recordMetric(action, statusClass, false, "", durationMS, action == "provision_bundle")

		return response.StatusCode, nil
	}

	if err := json.NewDecoder(response.Body).Decode(target); err != nil {
		statusClass := classifyStatusCode(response.StatusCode)
		c.recordMetric(action, statusClass, true, "decode_failed", durationMS, action == "provision_bundle")

		return response.StatusCode, err
	}

	statusClass := classifyStatusCode(response.StatusCode)
	c.recordMetric(action, statusClass, false, "", durationMS, action == "provision_bundle")

	return response.StatusCode, nil
}

func (c *LaravelEngineServerClient) recordMetric(action string, statusClass string, failed bool, errorCode string, durationMS float64, isProvision bool) {
	if c == nil {
		return
	}

	key := action + "|" + statusClass

	c.metrics.mu.Lock()
	defer c.metrics.mu.Unlock()

	c.metrics.requests[key]++
	c.metrics.durationTotalMS[key] += durationMS
	if failed {
		c.metrics.failures[key]++
		if strings.TrimSpace(errorCode) != "" {
			c.metrics.failureByCode[action+"|"+errorCode]++
		}
	}
	if isProvision {
		c.metrics.provisionDuration += durationMS
		c.metrics.provisionDurations++
	}
}

func (c *LaravelEngineServerClient) MetricsSnapshot() InternalAPIMetricsSnapshot {
	if c == nil {
		return InternalAPIMetricsSnapshot{
			RequestsByActionClass: make(map[string]int64),
			FailuresByActionClass: make(map[string]int64),
			FailureByCode:         make(map[string]int64),
		}
	}

	c.metrics.mu.Lock()
	defer c.metrics.mu.Unlock()

	requests := make(map[string]int64, len(c.metrics.requests))
	for key, value := range c.metrics.requests {
		requests[key] = value
	}
	failures := make(map[string]int64, len(c.metrics.failures))
	for key, value := range c.metrics.failures {
		failures[key] = value
	}
	failureByCode := make(map[string]int64, len(c.metrics.failureByCode))
	for key, value := range c.metrics.failureByCode {
		failureByCode[key] = value
	}

	return InternalAPIMetricsSnapshot{
		RequestsByActionClass:  requests,
		FailuresByActionClass:  failures,
		FailureByCode:          failureByCode,
		ProvisionDurationSumMS: c.metrics.provisionDuration,
		ProvisionDurationCount: c.metrics.provisionDurations,
	}
}

func internalAPIAction(method string, path string) string {
	normalized := strings.TrimSpace(path)
	switch {
	case normalized == "/api/internal/engine-pools" && strings.EqualFold(method, http.MethodGet):
		return "list_pools"
	case normalized == "/api/internal/engine-pools" && strings.EqualFold(method, http.MethodPost):
		return "create_pool"
	case strings.HasSuffix(normalized, "/archive") && strings.Contains(normalized, "/api/internal/engine-pools/"):
		return "archive_pool"
	case strings.HasSuffix(normalized, "/set-default") && strings.Contains(normalized, "/api/internal/engine-pools/"):
		return "set_default_pool"
	case strings.HasPrefix(normalized, "/api/internal/engine-pools/") && strings.EqualFold(method, http.MethodPut):
		return "update_pool"
	case normalized == "/api/internal/engine-servers" && strings.EqualFold(method, http.MethodGet):
		return "list_servers"
	case normalized == "/api/internal/engine-servers" && strings.EqualFold(method, http.MethodPost):
		return "create_server"
	case strings.HasPrefix(normalized, "/api/internal/smtp-decision-traces") && strings.EqualFold(method, http.MethodGet):
		return "list_decision_traces"
	case strings.HasSuffix(normalized, "/provisioning-bundles/latest"):
		return "latest_bundle"
	case strings.HasSuffix(normalized, "/provisioning-bundles"):
		return "provision_bundle"
	case strings.HasSuffix(normalized, "/commands") && strings.EqualFold(method, http.MethodPost):
		return "execute_server_command"
	case strings.HasPrefix(normalized, "/api/internal/engine-servers/") && strings.EqualFold(method, http.MethodPut):
		return "update_server"
	case strings.HasPrefix(normalized, "/api/internal/engine-servers/") && strings.EqualFold(method, http.MethodDelete):
		return "delete_server"
	default:
		return "unknown"
	}
}

func classifyStatusCode(statusCode int) string {
	switch {
	case statusCode >= 200 && statusCode < 300:
		return "2xx"
	case statusCode >= 400 && statusCode < 500:
		return "4xx"
	case statusCode >= 500:
		return "5xx"
	default:
		return "unknown"
	}
}
