package main

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/redis/go-redis/v9"
)

func TestRequireSameOriginUIAllowsSameOrigin(t *testing.T) {
	s := &Server{}
	called := false

	handler := s.requireSameOriginUI(func(w http.ResponseWriter, _ *http.Request) {
		called = true
		w.WriteHeader(http.StatusNoContent)
	})

	req := httptest.NewRequest(http.MethodPost, "http://app.local/verifier-engine-room/settings", nil)
	req.Host = "app.local"
	req.Header.Set("Origin", "http://app.local")

	resp := httptest.NewRecorder()
	handler(resp, req)

	if resp.Code != http.StatusNoContent {
		t.Fatalf("expected status %d, got %d", http.StatusNoContent, resp.Code)
	}
	if !called {
		t.Fatal("expected wrapped handler to be called")
	}
}

func TestRequireSameOriginUIRejectsCrossOrigin(t *testing.T) {
	s := &Server{}
	called := false

	handler := s.requireSameOriginUI(func(w http.ResponseWriter, _ *http.Request) {
		called = true
		w.WriteHeader(http.StatusNoContent)
	})

	req := httptest.NewRequest(http.MethodPost, "http://app.local/verifier-engine-room/settings", nil)
	req.Host = "app.local"
	req.Header.Set("Origin", "http://evil.local")

	resp := httptest.NewRecorder()
	handler(resp, req)

	if resp.Code != http.StatusForbidden {
		t.Fatalf("expected status %d, got %d", http.StatusForbidden, resp.Code)
	}
	if called {
		t.Fatal("expected wrapped handler not to be called")
	}
	if !strings.Contains(resp.Body.String(), "forbidden") {
		t.Fatalf("expected forbidden body, got %q", resp.Body.String())
	}
}

func TestUIRoutesRejectCrossOriginPosts(t *testing.T) {
	token := "secret"
	s := NewServer(nil, nil, Config{ControlPlaneToken: token})
	router := s.Router()

	routes := []string{
		"/verifier-engine-room/workers/worker-1/pause",
		"/verifier-engine-room/workers/worker-1/resume",
		"/verifier-engine-room/workers/worker-1/drain",
		"/verifier-engine-room/workers/worker-1/quarantine",
		"/verifier-engine-room/workers/worker-1/unquarantine",
		"/verifier-engine-room/provisioning/servers",
		"/verifier-engine-room/provisioning/servers/1/provision",
		"/verifier-engine-room/provisioning/servers/1/verify",
		"/verifier-engine-room/servers/1/edit",
		"/verifier-engine-room/servers/1/command",
		"/verifier-engine-room/servers/1/delete",
		"/verifier-engine-room/pools",
		"/verifier-engine-room/pools/1",
		"/verifier-engine-room/pools/1/archive",
		"/verifier-engine-room/pools/1/set-default",
		"/verifier-engine-room/pools/default/scale",
		"/verifier-engine-room/settings",
		"/verifier-engine-room/settings/rollback",
		"/verifier-engine-room/settings/provisioning-credentials",
		"/verifier-engine-room/settings/provisioning-credentials/reveal",
		"/verifier-engine-room/providers/gmail/mode",
		"/verifier-engine-room/providers/policies/reload",
		"/verifier-engine-room/policies/validate",
		"/verifier-engine-room/policies/promote",
		"/verifier-engine-room/policies/rollback",
	}

	for _, route := range routes {
		t.Run(route, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodPost, "http://app.local"+route, nil)
			req.Host = "app.local"
			req.SetBasicAuth("dev", token)
			req.Header.Set("Origin", "http://evil.local")

			resp := httptest.NewRecorder()
			router.ServeHTTP(resp, req)

			if resp.Code != http.StatusForbidden {
				t.Fatalf("expected status %d, got %d", http.StatusForbidden, resp.Code)
			}
		})
	}
}

func TestAPIPostRoutesNotBlockedBySameOriginChecks(t *testing.T) {
	token := "secret"
	rdb := redis.NewClient(&redis.Options{
		Addr:         "127.0.0.1:1",
		DialTimeout:  25 * time.Millisecond,
		ReadTimeout:  25 * time.Millisecond,
		WriteTimeout: 25 * time.Millisecond,
		PoolTimeout:  25 * time.Millisecond,
	})
	t.Cleanup(func() {
		_ = rdb.Close()
	})

	store := NewStore(rdb, time.Second)
	s := NewServer(store, nil, Config{ControlPlaneToken: token})
	router := s.Router()

	req := httptest.NewRequest(http.MethodPost, "http://app.local/api/workers/worker-1/pause", nil)
	req.Host = "app.local"
	req.SetBasicAuth("dev", token)

	resp := httptest.NewRecorder()
	router.ServeHTTP(resp, req)

	if resp.Code == http.StatusForbidden {
		t.Fatalf("expected API route not to be blocked by same-origin check, got %d", resp.Code)
	}
	if resp.Code == http.StatusUnauthorized {
		t.Fatalf("expected authenticated API route, got %d", resp.Code)
	}
}
