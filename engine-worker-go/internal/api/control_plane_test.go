package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestControlPlaneHeartbeat(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatalf("expected POST, got %s", r.Method)
		}
		if r.URL.Path != "/api/workers/heartbeat" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}

		var payload ControlPlaneHeartbeatRequest
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			t.Fatalf("failed to decode payload: %v", err)
		}
		if payload.WorkerID != "worker-1" {
			t.Fatalf("unexpected worker id: %s", payload.WorkerID)
		}

		_ = json.NewEncoder(w).Encode(ControlPlaneHeartbeatResponse{
			DesiredState: "draining",
			Commands:     []string{"drain"},
		})
	}))
	defer server.Close()

	client := NewControlPlaneClient(server.URL, "token")
	resp, err := client.Heartbeat(context.Background(), ControlPlaneHeartbeatRequest{
		WorkerID: "worker-1",
		Status:   "running",
	})
	if err != nil {
		t.Fatalf("heartbeat returned error: %v", err)
	}

	if resp.DesiredState != "draining" {
		t.Fatalf("expected draining desired state, got %s", resp.DesiredState)
	}
	if len(resp.Commands) != 1 || resp.Commands[0] != "drain" {
		t.Fatalf("unexpected commands: %#v", resp.Commands)
	}
}

func TestControlPlaneProviderPolicies(t *testing.T) {
	t.Parallel()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet {
			t.Fatalf("expected GET, got %s", r.Method)
		}
		if r.URL.Path != "/api/providers/policies" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}

		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"policy_engine_enabled":  true,
				"adaptive_retry_enabled": true,
				"auto_protect_enabled":   false,
				"active_version":         "v2.2.0",
			},
		})
	}))
	defer server.Close()

	client := NewControlPlaneClient(server.URL, "token")
	resp, err := client.ProviderPolicies(context.Background())
	if err != nil {
		t.Fatalf("provider policies returned error: %v", err)
	}

	if !resp.Data.PolicyEngineEnabled {
		t.Fatalf("expected policy engine enabled")
	}
	if !resp.Data.AdaptiveRetryEnabled {
		t.Fatalf("expected adaptive retry enabled")
	}
	if resp.Data.ActiveVersion != "v2.2.0" {
		t.Fatalf("unexpected active version: %s", resp.Data.ActiveVersion)
	}
}
