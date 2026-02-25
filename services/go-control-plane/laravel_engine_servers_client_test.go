package main

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
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

func TestLaravelEngineServerClientRetriesOnRateLimit(t *testing.T) {
	var attempts int32
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		current := atomic.AddInt32(&attempts, 1)
		w.Header().Set("Content-Type", "application/json")
		if current == 1 {
			w.WriteHeader(http.StatusTooManyRequests)
			_ = json.NewEncoder(w).Encode(map[string]any{
				"error_code": "rate_limited",
				"message":    "Too many requests",
				"request_id": "req-429",
			})
			return
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"servers":          []map[string]any{},
				"verifier_domains": []map[string]any{},
			},
		})
	}))
	defer server.Close()

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL:        server.URL,
		LaravelInternalAPIToken:          "internal-token",
		LaravelInternalAPIRetryMax:       2,
		LaravelInternalAPIRetryBackoffMS: 1,
	})
	if client == nil {
		t.Fatal("expected configured client")
	}

	if _, _, err := client.ListServers(context.Background()); err != nil {
		t.Fatalf("expected retry flow to recover, got %v", err)
	}
	if got := atomic.LoadInt32(&attempts); got != 2 {
		t.Fatalf("expected 2 attempts, got %d", got)
	}
}

func TestLaravelEngineServerClientRetriesOnServerError(t *testing.T) {
	var attempts int32
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		current := atomic.AddInt32(&attempts, 1)
		w.Header().Set("Content-Type", "application/json")
		if current == 1 {
			w.WriteHeader(http.StatusInternalServerError)
			_ = json.NewEncoder(w).Encode(map[string]any{
				"error_code": "upstream_error",
				"message":    "Server error",
				"request_id": "req-500",
			})
			return
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"servers":          []map[string]any{},
				"verifier_domains": []map[string]any{},
			},
		})
	}))
	defer server.Close()

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL:        server.URL,
		LaravelInternalAPIToken:          "internal-token",
		LaravelInternalAPIRetryMax:       2,
		LaravelInternalAPIRetryBackoffMS: 1,
	})
	if client == nil {
		t.Fatal("expected configured client")
	}

	if _, _, err := client.ListServers(context.Background()); err != nil {
		t.Fatalf("expected retry flow to recover, got %v", err)
	}
	if got := atomic.LoadInt32(&attempts); got != 2 {
		t.Fatalf("expected 2 attempts, got %d", got)
	}
}

func TestLaravelEngineServerClientDoesNotRetryValidationErrors(t *testing.T) {
	var attempts int32
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		atomic.AddInt32(&attempts, 1)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusUnprocessableEntity)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"error_code": "validation_failed",
			"message":    "Validation failed.",
			"request_id": "req-422",
		})
	}))
	defer server.Close()

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL:        server.URL,
		LaravelInternalAPIToken:          "internal-token",
		LaravelInternalAPIRetryMax:       2,
		LaravelInternalAPIRetryBackoffMS: 1,
	})
	if client == nil {
		t.Fatal("expected configured client")
	}

	_, _, err := client.ListServers(context.Background())
	if err == nil {
		t.Fatal("expected validation error")
	}
	if got := atomic.LoadInt32(&attempts); got != 1 {
		t.Fatalf("expected no retry for 422, got %d attempts", got)
	}

	apiErr := &LaravelAPIError{}
	if !errors.As(err, &apiErr) {
		t.Fatalf("expected LaravelAPIError, got %T", err)
	}
	if apiErr.StatusCode != http.StatusUnprocessableEntity {
		t.Fatalf("expected 422 status, got %d", apiErr.StatusCode)
	}
}

