package api

import (
	"context"
	"encoding/json"
	"net/http"
)

type Policy struct {
	Mode                       string   `json:"mode"`
	Enabled                    bool     `json:"enabled"`
	DNSTimeoutMs               int      `json:"dns_timeout_ms"`
	SMTPConnectTimeoutMs       int      `json:"smtp_connect_timeout_ms"`
	SMTPReadTimeoutMs          int      `json:"smtp_read_timeout_ms"`
	MaxMXAttempts              int      `json:"max_mx_attempts"`
	MaxConcurrencyDefault      int      `json:"max_concurrency_default"`
	PerDomainConcurrency       int      `json:"per_domain_concurrency"`
	CatchAllDetectionEnabled   bool     `json:"catch_all_detection_enabled"`
	GlobalConnectsPerMinute    *int     `json:"global_connects_per_minute"`
	TempfailBackoffSeconds     *int     `json:"tempfail_backoff_seconds"`
	CircuitBreakerTempfailRate *float64 `json:"circuit_breaker_tempfail_rate"`
}

type PolicyResponse struct {
	Data struct {
		ContractVersion      string            `json:"contract_version"`
		EnginePaused         bool              `json:"engine_paused"`
		EnhancedModeEnabled  bool              `json:"enhanced_mode_enabled"`
		RoleAccountsBehavior string            `json:"role_accounts_behavior"`
		RoleAccountsList     []string          `json:"role_accounts_list"`
		Policies             map[string]Policy `json:"policies"`
	} `json:"data"`
}

func (c *Client) Policy(ctx context.Context) (*PolicyResponse, error) {
	status, body, err := c.do(ctx, http.MethodGet, "/api/verifier/policy", nil)
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}
	var resp PolicyResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}
