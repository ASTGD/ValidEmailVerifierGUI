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
