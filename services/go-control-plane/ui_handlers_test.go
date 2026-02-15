package main

import "testing"

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
