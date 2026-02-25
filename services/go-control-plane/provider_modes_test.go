package main

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestDefaultProviderModeSemanticsIncludesExtendedModes(t *testing.T) {
	semantics := defaultProviderModeSemantics()

	required := []string{"normal", "cautious", "drain", "quarantine", "degraded_probe"}
	for _, key := range required {
		if _, ok := semantics[key]; !ok {
			t.Fatalf("expected mode semantics to include %s", key)
		}
	}

	if semantics["quarantine"].ProbeEnabled {
		t.Fatal("expected quarantine mode to disable probe")
	}
	if !semantics["degraded_probe"].ProbeEnabled {
		t.Fatal("expected degraded_probe mode to allow probe")
	}
}

func TestProviderModeFromPoolProfile(t *testing.T) {
	tests := map[string]string{
		"standard": "normal",
		"low_hit":  "cautious",
		"warmup":   "degraded_probe",
		"unknown":  "normal",
	}

	for profile, expected := range tests {
		if got := providerModeFromPoolProfile(profile); got != expected {
			t.Fatalf("expected profile %s to map to %s, got %s", profile, expected, got)
		}
	}
}

func TestApplyPoolProviderModesRespectsManualModeAndAppliesProfileOverrides(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/internal/engine-pools" {
			t.Fatalf("unexpected path: %s", r.URL.Path)
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": []map[string]any{
				{
					"id":        10,
					"slug":      "gmail-lowhit",
					"name":      "Gmail Low Hit",
					"is_active": true,
					"provider_profiles": map[string]string{
						"generic":   "standard",
						"gmail":     "low_hit",
						"microsoft": "warmup",
						"yahoo":     "standard",
					},
				},
			},
		})
	}))
	defer server.Close()

	controlPlane := &Server{
		laravelEngineClient: NewLaravelEngineServerClient(Config{
			LaravelInternalAPIBaseURL: server.URL,
			LaravelInternalAPIToken:   "token",
		}),
	}

	modes := []ProviderModeState{
		{Provider: "gmail", Mode: "quarantine", Source: "manual"},
		{Provider: "microsoft", Mode: "normal", Source: "auto"},
	}

	updated := controlPlane.applyPoolProviderModes(context.Background(), modes, "gmail-lowhit")
	if len(updated) < 2 {
		t.Fatalf("expected at least two provider modes, got %d", len(updated))
	}

	byProvider := map[string]ProviderModeState{}
	for _, mode := range updated {
		byProvider[mode.Provider] = mode
	}

	if byProvider["gmail"].Mode != "quarantine" {
		t.Fatalf("expected gmail manual mode to stay quarantine, got %s", byProvider["gmail"].Mode)
	}
	if byProvider["gmail"].Source != "manual" {
		t.Fatalf("expected gmail source manual, got %s", byProvider["gmail"].Source)
	}
	if byProvider["microsoft"].Mode != "degraded_probe" {
		t.Fatalf("expected microsoft mode degraded_probe from warmup profile, got %s", byProvider["microsoft"].Mode)
	}
	if byProvider["microsoft"].Source != "pool_profile" {
		t.Fatalf("expected microsoft source pool_profile, got %s", byProvider["microsoft"].Source)
	}
}
