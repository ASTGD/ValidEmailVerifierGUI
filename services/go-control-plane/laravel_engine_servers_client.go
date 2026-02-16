package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
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
	Notes                  string                          `json:"notes"`
	LatestProvisioningInfo *LaravelProvisioningBundleBrief `json:"latest_provisioning_bundle"`
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
	BundleUUID             string            `json:"bundle_uuid"`
	EngineServerID         int               `json:"engine_server_id"`
	ExpiresAt              string            `json:"expires_at"`
	IsExpired              bool              `json:"is_expired"`
	DownloadURLs           map[string]string `json:"download_urls"`
	InstallCommandTemplate string            `json:"install_command_template"`
}

type LaravelEngineServerUpsertPayload struct {
	Name             string `json:"name"`
	IPAddress        string `json:"ip_address"`
	Environment      string `json:"environment"`
	Region           string `json:"region"`
	IsActive         bool   `json:"is_active"`
	DrainMode        bool   `json:"drain_mode"`
	MaxConcurrency   *int   `json:"max_concurrency"`
	HeloName         string `json:"helo_name"`
	MailFromAddress  string `json:"mail_from_address"`
	VerifierDomainID *int   `json:"verifier_domain_id"`
	Notes            string `json:"notes"`
}

type LaravelEngineServerClient struct {
	baseURL string
	token   string
	client  *http.Client
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

	return &LaravelEngineServerClient{
		baseURL: baseURL,
		token:   token,
		client: &http.Client{
			Timeout: time.Duration(timeoutSeconds) * time.Second,
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

func (c *LaravelEngineServerClient) doJSON(ctx context.Context, method string, path string, payload interface{}, triggeredBy string, target interface{}) error {
	requestURL := c.baseURL + path

	var body io.Reader
	if payload != nil {
		encoded, err := json.Marshal(payload)
		if err != nil {
			return err
		}
		body = bytes.NewReader(encoded)
	}

	request, err := http.NewRequestWithContext(ctx, method, requestURL, body)
	if err != nil {
		return err
	}
	request.Header.Set("Accept", "application/json")
	request.Header.Set("Authorization", fmt.Sprintf("Bearer %s", c.token))
	request.Header.Set("X-Internal-Token", c.token)
	if payload != nil {
		request.Header.Set("Content-Type", "application/json")
	}

	triggeredBy = strings.TrimSpace(triggeredBy)
	if triggeredBy != "" {
		request.Header.Set("X-Triggered-By", triggeredBy)
	}

	response, err := c.client.Do(request)
	if err != nil {
		return err
	}
	defer response.Body.Close()

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		errorBody := struct {
			Error   string            `json:"error"`
			Message string            `json:"message"`
			Errors  map[string]string `json:"errors"`
		}{}
		_ = json.NewDecoder(response.Body).Decode(&errorBody)
		message := strings.TrimSpace(errorBody.Error)
		if message == "" {
			message = strings.TrimSpace(errorBody.Message)
		}
		if message == "" {
			message = fmt.Sprintf("laravel internal api status %d", response.StatusCode)
		}

		return fmt.Errorf(message)
	}

	if target == nil {
		return nil
	}

	if err := json.NewDecoder(response.Body).Decode(target); err != nil {
		return err
	}

	return nil
}
