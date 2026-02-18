package main

import (
	"net/http/httptest"
	"strings"
	"testing"
)

func TestDocsURLUsesExplicitOverrideWhenSet(t *testing.T) {
	server := &Server{
		cfg: Config{
			OpsDocsURL:        "https://docs.example.test/internal/docs",
			LaravelAPIBaseURL: "https://app.example.test",
		},
	}

	if got := server.docsURL(); got != "https://docs.example.test/internal/docs" {
		t.Fatalf("expected override docs URL, got %q", got)
	}
}

func TestDocsURLFallsBackToLaravelBaseURL(t *testing.T) {
	server := &Server{
		cfg: Config{
			LaravelAPIBaseURL: "https://app.example.test/",
		},
	}

	if got := server.docsURL(); got != "https://app.example.test/internal/docs" {
		t.Fatalf("expected fallback docs URL, got %q", got)
	}
}

func TestDocsURLReturnsEmptyWhenUnavailable(t *testing.T) {
	server := &Server{
		cfg: Config{},
	}

	if got := server.docsURL(); got != "" {
		t.Fatalf("expected empty docs URL, got %q", got)
	}
}

func TestSettingsTemplateRendersRuntimeHelpKeys(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	defaults := defaultRuntimeSettings(Config{})
	help := buildRuntimeSettingsHelp(defaults, "http://localhost:8082/internal/docs")

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, SettingsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Settings",
			Subtitle:        "Runtime controls",
			ActiveNav:       "settings",
			ContentTemplate: "settings",
			BasePath:        "/verifier-engine-room",
			DocsURL:         "http://localhost:8082/internal/docs",
		},
		Settings:        defaults,
		DefaultSettings: defaults,
		RuntimeHelp:     help,
	})

	body := recorder.Body.String()
	for _, key := range []string{
		"alert_error_rate_threshold",
		"autoscale_enabled",
		"policy_canary_window_minutes",
	} {
		if !strings.Contains(body, `data-help-key="`+key+`"`) {
			t.Fatalf("expected settings page to include help key %s", key)
		}
	}
	if !strings.Contains(body, "Recommended") {
		t.Fatalf("expected settings page to render recommended pill text")
	}
	if !strings.Contains(body, "Safe:") {
		t.Fatalf("expected settings page to render safe range hint text")
	}
	if !strings.Contains(body, `data-help-risk-key="alert_error_rate_threshold"`) {
		t.Fatalf("expected settings page to render dynamic risk placeholder")
	}
	if !strings.Contains(body, "data-caution-min") || !strings.Contains(body, "data-caution-max") {
		t.Fatalf("expected settings page to render caution range metadata")
	}
	if !strings.Contains(body, "settings-risk-warning") {
		t.Fatalf("expected settings page to render risk warning container")
	}
	if !strings.Contains(body, "settings-risk-warning__action") {
		t.Fatalf("expected settings page script to include set recommended action hook")
	}
	if !strings.Contains(body, "<strong>If enabled:</strong>") {
		t.Fatalf("expected settings page to render toggle wording")
	}
	if !strings.Contains(body, "<strong>If increased:</strong>") {
		t.Fatalf("expected settings page to render numeric wording")
	}
	if !strings.Contains(body, "data-settings-preset=\"balanced\"") {
		t.Fatalf("expected settings page to include preset controls")
	}
	if !strings.Contains(body, "settings-change-preview") {
		t.Fatalf("expected settings page to include change preview container")
	}
	if !strings.Contains(body, "runtime-settings-save-button") {
		t.Fatalf("expected settings page to include save button id for risk guard")
	}
	if !strings.Contains(body, "runtime-settings-set-all-recommended") {
		t.Fatalf("expected settings page to include set all to recommended button")
	}
}

