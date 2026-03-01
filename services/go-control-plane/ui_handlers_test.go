package main

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
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
		Settings:                     defaults,
		DefaultSettings:              defaults,
		RuntimeHelp:                  help,
		ProvisioningCredentials:      &LaravelProvisioningCredentials{GHCRUsername: "astgd", GHCRTokenConfigured: true, GHCRTokenMasked: "******"},
		ProvisioningCredentialsSaved: true,
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
	if !strings.Contains(body, "Provisioning Bundle Credentials") {
		t.Fatalf("expected settings page to include provisioning credentials section")
	}
	if !strings.Contains(body, "provisioning-reveal-token-btn") {
		t.Fatalf("expected settings page to include token reveal button")
	}
	if !strings.Contains(body, "provisioning-stored-token") {
		t.Fatalf("expected settings page to include stored token field")
	}
	if !strings.Contains(body, `name="ghcr_token"`) {
		t.Fatalf("expected settings page to submit ghcr token from stored token field")
	}
	if !strings.Contains(body, `id="cp-confirm-modal"`) {
		t.Fatalf("expected layout to render centered confirm modal container")
	}
}

func TestBuiltCSSContainsHelpTipStyles(t *testing.T) {
	asset, err := os.ReadFile("assets/app.css")
	if err != nil {
		t.Fatalf("failed to read built css asset: %v", err)
	}

	css := string(asset)
	for _, selector := range []string{
		".help-tip__trigger",
		".help-tip__panel",
		".help-tip__risk--high",
		".help-tip__details>summary",
	} {
		if !strings.Contains(css, selector) {
			t.Fatalf("expected css asset to include selector %s", selector)
		}
	}
}

func TestProvisioningTemplateRendersWizardOnly(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ProvisioningPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Provisioning",
			Subtitle:        "Guided server onboarding",
			ActiveNav:       "provisioning",
			ContentTemplate: "provisioning",
			BasePath:        "/verifier-engine-room",
		},
		Mode:                "existing",
		VerificationChecked: true,
		SelectedServer: &LaravelEngineServerRecord{
			ID:                   7,
			Name:                 "engine-7",
			IPAddress:            "10.0.0.7",
			ProcessState:         "running",
			HeartbeatState:       "healthy",
			RuntimeMatchStatus:   "matched",
			LastAgentSeenAt:      "2026-02-18T12:00:00Z",
			ProcessControlMode:   "agent_systemd",
			AgentEnabled:         true,
			AgentServiceName:     "vev-worker.service",
			LastCommandStatus:    "success",
			LastCommandRequestID: "req-42",
		},
		ServerRegistry: EngineServerRegistryPageData{
			Enabled:    true,
			Configured: true,
			Servers: []LaravelEngineServerRecord{
				{
					ID:        7,
					Name:      "engine-7",
					IPAddress: "10.0.0.7",
					Status:    "online",
				},
			},
			VerifierDomains: []LaravelVerifierDomainOption{
				{ID: 1, Domain: "example.org"},
			},
			ProvisionBundleServerID: 7,
			ProvisionBundle: &LaravelProvisioningBundleDetails{
				BundleUUID:             "bundle-uuid",
				EngineServerID:         7,
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
		InstallCommandCopy:   `curl -fsSL "https://app.test/install.sh" | bash -s -- --ghcr-user "<ghcr-username>" --ghcr-token "<ghcr-token>"`,
		InstallCopyUsesSaved: true,
		InstallCopyUsername:  "astgd",
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected provisioning template to contain %q", needle)
		}
	}

	assertContains("Provisioning Wizard")
	assertContains("Step 1: Create Server/Choose Server")
	assertContains("Step 2: Generate Bundle")
	assertContains("Step 3: Install on VPS")
	assertContains("Step 4: Verification Checks")
	assertContains("Register or select a server, generate an engine worker bundle, install on VPS, then verify.")
	assertContains("/verifier-engine-room/provisioning/servers")
	assertContains("/verifier-engine-room/provisioning/servers/7/provision")
	assertContains("/verifier-engine-room/provisioning/servers/7/verify")
	assertContains("Add New Server")
	assertContains("Select Server")
	assertContains("Selecting a server applies Step 2 automatically.")
	assertContains("Run this command on your VPS shell as root.")
	assertContains("Claim-next auth sanity")
	assertContains("provisioning-copy-install-command")
	assertContains("Install command copied")
	assertContains("saved GHCR credentials")
	if strings.Contains(body, "Runtime-only orphan workers") {
		t.Fatalf("expected provisioning template to hide inventory table blocks")
	}
	if strings.Contains(body, ">Continue<") {
		t.Fatalf("expected provisioning template to remove explicit continue button in step 1 existing mode")
	}
	if strings.Contains(body, "/verifier-engine-room/provisioning/servers/7/command") {
		t.Fatalf("expected provisioning template to hide infrastructure command controls")
	}
	if strings.Contains(body, "Server Inventory") {
		t.Fatalf("expected provisioning template heading to be wizard-only")
	}
}

