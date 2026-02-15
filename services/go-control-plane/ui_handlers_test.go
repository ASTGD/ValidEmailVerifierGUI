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
		Settings:    defaults,
		RuntimeHelp: help,
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
	if !strings.Contains(body, "<strong>If enabled:</strong>") {
		t.Fatalf("expected settings page to render toggle wording")
	}
	if !strings.Contains(body, "<strong>If increased:</strong>") {
		t.Fatalf("expected settings page to render numeric wording")
	}
}