func TestWorkersTemplateRendersRegistryTabAndForms(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
		},
		ActiveTab: "registry",
		ServerRegistry: EngineServerRegistryPageData{
			Enabled:    true,
			Configured: true,
			Filter:     "all",
			Servers: []LaravelEngineServerRecord{
				{
					ID:                   7,
					Name:                 "engine-7",
					IPAddress:            "10.0.0.7",
					Status:               "online",
					RuntimeMatchStatus:   "matched",
					RuntimeMatchWorkerID: "7",
				},
			},
			VerifierDomains: []LaravelVerifierDomainOption{
				{ID: 1, Domain: "example.org"},
			},
			EditServerID: 7,
			EditServer: &LaravelEngineServerRecord{
				ID:                     7,
				Name:                   "engine-7",
				IPAddress:              "10.0.0.7",
				VerifierDomainID:       1,
				MaxConcurrency:         10,
				Status:                 "online",
				LatestProvisioningInfo: nil,
			},
			ProvisionBundleServerID: 7,
			ProvisionBundle: &LaravelProvisioningBundleDetails{
				BundleUUID:     "bundle-uuid",
				EngineServerID: 7,
				DownloadURLs: map[string]string{
					"install": "https://app.test/install.sh",
					"env":     "https://app.test/worker.env",
				},
				InstallCommandTemplate: "curl -fsSL \"https://app.test/install.sh\" | bash -s -- --ghcr-user \"<ghcr-username>\" --ghcr-token \"<ghcr-token>\"",
			},
			OrphanWorkers: []WorkerSummary{
				{
					WorkerID:  "orphan-1",
					Host:      "orphan-host",
					IPAddress: "10.9.0.1",
				},
			},
		},
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected workers template to contain %q", needle)
		}
	}

	assertContains("Runtime Workers")
	assertContains("Server Registry &amp; Provisioning")
	assertContains("/verifier-engine-room/workers/servers")
	assertContains("/verifier-engine-room/workers/servers/7/provision")
	assertContains("Latest Provisioning Bundle")
	assertContains("Create Server")
	assertContains("Save Changes")
	assertContains("Runtime Match")
	assertContains("Runtime-only orphan workers")
	assertContains("registry_filter=matched")
}

func TestWorkersTemplateRendersDecisionTraceExplorer(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
		},
		ActiveTab: "runtime",
		Workers: []WorkerSummary{
			{
				WorkerID:      "worker-1",
				Host:          "host-1",
				Pool:          "pool-a",
				Status:        "online",
				DesiredState:  "running",
				LastHeartbeat: "2026-02-18T12:00:00Z",
			},
		},
		DecisionTraces: WorkerDecisionTracePageData{
			Enabled: true,
			Filter: LaravelDecisionTraceFilter{
				Provider:      "gmail",
				DecisionClass: "unknown",
				ReasonTag:     "provider_tempfail_unresolved",
				PolicyVersion: "v4.2.0",
				Limit:         20,
			},
			Records: []LaravelDecisionTraceRecord{
				{
					ID:            1,
					Provider:      "gmail",
					DecisionClass: "unknown",
					ReasonTag:     "provider_tempfail_unresolved",
					PolicyVersion: "v4.2.0",
					MatchedRuleID: "rule-1",
					SMTPCode:      "451",
					EnhancedCode:  "4.7.1",
					AttemptChain:  []map[string]interface{}{{"attempt_number": 1}},
					ObservedAt:    "2026-02-18T12:00:00Z",
				},
			},
			NextBeforeID: 10,
		},
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected workers runtime template to contain %q", needle)
		}
	}

	assertContains("Decision Trace Explorer")
	assertContains("trace_provider")
	assertContains("provider_tempfail_unresolved")
	assertContains("Load More")
	assertContains("retryable")
}

func TestWorkersTemplateRendersStoppedWorkerAsRedWithStartAction(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
		},
		ActiveTab: "runtime",
		Workers: []WorkerSummary{
			{
				WorkerID:      "worker-stopped",
				Host:          "host-1",
				Pool:          "pool-a",
				Status:        "stopped",
				DesiredState:  "stopped",
				LastHeartbeat: "2026-02-18T12:00:00Z",
			},
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, "bg-red-500/20 text-red-300") {
		t.Fatalf("expected stopped worker status to render in red")
	}
	if !strings.Contains(body, "/verifier-engine-room/workers/worker-stopped/resume") {
		t.Fatalf("expected stopped worker to expose resume endpoint")
	}
	if !strings.Contains(body, ">Start</button>") {
		t.Fatalf("expected stopped worker action to render Start button")
	}
}