func TestProvisioningTemplateHidesCheckResultsUntilVerificationRuns(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ProvisioningPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Provisioning",
			Subtitle:        "Guided server onboarding",
			ActiveNav:       "provisioning",
			ContentTemplate: "provisioning",
			BasePath:        "/verifier-engine-room",
		},
		Mode: "existing",
		SelectedServer: &LaravelEngineServerRecord{
			ID:                 7,
			Name:               "engine-7",
			IPAddress:          "10.0.0.7",
			ProcessState:       "running",
			HeartbeatState:     "healthy",
			RuntimeMatchStatus: "matched",
		},
		ServerRegistry: EngineServerRegistryPageData{
			Enabled:    true,
			Configured: true,
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, "Run Verification Checks") {
		t.Fatalf("expected verification button when server is selected")
	}
	if !strings.Contains(body, "Run verification after installing the bundle on your VPS.") {
		t.Fatalf("expected pre-check helper copy before verification runs")
	}
	if strings.Contains(body, "Pass · running") {
		t.Fatalf("expected check results to remain hidden until verification runs")
	}
}

func TestProvisioningTemplateCreateModeRendersPoolSelector(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ProvisioningPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Provisioning",
			Subtitle:        "Guided server onboarding",
			ActiveNav:       "provisioning",
			ContentTemplate: "provisioning",
			BasePath:        "/verifier-engine-room",
		},
		Mode: "create",
		ServerRegistry: EngineServerRegistryPageData{
			Enabled:    true,
			Configured: true,
			Pools: []LaravelEnginePoolRecord{
				{
					ID:        1,
					Slug:      "default",
					Name:      "Default Pool",
					IsActive:  true,
					IsDefault: true,
				},
			},
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, "name=\"worker_pool_id\"") {
		t.Fatalf("expected provisioning create mode to render worker pool selector")
	}
	if !strings.Contains(body, "Manage Pools") {
		t.Fatalf("expected provisioning create mode to render manage pools deep link")
	}
}

func TestMapRegistryActionErrorReturnsValidationDetailForUnprocessableEntity(t *testing.T) {
	err := mapRegistryActionError("create server", &LaravelAPIError{
		StatusCode: http.StatusUnprocessableEntity,
		Message:    "A server with this IP address already exists.",
		RequestID:  "req-duplicate",
	})

	expected := "A server with this IP address already exists. (request id: req-duplicate)"
	if err != expected {
		t.Fatalf("expected %q, got %q", expected, err)
	}
}

func TestServersTemplateRendersInventoryListWithManageAction(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ServersPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Servers",
			Subtitle:        "Inventory and operational state",
			ActiveNav:       "servers",
			ContentTemplate: "servers",
			BasePath:        "/verifier-engine-room",
		},
		ServerRegistry: EngineServerRegistryPageData{
			Enabled:    true,
			Configured: true,
			Filter:     "operational",
			Servers: []LaravelEngineServerRecord{
				{
					ID:                   7,
					Name:                 "engine-7",
					IPAddress:            "10.0.0.7",
					ProcessState:         "running",
					HeartbeatState:       "healthy",
					HeartbeatAgeText:     "45s",
					RuntimeMatchStatus:   "matched",
					RuntimeMatchWorkerID: "7",
					UpdatedAtDisplay:     "2026-02-24T09:00:22.000000Z",
					LastCommandStatus:    "success",
					LastCommandRequestID: "req-42",
				},
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
			t.Fatalf("expected servers template to contain %q", needle)
		}
	}

	assertContains("Server Inventory")
	assertContains("Refresh Sync")
	assertContains("Open Provisioning Wizard")
	assertContains("List and manage state only")
	assertContains("Filter:")
	assertContains("/verifier-engine-room/servers?registry_filter=operational")
	assertContains("/verifier-engine-room/servers?registry_filter=needs_attention")
	assertContains("/verifier-engine-room/servers/7")
	assertContains("matched")
	assertContains("Runtime-only workers detected")
	assertContains("req-42")
	if strings.Contains(body, "method=\"POST\" action=\"/verifier-engine-room/servers\"") {
		t.Fatalf("expected servers template to avoid inline create-server form")
	}
	if strings.Contains(body, "Step 1: Select Server") {
		t.Fatalf("expected servers template to avoid wizard-only step sections")
	}
	if strings.Contains(body, "Generate Bundle") {
		t.Fatalf("expected servers list to avoid provisioning actions")
	}
	if strings.Contains(body, "Delete Server") {
		t.Fatalf("expected servers list to avoid direct delete control")
	}
}

