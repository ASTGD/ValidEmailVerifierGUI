package worker

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"engine-worker-go/internal/api"
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

func TestSendHeartbeatsUsesControlPlaneAndLaravelFallbackFrequency(t *testing.T) {
	t.Parallel()

	laravelHeartbeatCalls := 0
	laravelServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/verifier/heartbeat" {
			laravelHeartbeatCalls++
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"identity": map[string]any{
						"helo_name":         "helo.test",
						"mail_from_address": "mail@test.local",
						"identity_domain":   "test.local",
					},
				},
			})
			return
		}

		http.NotFound(w, r)
	}))
	defer laravelServer.Close()

	controlPlaneServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/workers/heartbeat" {
			http.NotFound(w, r)
			return
		}

		_ = json.NewEncoder(w).Encode(api.ControlPlaneHeartbeatResponse{
			DesiredState: "paused",
			Commands:     []string{"pause"},
		})
	}))
	defer controlPlaneServer.Close()

	w := New(api.NewClient(laravelServer.URL, ""), Config{
		WorkerID:                     "worker-1",
		Server:                       api.EngineServerPayload{Name: "node-1", IPAddress: "127.0.0.1"},
		BaseVerifierConfig:           verifier.Config{},
		ControlPlaneClient:           api.NewControlPlaneClient(controlPlaneServer.URL, ""),
		ControlPlaneHeartbeatEnabled: true,
		LaravelHeartbeatEnabled:      true,
		LaravelHeartbeatEveryN:       2,
	})

	w.heartbeatCount = 1
	w.sendHeartbeats(context.Background())
	if laravelHeartbeatCalls != 0 {
		t.Fatalf("expected laravel heartbeat to be skipped at count 1")
	}
	if w.currentDesiredState() != "paused" {
		t.Fatalf("expected desired state paused after control-plane heartbeat")
	}

	w.heartbeatCount = 2
	w.sendHeartbeats(context.Background())
	if laravelHeartbeatCalls != 1 {
		t.Fatalf("expected laravel heartbeat to run once at count 2, got %d", laravelHeartbeatCalls)
	}
}

func TestResolvePolicyRuntimeUsesActiveVersionPayload(t *testing.T) {
	t.Parallel()

	const policyPayload = `{
		"enabled": true,
		"version": "v2.9.0",
		"profiles": {
			"generic": {
				"name": "generic",
				"enhanced_rules": [
					{
						"rule_id": "test-rule",
						"enhanced_prefixes": ["4.7."],
						"decision_class": "retryable",
						"category": "risky",
						"reason": "smtp_tempfail",
						"reason_code": "smtp_tempfail"
					}
				],
				"smtp_code_rules": [],
				"message_rules": [],
				"retry": {
					"default_seconds": 60,
					"tempfail_seconds": 90,
					"greylist_seconds": 120,
					"policy_blocked_seconds": 300,
					"unknown_seconds": 90
				}
			}
		}
	}`

	laravelServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/verifier/policy-versions/v2.9.0/payload" {
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"version":        "v2.9.0",
					"policy_payload": json.RawMessage(policyPayload),
				},
			})
			return
		}

		http.NotFound(w, r)
	}))
	defer laravelServer.Close()

	controlPlaneServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/providers/policies" {
			http.NotFound(w, r)
			return
		}

		_ = json.NewEncoder(w).Encode(map[string]any{
			"data": map[string]any{
				"policy_engine_enabled":  true,
				"adaptive_retry_enabled": true,
				"active_version":         "v2.9.0",
			},
		})
	}))
	defer controlPlaneServer.Close()

	w := New(api.NewClient(laravelServer.URL, ""), Config{
		BaseVerifierConfig: verifier.Config{
			ProviderPolicyEngineEnabled: false,
			AdaptiveRetryEnabled:        false,
		},
		ControlPlaneClient:            api.NewControlPlaneClient(controlPlaneServer.URL, ""),
		ControlPlanePolicySyncEnabled: true,
	})

	runtime := w.resolvePolicyRuntime(context.Background(), policyState{})
	if runtime.activeVersion != "v2.9.0" {
		t.Fatalf("expected active version v2.9.0, got %s", runtime.activeVersion)
	}
	if !runtime.policyEngineEnabled {
		t.Fatalf("expected policy engine enabled")
	}
	if runtime.replyPolicyEngine == nil {
		t.Fatalf("expected reply policy engine to be loaded")
	}
	if runtime.replyPolicyEngine.Version != "v2.9.0" {
		t.Fatalf("expected runtime policy engine version v2.9.0, got %s", runtime.replyPolicyEngine.Version)
	}
}
