package worker

import (
	"context"
	"testing"

	"engine-worker-go/internal/verifier"
)

func TestNormalizeWorkerCapability(t *testing.T) {
	t.Parallel()

	tests := map[string]string{
		"":           "all",
		"SCREENING":  "screening",
		"smtp_probe": "smtp_probe",
		"all":        "all",
		"unknown":    "all",
	}

	for input, expected := range tests {
		if got := normalizeWorkerCapability(input); got != expected {
			t.Fatalf("normalizeWorkerCapability(%q) = %q, expected %q", input, got, expected)
		}
	}
}

func TestWorkerCapabilityStageMatch(t *testing.T) {
	t.Parallel()

	screeningWorker := &Worker{cfg: Config{WorkerCapability: "screening"}}
	if !screeningWorker.canProcessStage("screening") {
		t.Fatalf("screening worker should process screening stage")
	}
	if screeningWorker.canProcessStage("smtp_probe") {
		t.Fatalf("screening worker should not process smtp_probe stage")
	}

	probeWorker := &Worker{cfg: Config{WorkerCapability: "smtp_probe"}}
	if !probeWorker.canProcessStage("smtp_probe") {
		t.Fatalf("smtp_probe worker should process smtp_probe stage")
	}
	if probeWorker.canProcessStage("screening") {
		t.Fatalf("smtp_probe worker should not process screening stage")
	}
}

func TestModeForStage(t *testing.T) {
	t.Parallel()

	if got := modeForStage("screening", "enhanced"); got != "standard" {
		t.Fatalf("screening stage should always run standard mode, got %q", got)
	}

	if got := modeForStage("smtp_probe", "standard"); got != "enhanced" {
		t.Fatalf("smtp_probe stage should always run enhanced mode, got %q", got)
	}
}

func TestStaticRiskyVerifier(t *testing.T) {
	t.Parallel()

	v := staticRiskyVerifier{reason: "smtp_probe_disabled"}
	result := v.Verify(context.Background(), "user@example.com")

	if result.Category != verifier.CategoryRisky {
		t.Fatalf("expected risky category, got %q", result.Category)
	}
	if result.Reason != "smtp_probe_disabled" {
		t.Fatalf("expected reason smtp_probe_disabled, got %q", result.Reason)
	}
}