func TestServerManageTemplateRendersInfrastructureControls(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ServerManagePageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Server · engine-7",
			Subtitle:        "Server diagnostics and infrastructure control",
			ActiveNav:       "servers",
			ContentTemplate: "server_manage",
			BasePath:        "/verifier-engine-room",
		},
		Server: &LaravelEngineServerRecord{
			ID:                   7,
			Name:                 "engine-7",
			IPAddress:            "10.0.0.7",
			ProcessState:         "running",
			HeartbeatState:       "healthy",
			HeartbeatAgeText:     "44s",
			RuntimeMatchStatus:   "matched",
			RuntimeMatchWorkerID: "7",
			RuntimeMatchDetail:   "Matched runtime worker 7",
			ProcessControlMode:   "agent_systemd",
			AgentEnabled:         true,
			LastCommandStatus:    "success",
			LastCommandRequestID: "req-55",
		},
		DeleteLocked: true,
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected server manage template to contain %q", needle)
		}
	}

	assertContains("Manage Server")
	assertContains("Infrastructure Control")
	assertContains("/verifier-engine-room/servers/7/command")
	assertContains("go-servers-manage-status")
	assertContains("Delete Guard")
	assertContains("Blocked until process is stopped")
	assertContains("/verifier-engine-room/servers/7/delete")
	assertContains("/verifier-engine-room/servers/7/edit")
	assertContains("/verifier-engine-room/provisioning?mode=existing&server_id=7")
	assertContains(`data-confirm-message="Delete server engine-7 from inventory? This does not terminate the VPS itself."`)
}

func TestServerEditTemplateRendersDedicatedEditForm(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, ServerEditPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Edit Server · engine-7",
			Subtitle:        "Edit server metadata",
			ActiveNav:       "servers",
			ContentTemplate: "server_edit",
			BasePath:        "/verifier-engine-room",
		},
		Server: &LaravelEngineServerRecord{
			ID:                  7,
			Name:                "engine-7",
			IPAddress:           "10.0.0.7",
			ProcessControlMode:  "agent_systemd",
			AgentEnabled:        true,
			AgentVerifyTLS:      true,
			AgentTimeoutSeconds: 8,
		},
		VerifierDomains: []LaravelVerifierDomainOption{
			{ID: 1, Domain: "example.org"},
		},
		Pools: []LaravelEnginePoolRecord{
			{
				ID:        1,
				Slug:      "default",
				Name:      "Default Pool",
				IsActive:  true,
				IsDefault: true,
			},
		},
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected server edit template to contain %q", needle)
		}
	}

	assertContains("Edit Server")
	assertContains("Save Server")
	assertContains("name=\"process_control_mode\"")
	assertContains("name=\"agent_base_url\"")
	assertContains("name=\"worker_pool_id\"")
	assertContains("View pool policy")
	assertContains("/verifier-engine-room/servers/7/edit")
	assertContains("return_to\" value=\"server_edit\"")
	if strings.Contains(body, "Run Verification Checks") {
		t.Fatalf("expected server edit page to avoid provisioning controls")
	}
}

func TestPoolsTemplateRendersCrudAndRuntimeSummary(t *testing.T) {
	renderer, err := NewViewRenderer()
	if err != nil {
		t.Fatalf("failed to create renderer: %v", err)
	}

	recorder := httptest.NewRecorder()
	renderer.Render(recorder, PoolsPageData{
		BasePageData: BasePageData{
			Title:           "Verifier Engine Room · Pools",
			Subtitle:        "Server-group policy and capacity control",
			ActiveNav:       "pools",
			ContentTemplate: "pools",
			BasePath:        "/verifier-engine-room",
		},
		Configured: true,
		Rows: []PoolInventoryRow{
			{
				Pool: LaravelEnginePoolRecord{
					ID:        1,
					Slug:      "default",
					Name:      "Default Pool",
					IsActive:  true,
					IsDefault: true,
					ProviderProfiles: map[string]string{
						"generic":   "standard",
						"gmail":     "standard",
						"microsoft": "standard",
						"yahoo":     "standard",
					},
				},
				RuntimeOnline:      2,
				RuntimeDesired:     5,
				RuntimeHealthScore: 0.92,
			},
		},
		ProfileOptions: []PoolProfileOption{
			{Key: "standard", Label: "Standard"},
			{Key: "low_hit", Label: "Low Hit"},
			{Key: "warmup", Label: "Warmup"},
		},
	})

	body := recorder.Body.String()
	assertContains := func(needle string) {
		if !strings.Contains(body, needle) {
			t.Fatalf("expected pools template to contain %q", needle)
		}
	}

	assertContains("Create Pool")
	assertContains("Edit Pool")
	assertContains("Default Pool")
	assertContains("Policy Summary")
	assertContains("/verifier-engine-room/pools/default/scale")
	assertContains("desired")
}