func TestLaravelEngineServerClientListDecisionTraces(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/smtp-decision-traces" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}
		if got := r.URL.Query().Get("provider"); got != "gmail" {
			t.Fatalf("expected provider=gmail, got %q", got)
		}
		if got := r.URL.Query().Get("decision_class"); got != "unknown" {
			t.Fatalf("expected decision_class=unknown, got %q", got)
		}
		if got := r.URL.Query().Get("limit"); got != "10" {
			t.Fatalf("expected limit=10, got %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": []map[string]any{
				{
					"id":                  101,
					"provider":            "gmail",
					"decision_class":      "unknown",
					"reason_tag":          "provider_tempfail_unresolved",
					"attempt_chain":       []map[string]any{{"attempt_number": 1}},
					"verification_job_id": "job-1",
				},
			},
			"meta": map[string]any{
				"next_before_id": 99,
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

	response, err := client.ListDecisionTraces(context.Background(), LaravelDecisionTraceFilter{
		Provider:      "gmail",
		DecisionClass: "unknown",
		Limit:         10,
	})
	if err != nil {
		t.Fatalf("expected list decision traces to succeed, got %v", err)
	}
	if len(response.Records) != 1 {
		t.Fatalf("expected one trace record, got %d", len(response.Records))
	}
	if response.Records[0].Provider != "gmail" {
		t.Fatalf("expected provider gmail, got %s", response.Records[0].Provider)
	}
	if response.NextBeforeID != 99 {
		t.Fatalf("expected next_before_id 99, got %d", response.NextBeforeID)
	}
}

func TestLaravelEngineServerClientExecuteServerCommand(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-servers/42/commands" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}
		if r.Method != http.MethodPost {
			t.Fatalf("expected POST method, got %s", r.Method)
		}
		if got := r.Header.Get("X-Triggered-By"); got != "go-ui:test" {
			t.Fatalf("expected triggered by header, got %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"id":               "cmd-123",
				"engine_server_id": 42,
				"action":           "stop",
				"status":           "success",
				"request_id":       "req-1",
				"agent_command_id": "agt-999",
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

	command, err := client.ExecuteServerCommand(context.Background(), 42, LaravelEngineServerCommandPayload{
		Action: "stop",
		Reason: "maintenance",
	}, "go-ui:test")
	if err != nil {
		t.Fatalf("expected execute command to succeed, got %v", err)
	}
	if command.ID != "cmd-123" || command.Status != "success" {
		t.Fatalf("unexpected command response: %#v", command)
	}
}

func TestLaravelEngineServerClientLatestProvisioningBundleAllowsArrayDownloadURLs(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-servers/7/provisioning-bundles/latest" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}

		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"bundle_uuid":              "bundle-7",
				"engine_server_id":         7,
				"download_urls":            []any{},
				"install_command_template": nil,
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

	bundle, err := client.LatestProvisioningBundle(context.Background(), 7)
	if err != nil {
		t.Fatalf("expected latest bundle call to succeed, got %v", err)
	}
	if bundle == nil {
		t.Fatal("expected bundle response")
	}
	if len(bundle.DownloadURLs) != 0 {
		t.Fatalf("expected empty download URLs map, got %#v", bundle.DownloadURLs)
	}
}

func TestLaravelEngineServerClientListPools(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-pools" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}
		if r.Method != http.MethodGet {
			t.Fatalf("expected GET method, got %s", r.Method)
		}
		if got := r.Header.Get("Authorization"); got != "Bearer internal-token" {
			t.Fatalf("expected bearer token, got %q", got)
		}

		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": []map[string]any{
				{
					"id":          2,
					"slug":        "gmail-lowhit",
					"name":        "Gmail Low Hit",
					"is_active":   true,
					"is_default":  false,
					"description": "Low frequency profile for Gmail",
					"provider_profiles": map[string]string{
						"generic":   "standard",
						"gmail":     "low_hit",
						"microsoft": "standard",
						"yahoo":     "standard",
					},
					"linked_servers_count": 3,
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

	pools, err := client.ListPools(context.Background())
	if err != nil {
		t.Fatalf("expected list pools to succeed, got %v", err)
	}
	if len(pools) != 1 {
		t.Fatalf("expected one pool, got %d", len(pools))
	}
	if pools[0].Slug != "gmail-lowhit" {
		t.Fatalf("expected pool slug gmail-lowhit, got %q", pools[0].Slug)
	}
	if pools[0].ProviderProfiles["gmail"] != "low_hit" {
		t.Fatalf("expected gmail profile low_hit, got %q", pools[0].ProviderProfiles["gmail"])
	}
}

func TestLaravelEngineServerClientPoolMutations(t *testing.T) {
	var createSeen bool
	var updateSeen bool
	var archiveSeen bool
	var setDefaultSeen bool

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")

		switch {
		case r.Method == http.MethodPost && r.URL.Path == "/api/internal/engine-pools":
			createSeen = true
			var payload LaravelEnginePoolUpsertPayload
			if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
				t.Fatalf("failed to decode create payload: %v", err)
			}
			if payload.Slug != "gmail-lowhit" {
				t.Fatalf("expected create slug gmail-lowhit, got %q", payload.Slug)
			}
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"id":         7,
					"slug":       payload.Slug,
					"name":       payload.Name,
					"is_active":  true,
					"is_default": false,
					"provider_profiles": map[string]string{
						"generic":   "standard",
						"gmail":     "low_hit",
						"microsoft": "standard",
						"yahoo":     "standard",
					},
				},
			})
		case r.Method == http.MethodPut && r.URL.Path == "/api/internal/engine-pools/7":
			updateSeen = true
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"id":         7,
					"slug":       "gmail-lowhit",
					"name":       "Gmail Low Hit Updated",
					"is_active":  true,
					"is_default": false,
					"provider_profiles": map[string]string{
						"generic":   "standard",
						"gmail":     "low_hit",
						"microsoft": "warmup",
						"yahoo":     "standard",
					},
				},
			})
		case r.Method == http.MethodPost && r.URL.Path == "/api/internal/engine-pools/7/archive":
			archiveSeen = true
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"id":         7,
					"slug":       "gmail-lowhit",
					"name":       "Gmail Low Hit Updated",
					"is_active":  false,
					"is_default": false,
				},
			})
		case r.Method == http.MethodPost && r.URL.Path == "/api/internal/engine-pools/7/set-default":
			setDefaultSeen = true
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"id":         7,
					"slug":       "gmail-lowhit",
					"name":       "Gmail Low Hit Updated",
					"is_active":  true,
					"is_default": true,
				},
			})
		default:
			t.Fatalf("unexpected request %s %s", r.Method, r.URL.Path)
		}
	}))
	defer server.Close()

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL: server.URL,
		LaravelInternalAPIToken:   "internal-token",
	})
	if client == nil {
		t.Fatal("expected configured client")
	}

	payload := LaravelEnginePoolUpsertPayload{
		Slug:        "gmail-lowhit",
		Name:        "Gmail Low Hit",
		Description: "Low frequency profile for Gmail",
		IsActive:    true,
		ProviderProfiles: map[string]string{
			"generic":   "standard",
			"gmail":     "low_hit",
			"microsoft": "standard",
			"yahoo":     "standard",
		},
	}

	created, err := client.CreatePool(context.Background(), payload, "go-ui:test")
	if err != nil {
		t.Fatalf("expected create pool to succeed, got %v", err)
	}
	if created == nil || created.ID != 7 {
		t.Fatalf("unexpected create response %#v", created)
	}

	updated, err := client.UpdatePool(context.Background(), 7, payload, "go-ui:test")
	if err != nil {
		t.Fatalf("expected update pool to succeed, got %v", err)
	}
	if updated == nil || updated.Name != "Gmail Low Hit Updated" {
		t.Fatalf("unexpected update response %#v", updated)
	}

	archived, err := client.ArchivePool(context.Background(), 7, "go-ui:test")
	if err != nil {
		t.Fatalf("expected archive pool to succeed, got %v", err)
	}
	if archived == nil || archived.IsActive {
		t.Fatalf("unexpected archive response %#v", archived)
	}

	defaulted, err := client.SetDefaultPool(context.Background(), 7, "go-ui:test")
	if err != nil {
		t.Fatalf("expected set default pool to succeed, got %v", err)
	}
	if defaulted == nil || !defaulted.IsDefault {
		t.Fatalf("unexpected set default response %#v", defaulted)
	}

	if !createSeen || !updateSeen || !archiveSeen || !setDefaultSeen {
		t.Fatalf("expected all pool mutation endpoints to be called, create=%t update=%t archive=%t setDefault=%t", createSeen, updateSeen, archiveSeen, setDefaultSeen)
	}
}

func TestLaravelEngineServerClientDeleteServer(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-servers/42" {
			t.Fatalf("unexpected request path %q", r.URL.Path)
		}
		if r.Method != http.MethodDelete {
			t.Fatalf("expected DELETE method, got %s", r.Method)
		}
		if got := r.Header.Get("X-Triggered-By"); got != "go-ui:test" {
			t.Fatalf("expected triggered by header, got %q", got)
		}
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"id":      42,
				"name":    "engine-42",
				"deleted": true,
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

	deleted, err := client.DeleteServer(context.Background(), 42, "go-ui:test")
	if err != nil {
		t.Fatalf("expected delete server to succeed, got %v", err)
	}
	if deleted == nil {
		t.Fatal("expected deleted server payload")
	}
	if deleted.ID != 42 || deleted.Name != "engine-42" {
		t.Fatalf("unexpected delete response: %#v", deleted)
	}
}