func TestWorkersTemplateRendersStartingStateWithoutStartAction(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, WorkersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Workers",
			Subtitle:        "Live worker status",
			ActiveNav:       "workers",
			ContentTemplate: "workers",
			BasePath:        "/verifier-engine-room",
		},
		ActiveTab: "runtime",
		Workers: []WorkerSummary{
			{
				WorkerID:      "worker-starting",
				Host:          "host-1",
				Pool:          "pool-a",
				Status:        "stopped",
				DesiredState:  "running",
				LastHeartbeat: "2026-02-18T12:00:00Z",
			},
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, ">starting<") {
		t.Fatalf("expected transition state label to render as starting")
	}
	if strings.Contains(body, "/verifier-engine-room/workers/worker-starting/resume") && strings.Contains(body, ">Start</button>") {
		t.Fatalf("expected no Start action while desired state is running")
	}
}

func TestApplyRegistryRuntimeMatchMarksMatchedAndOrphanWorkers(t *testing.T) {
	servers := []LaravelEngineServerRecord{
		{ID: 1, Name: "srv-1", IPAddress: "10.0.0.1", Status: "online"},
		{ID: 2, Name: "srv-2", IPAddress: "10.0.0.2", Status: "offline"},
	}
	workers := []WorkerSummary{
		{WorkerID: "1", Host: "srv-1", IPAddress: "10.0.0.1"},
		{WorkerID: "orphan-1", Host: "unknown-host", IPAddress: "10.0.0.99"},
	}

	matched, orphans := applyRegistryRuntimeMatch(servers, workers)
	if len(matched) != 2 {
		t.Fatalf("expected 2 servers after match processing, got %d", len(matched))
	}
	if matched[0].RuntimeMatchStatus != "matched" {
		t.Fatalf("expected first server to be matched, got %q", matched[0].RuntimeMatchStatus)
	}
	if matched[1].RuntimeMatchStatus != "no_runtime_heartbeat" {
		t.Fatalf("expected second server to be mismatch, got %q", matched[1].RuntimeMatchStatus)
	}
	if len(orphans) != 1 || orphans[0].WorkerID != "orphan-1" {
		t.Fatalf("expected orphan worker orphan-1, got %#v", orphans)
	}
}

func TestMapRegistryActionErrorUsesStatusSpecificMessages(t *testing.T) {
	tests := []struct {
		name   string
		err    error
		expect string
	}{
		{
			name: "unauthorized",
			err: &LaravelAPIError{
				StatusCode: 401,
				Message:    "Unauthorized.",
				RequestID:  "req-401",
			},
			expect: "authentication failed",
		},
		{
			name: "validation",
			err: &LaravelAPIError{
				StatusCode: 422,
				Message:    "Validation failed.",
				RequestID:  "req-422",
			},
			expect: "Validation failed",
		},
		{
			name: "rate_limited",
			err: &LaravelAPIError{
				StatusCode: 429,
				Message:    "Too many requests.",
				RequestID:  "req-429",
			},
			expect: "rate-limited",
		},
		{
			name: "server_error",
			err: &LaravelAPIError{
				StatusCode: 500,
				Message:    "Server error.",
				RequestID:  "req-500",
			},
			expect: "temporarily unavailable",
		},
	}

	for _, testCase := range tests {
		message := mapRegistryActionError("load server registry", testCase.err)
		if !strings.Contains(message, testCase.expect) {
			t.Fatalf("%s: expected %q in %q", testCase.name, testCase.expect, message)
		}
		if !strings.Contains(message, "request id:") {
			t.Fatalf("%s: expected request id in %q", testCase.name, message)
		}
	}
}