func TestWorkersRuntimeTemplateRendersDecisionTraceExplorer(t *testing.T) {
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
			ContentTemplate: "workers_runtime",
			BasePath:        "/verifier-engine-room",
		},
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
		WorkerServerLinks: map[string]WorkerServerLink{
			"worker-1": {
				Matched:   true,
				ManageURL: "/verifier-engine-room/servers/9",
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
	assertContains("/verifier-engine-room/workers/worker-1/pause")
	assertContains("/verifier-engine-room/workers/worker-1/drain")
	assertContains("/verifier-engine-room/workers/worker-1/quarantine")
	assertContains("/verifier-engine-room/servers/9")
	if strings.Contains(body, "/verifier-engine-room/workers/worker-1/stop") {
		t.Fatalf("expected workers runtime template to hide stop action")
	}
	if strings.Contains(body, "/verifier-engine-room/workers/worker-1/resume") {
		t.Fatalf("expected running worker to render only Pause action")
	}
}

func TestWorkersRuntimeTemplateRendersRuntimeControlsOnly(t *testing.T) {
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
			ContentTemplate: "workers_runtime",
			BasePath:        "/verifier-engine-room",
		},
		Workers: []WorkerSummary{
			{
				WorkerID:      "worker-running",
				Host:          "host-1",
				Pool:          "pool-a",
				Status:        "online",
				DesiredState:  "running",
				LastHeartbeat: "2026-02-18T12:00:00Z",
			},
		},
		WorkerServerLinks: map[string]WorkerServerLink{
			"worker-running": {
				Matched:     false,
				RegisterURL: "/verifier-engine-room/provisioning?mode=create&worker_id=worker-running",
			},
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, "Pause/Resume, Drain, and Quarantine control scheduler intent") {
		t.Fatalf("expected workers runtime microcopy to be rendered")
	}
	if strings.Contains(body, "Server Registry &amp; Provisioning") {
		t.Fatalf("expected workers runtime page to hide registry tab")
	}
	if strings.Contains(body, "/verifier-engine-room/workers/worker-running/stop") {
		t.Fatalf("expected workers runtime page to hide stop action")
	}
	if !strings.Contains(body, "/verifier-engine-room/workers/worker-running/pause") {
		t.Fatalf("expected pause action for running worker")
	}
	if !strings.Contains(body, "/verifier-engine-room/provisioning?mode=create") || !strings.Contains(body, "worker-running") {
		t.Fatalf("expected register server link for unmatched runtime worker")
	}
}

func TestWorkersRuntimeTemplateRendersPausedWorkerWithResumeAction(t *testing.T) {
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
			ContentTemplate: "workers_runtime",
			BasePath:        "/verifier-engine-room",
		},
		Workers: []WorkerSummary{
			{
				WorkerID:      "worker-paused",
				Host:          "host-1",
				Pool:          "pool-a",
				Status:        "online",
				DesiredState:  "paused",
				LastHeartbeat: "2026-02-18T12:00:00Z",
			},
		},
	})

	body := recorder.Body.String()
	if !strings.Contains(body, "/verifier-engine-room/workers/worker-paused/resume") {
		t.Fatalf("expected paused worker to expose Resume action")
	}
	if strings.Contains(body, "/verifier-engine-room/workers/worker-paused/pause") {
		t.Fatalf("expected paused worker to hide Pause action")
	}
	if strings.Contains(body, "/verifier-engine-room/workers/worker-paused/stop") {
		t.Fatalf("expected paused worker to hide Stop action")
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
	if matched[0].ProcessState != "running" {
		t.Fatalf("expected matched server process state fallback to running, got %q", matched[0].ProcessState)
	}
	if matched[1].ProcessState != "stopped" {
		t.Fatalf("expected offline server process state fallback to stopped, got %q", matched[1].ProcessState)
	}
	if matched[1].HeartbeatState != "none" {
		t.Fatalf("expected heartbeat state fallback to none without heartbeat, got %q", matched[1].HeartbeatState)
	}
}

