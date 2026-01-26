package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type Client struct {
	baseURL    string
	token      string
	httpClient *http.Client
}

type Config struct {
	Enabled         bool     `json:"enabled"`
	IntervalMinutes int      `json:"interval_minutes"`
	RBLList         []string `json:"rbl_list"`
	ResolverMode    string   `json:"resolver_mode"`
	ResolverIP      string   `json:"resolver_ip"`
	ResolverPort    int      `json:"resolver_port"`
}

type Server struct {
	ID              int    `json:"id"`
	Name            string `json:"name"`
	IPAddress       string `json:"ip_address"`
	Environment     string `json:"environment"`
	Region          string `json:"region"`
	IsActive        bool   `json:"is_active"`
	DrainMode       bool   `json:"drain_mode"`
	LastHeartbeatAt string `json:"last_heartbeat_at"`
}

type CheckResult struct {
	RBL          string `json:"rbl"`
	Listed       bool   `json:"listed"`
	Response     string `json:"response,omitempty"`
	ErrorMessage string `json:"error_message,omitempty"`
}

type CheckPayload struct {
	ServerID  int           `json:"server_id,omitempty"`
	ServerIP  string        `json:"server_ip,omitempty"`
	CheckedAt time.Time     `json:"checked_at"`
	Results   []CheckResult `json:"results"`
}

type configResponse struct {
	Data Config `json:"data"`
}

type serversResponse struct {
	Data struct {
		Servers []Server `json:"servers"`
	} `json:"data"`
}

func NewClient(baseURL, token string, timeout time.Duration) *Client {
	baseURL = strings.TrimRight(baseURL, "/")

	return &Client{
		baseURL: baseURL,
		token:   token,
		httpClient: &http.Client{
			Timeout: timeout,
		},
	}
}

func (c *Client) FetchConfig(ctx context.Context) (Config, error) {
	var response configResponse
	if err := c.get(ctx, "/api/monitor/config", &response); err != nil {
		return Config{}, err
	}

	return response.Data, nil
}

func (c *Client) FetchServers(ctx context.Context) ([]Server, error) {
	var response serversResponse
	if err := c.get(ctx, "/api/monitor/servers", &response); err != nil {
		return nil, err
	}

	return response.Data.Servers, nil
}

func (c *Client) SubmitChecks(ctx context.Context, payload CheckPayload) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	request, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+"/api/monitor/checks", bytes.NewReader(body))
	if err != nil {
		return err
	}

	request.Header.Set("Authorization", "Bearer "+c.token)
	request.Header.Set("Content-Type", "application/json")

	response, err := c.httpClient.Do(request)
	if err != nil {
		return err
	}
	defer response.Body.Close()

	if response.StatusCode >= 300 {
		return fmt.Errorf("monitor checks returned status %d", response.StatusCode)
	}

	return nil
}

func (c *Client) get(ctx context.Context, path string, output interface{}) error {
	request, err := http.NewRequestWithContext(ctx, http.MethodGet, c.baseURL+path, nil)
	if err != nil {
		return err
	}

	request.Header.Set("Authorization", "Bearer "+c.token)

	response, err := c.httpClient.Do(request)
	if err != nil {
		return err
	}
	defer response.Body.Close()

	if response.StatusCode >= 300 {
		return fmt.Errorf("monitor request returned status %d", response.StatusCode)
	}

	return json.NewDecoder(response.Body).Decode(output)
}
