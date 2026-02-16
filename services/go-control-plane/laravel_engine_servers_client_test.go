package main

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestNewLaravelEngineServerClientRequiresConfig(t *testing.T) {
	if client := NewLaravelEngineServerClient(Config{}); client != nil {
		t.Fatalf("expected nil client when config missing, got %#v", client)
	}
}

func TestLaravelEngineServerClientListServers(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-servers" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer internal-token" {
			t.Fatalf("expected bearer token, got %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"servers": []map[string]any{
					{
						"id":         99,
						"name":       "engine-99",
						"ip_address": "10.0.0.99",
						"status":     "online",
					},
				},
				"verifier_domains": []map[string]any{
					{"id": 10, "domain": "example.org"},
				},
			},
		})
	}))
	defer server.Close()

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL: server.URL,
		LaravelInternalAPIToken:   "internal-token",
	})
	if client == nil {
		t.Fatal("expected configured client")
	}

	servers, domains, err := client.ListServers(context.Background())
	if err != nil {
		t.Fatalf("expected list servers to succeed, got %v", err)
	}
	if len(servers) != 1 || servers[0].ID != 99 {
		t.Fatalf("expected one server with id=99, got %#v", servers)
	}
	if len(domains) != 1 || domains[0].ID != 10 {
		t.Fatalf("expected one domain option with id=10, got %#v", domains)
	}
}