func TestHostsLikelyMatchSupportsShortAndFQDNHosts(t *testing.T) {
	if !hostsLikelyMatch("eng2.vasevev.com", "eng2") {
		t.Fatal("expected short worker host to match FQDN server name")
	}
	if !hostsLikelyMatch("eng2", "eng2.vasevev.com") {
		t.Fatal("expected FQDN worker host to match short server name")
	}
	if hostsLikelyMatch("eng2.vasevev.com", "eng9.vasevev.com") {
		t.Fatal("expected different hosts not to match")
	}
}

func TestRunProvisioningClaimNextAuthProbePassesOnValidationOnlyResponse(t *testing.T) {
	var testServerURL string
	testServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/internal/engine-servers/7/provisioning-bundles/latest":
			if got := r.Header.Get("Authorization"); got != "Bearer internal-token" {
				t.Fatalf("expected internal auth header, got %q", got)
			}
			w.Header().Set("Content-Type", "application/json")
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"bundle_uuid":      "bundle-7",
					"engine_server_id": 7,
					"download_urls": map[string]string{
						"env": testServerURL + "/bundle/env",
					},
				},
			})
		case "/bundle/env":
			w.Header().Set("Content-Type", "text/plain")
			_, _ = w.Write([]byte("ENGINE_API_BASE_URL=" + testServerURL + "\nENGINE_API_TOKEN=probe-token\n"))
		case "/api/verifier/chunks/claim-next":
			if got := r.Header.Get("Authorization"); got != "Bearer probe-token" {
				t.Fatalf("expected probe token auth header, got %q", got)
			}
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusUnprocessableEntity)
			_, _ = w.Write([]byte(`{"message":"The engine server field is required."}`))
		default:
			t.Fatalf("unexpected path %q", r.URL.Path)
		}
	}))
	defer testServer.Close()
	testServerURL = testServer.URL

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL: testServerURL,
		LaravelInternalAPIToken:   "internal-token",
	})
	if client == nil {
		t.Fatal("expected configured internal client")
	}

	server := &Server{
		cfg: Config{
			LaravelInternalAPITimeoutSeconds: 2,
		},
		laravelEngineClient: client,
	}

	status, detail := server.runProvisioningClaimNextAuthProbe(context.Background(), 7)
	if status != "pass" {
		t.Fatalf("expected pass status, got %q (%s)", status, detail)
	}
	if !strings.Contains(detail, "validation-only probe") {
		t.Fatalf("expected validation-only pass detail, got %q", detail)
	}
}

func TestRunProvisioningClaimNextAuthProbeFailsOnUnauthorizedToken(t *testing.T) {
	var testServerURL string
	testServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/internal/engine-servers/9/provisioning-bundles/latest":
			w.Header().Set("Content-Type", "application/json")
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"bundle_uuid":      "bundle-9",
					"engine_server_id": 9,
					"download_urls": map[string]string{
						"env": testServerURL + "/bundle/env",
					},
				},
			})
		case "/bundle/env":
			w.Header().Set("Content-Type", "text/plain")
			_, _ = w.Write([]byte("ENGINE_API_BASE_URL=" + testServerURL + "\nENGINE_API_TOKEN=bad-token\n"))
		case "/api/verifier/chunks/claim-next":
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusUnauthorized)
			_, _ = w.Write([]byte(`{"message":"Unauthenticated."}`))
		default:
			t.Fatalf("unexpected path %q", r.URL.Path)
		}
	}))
	defer testServer.Close()
	testServerURL = testServer.URL

	client := NewLaravelEngineServerClient(Config{
		LaravelInternalAPIBaseURL: testServerURL,
		LaravelInternalAPIToken:   "internal-token",
	})
	if client == nil {
		t.Fatal("expected configured internal client")
	}

	server := &Server{
		cfg: Config{
			LaravelInternalAPITimeoutSeconds: 2,
		},
		laravelEngineClient: client,
	}

	status, detail := server.runProvisioningClaimNextAuthProbe(context.Background(), 9)
	if status != "fail" {
		t.Fatalf("expected fail status, got %q (%s)", status, detail)
	}
	if !strings.Contains(detail, "Unauthenticated") {
		t.Fatalf("expected unauthorized detail, got %q", detail)
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
