package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

type Client struct {
	baseURL    string
	token      string
	httpClient *http.Client
}

type EngineServerPayload struct {
	Name        string                 `json:"name"`
	IPAddress   string                 `json:"ip_address"`
	Environment string                 `json:"environment,omitempty"`
	Region      string                 `json:"region,omitempty"`
	Meta        map[string]interface{} `json:"meta,omitempty"`
}

type ClaimNextRequest struct {
	EngineServer     EngineServerPayload `json:"engine_server"`
	WorkerID         string              `json:"worker_id"`
	WorkerCapability string              `json:"worker_capability,omitempty"`
	LeaseSeconds     *int                `json:"lease_seconds,omitempty"`
}

type ClaimNextResponse struct {
	Data struct {
		ChunkID                  string `json:"chunk_id"`
		JobID                    string `json:"job_id"`
		ChunkNo                  int    `json:"chunk_no"`
		VerificationMode         string `json:"verification_mode"`
		ProcessingStage          string `json:"processing_stage"`
		WorkerCapabilityRequired string `json:"worker_capability_required"`
		LeaseExpiresAt           string `json:"lease_expires_at"`
		Input                    struct {
			Disk string `json:"disk"`
			Key  string `json:"key"`
		} `json:"input"`
	} `json:"data"`
}

type ChunkDetailsResponse struct {
	Data struct {
		ChunkID                  string `json:"chunk_id"`
		JobID                    string `json:"job_id"`
		ChunkNo                  int    `json:"chunk_no"`
		Status                   string `json:"status"`
		VerificationMode         string `json:"verification_mode"`
		ProcessingStage          string `json:"processing_stage"`
		WorkerCapabilityRequired string `json:"worker_capability_required"`
		Input                    struct {
			Disk string `json:"disk"`
			Key  string `json:"key"`
		} `json:"input"`
	} `json:"data"`
}

type SignedURLResponse struct {
	Data struct {
		Disk      string `json:"disk"`
		Key       string `json:"key"`
		URL       string `json:"url"`
		ExpiresIn int    `json:"expires_in"`
	} `json:"data"`
}

type OutputURLsResponse struct {
	Data struct {
		Disk      string `json:"disk"`
		ExpiresIn int    `json:"expires_in"`
		Targets   struct {
			Valid struct {
				Key string `json:"key"`
				URL string `json:"url"`
			} `json:"valid"`
			Invalid struct {
				Key string `json:"key"`
				URL string `json:"url"`
			} `json:"invalid"`
			Risky struct {
				Key string `json:"key"`
				URL string `json:"url"`
			} `json:"risky"`
		} `json:"targets"`
	} `json:"data"`
}

type HeartbeatResponse struct {
	Data struct {
		ServerID                 int    `json:"server_id"`
		Status                   string `json:"status"`
		HeartbeatThresholdMinute int    `json:"heartbeat_threshold_minutes"`
		Identity                 struct {
			HeloName        string `json:"helo_name"`
			MailFromAddress string `json:"mail_from_address"`
			IdentityDomain  string `json:"identity_domain"`
		} `json:"identity"`
	} `json:"data"`
}

type APIError struct {
	Status int
	Body   string
}

func (e APIError) Error() string {
	return fmt.Sprintf("api error: status=%d body=%s", e.Status, e.Body)
}

func NewClient(baseURL, token string) *Client {
	return &Client{
		baseURL:    strings.TrimRight(baseURL, "/"),
		token:      token,
		httpClient: &http.Client{Timeout: 30 * time.Second},
	}
}

func (c *Client) ClaimNext(ctx context.Context, req ClaimNextRequest) (*ClaimNextResponse, bool, error) {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/chunks/claim-next", req)
	if err != nil {
		return nil, false, err
	}
	if status == http.StatusNoContent {
		return nil, false, nil
	}
	if status < 200 || status >= 300 {
		return nil, false, APIError{Status: status, Body: string(body)}
	}
	var resp ClaimNextResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, false, err
	}

	return &resp, true, nil
}

func (c *Client) Heartbeat(ctx context.Context, server EngineServerPayload) (*HeartbeatResponse, error) {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/heartbeat", map[string]EngineServerPayload{
		"server": server,
	})
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}

	if len(body) == 0 {
		return nil, nil
	}

	var resp HeartbeatResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}

func (c *Client) ChunkDetails(ctx context.Context, chunkID string) (*ChunkDetailsResponse, error) {
	status, body, err := c.do(ctx, http.MethodGet, "/api/verifier/chunks/"+chunkID, nil)
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}
	var resp ChunkDetailsResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}

func (c *Client) InputURL(ctx context.Context, chunkID string) (*SignedURLResponse, error) {
	status, body, err := c.do(ctx, http.MethodGet, "/api/verifier/chunks/"+chunkID+"/input-url", nil)
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}
	var resp SignedURLResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}

func (c *Client) OutputURLs(ctx context.Context, chunkID string) (*OutputURLsResponse, error) {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/chunks/"+chunkID+"/output-urls", map[string]string{})
	if err != nil {
		return nil, err
	}
	if status < 200 || status >= 300 {
		return nil, APIError{Status: status, Body: string(body)}
	}
	var resp OutputURLsResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return &resp, nil
}

func (c *Client) CompleteChunk(ctx context.Context, chunkID string, payload map[string]interface{}) error {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/chunks/"+chunkID+"/complete", payload)
	if err != nil {
		return err
	}
	if status < 200 || status >= 300 {
		return APIError{Status: status, Body: string(body)}
	}

	return nil
}

func (c *Client) FailChunk(ctx context.Context, chunkID string, payload map[string]interface{}) error {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/chunks/"+chunkID+"/fail", payload)
	if err != nil {
		return err
	}
	if status < 200 || status >= 300 {
		return APIError{Status: status, Body: string(body)}
	}

	return nil
}

func (c *Client) LogChunk(ctx context.Context, chunkID string, payload map[string]interface{}) error {
	status, body, err := c.do(ctx, http.MethodPost, "/api/verifier/chunks/"+chunkID+"/log", payload)
	if err != nil {
		return err
	}
	if status < 200 || status >= 300 {
		return APIError{Status: status, Body: string(body)}
	}

	return nil
}

func (c *Client) do(ctx context.Context, method, path string, body interface{}) (int, []byte, error) {
	var reader io.Reader
	if body != nil {
		payload, err := json.Marshal(body)
		if err != nil {
			return 0, nil, err
		}
		reader = bytes.NewReader(payload)
	}

	req, err := http.NewRequestWithContext(ctx, method, c.baseURL+path, reader)
	if err != nil {
		return 0, nil, err
	}

	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return 0, nil, err
	}
	defer resp.Body.Close()

	data, err := io.ReadAll(resp.Body)
	if err != nil {
		return resp.StatusCode, nil, err
	}

	return resp.StatusCode, data, nil
}
