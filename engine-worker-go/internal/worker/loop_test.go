package worker

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
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
		BaseVerifierConfig:           verifier.Config{MailFromAddress: "already@known.local"},
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

func TestSendHeartbeatsSendsLaravelHeartbeatImmediatelyWhenIdentityMissing(t *testing.T) {
	t.Parallel()

	laravelHeartbeatCalls := 0
	laravelServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/verifier/heartbeat" {
			laravelHeartbeatCalls++
			_ = json.NewEncoder(w).Encode(map[string]any{
				"data": map[string]any{
					"identity": map[string]any{
						"helo_name":         "helo.bootstrap.test",
						"mail_from_address": "mailfrom@bootstrap.test",
						"identity_domain":   "bootstrap.test",
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
			DesiredState: "running",
			Commands:     []string{},
		})
	}))
	defer controlPlaneServer.Close()

	worker := New(api.NewClient(laravelServer.URL, ""), Config{
		WorkerID:                     "worker-2",
		Server:                       api.EngineServerPayload{Name: "node-2", IPAddress: "127.0.0.1"},
		BaseVerifierConfig:           verifier.Config{},
		ControlPlaneClient:           api.NewControlPlaneClient(controlPlaneServer.URL, ""),
		ControlPlaneHeartbeatEnabled: true,
		LaravelHeartbeatEnabled:      true,
		LaravelHeartbeatEveryN:       10,
	})

	worker.heartbeatCount = 1
	worker.sendHeartbeats(context.Background())

	if laravelHeartbeatCalls != 1 {
		t.Fatalf("expected immediate laravel heartbeat when identity missing, got %d calls", laravelHeartbeatCalls)
	}

	state := worker.policySnapshot()
	if state.mailFromAddress != "mailfrom@bootstrap.test" {
		t.Fatalf("expected mail-from identity to be populated, got %q", state.mailFromAddress)
	}
	if state.heloName != "helo.bootstrap.test" {
		t.Fatalf("expected helo identity to be populated, got %q", state.heloName)
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

func TestProviderModeForRuntimeDefaultsAndRecognizesExtendedModes(t *testing.T) {
	t.Parallel()

	w := &Worker{}
	if mode := w.providerModeForRuntime("gmail"); mode != "normal" {
		t.Fatalf("expected default provider mode normal, got %q", mode)
	}

	w.policy = policyState{
		providerModes: map[string]string{
			"gmail": "quarantine",
		},
	}

	if mode := w.providerModeForRuntime("gmail"); mode != "quarantine" {
		t.Fatalf("expected provider mode quarantine, got %q", mode)
	}
}

func TestReasonTagFromExtractsTagMetadata(t *testing.T) {
	t.Parallel()

	reason := "smtp_tempfail:decision=retryable;confidence=medium;tag=greylist;provider=gmail"
	if tag := reasonTagFrom(reason); tag != "greylist" {
		t.Fatalf("expected greylist tag, got %q", tag)
	}

	if tag := reasonTagFrom("smtp_tempfail"); tag != "" {
		t.Fatalf("expected empty tag for base reason only, got %q", tag)
	}
}

func TestReasonWithEvidenceIncludesAttemptMetadata(t *testing.T) {
	t.Parallel()

	reason := reasonWithEvidence(verifier.Result{
		Reason:             "smtp_tempfail",
		DecisionClass:      verifier.DecisionRetryable,
		DecisionConfidence: "medium",
		ReasonTag:          "greylist",
		ProviderProfile:    "gmail",
		MXHost:             "mx.gmail.test",
		AttemptNumber:      2,
		AttemptRoute:       "mx:mx.gmail.test",
		EvidenceStrength:   "medium",
		AttemptChain: []verifier.AttemptEvidence{
			{
				AttemptNumber:    1,
				MXHost:           "mx.gmail.test",
				AttemptRoute:     "mx:mx.gmail.test",
				DecisionClass:    verifier.DecisionRetryable,
				ReasonCode:       "smtp_tempfail",
				ReasonTag:        "greylist",
				RetryStrategy:    "greylist",
				SMTPCode:         451,
				EnhancedCode:     "4.7.1",
				ProviderProfile:  "gmail",
				ConfidenceHint:   "medium",
				EvidenceStrength: "medium",
			},
		},
	}, true, true)

	if !strings.Contains(reason, "mx=mx.gmail.test") {
		t.Fatalf("expected reason metadata to include mx host, got %q", reason)
	}
	if !strings.Contains(reason, "attempt=2") {
		t.Fatalf("expected reason metadata to include attempt number, got %q", reason)
	}
	if !strings.Contains(reason, "route=mx:mx.gmail.test") {
		t.Fatalf("expected reason metadata to include attempt route, got %q", reason)
	}
	if !strings.Contains(reason, "evidence=medium") {
		t.Fatalf("expected reason metadata to include evidence strength, got %q", reason)
	}
	if !strings.Contains(reason, "attempt_chain=") {
		t.Fatalf("expected reason metadata to include attempt chain, got %q", reason)
	}

	token := parseReasonMetadataValue(reason, "attempt_chain")
	if token == "" {
		t.Fatalf("expected attempt_chain metadata token")
	}
	decoded, err := base64.RawURLEncoding.DecodeString(token)
	if err != nil {
		t.Fatalf("failed to decode attempt_chain token: %v", err)
	}

	var attempts []verifier.AttemptEvidence
	if err := json.Unmarshal(decoded, &attempts); err != nil {
		t.Fatalf("failed to decode attempt chain json: %v", err)
	}
	if len(attempts) != 1 {
		t.Fatalf("expected one attempt evidence entry, got %d", len(attempts))
	}
	if attempts[0].ReasonCode != "smtp_tempfail" {
		t.Fatalf("expected smtp_tempfail reason code, got %q", attempts[0].ReasonCode)
	}
}

func TestReasonWithEvidenceNormalizesUnknownReasonTags(t *testing.T) {
	t.Parallel()

	reason := reasonWithEvidence(verifier.Result{
		Category:      verifier.CategoryRisky,
		Reason:        "smtp_tempfail",
		DecisionClass: verifier.DecisionRetryable,
		ReasonTag:     "greylist",
	}, true, true)
	if !strings.Contains(reason, "tag=provider_tempfail_unresolved") {
		t.Fatalf("expected normalized provider_tempfail_unresolved tag, got %q", reason)
	}

	identityReason := reasonWithEvidence(verifier.Result{
		Category: verifier.CategoryRisky,
		Reason:   "smtp_probe_identity_missing",
	}, true, true)
	if !strings.Contains(identityReason, "tag=identity_rejected") {
		t.Fatalf("expected normalized identity_rejected tag, got %q", identityReason)
	}

	connectionReason := reasonWithEvidence(verifier.Result{
		Category: verifier.CategoryRisky,
		Reason:   "smtp_connect_timeout",
	}, true, true)
	if !strings.Contains(connectionReason, "tag=connection_unstable") {
		t.Fatalf("expected normalized connection_unstable tag, got %q", connectionReason)
	}
}

func TestReasonWithEvidenceRespectsFeatureFlags(t *testing.T) {
	t.Parallel()

	reason := reasonWithEvidence(verifier.Result{
		Category:      verifier.CategoryRisky,
		Reason:        "smtp_tempfail",
		DecisionClass: verifier.DecisionRetryable,
		ReasonTag:     "greylist",
		AttemptChain: []verifier.AttemptEvidence{
			{AttemptNumber: 1, MXHost: "mx.example.test"},
		},
	}, false, false)

	if strings.Contains(reason, "attempt_chain=") {
		t.Fatalf("expected attempt_chain metadata to be disabled, got %q", reason)
	}
	if !strings.Contains(reason, "tag=greylist") {
		t.Fatalf("expected original reason tag when taxonomy flag is disabled, got %q", reason)
	}
}

func parseReasonMetadataValue(reason, key string) string {
	separator := strings.Index(reason, ":")
	if separator < 0 || separator+1 >= len(reason) {
		return ""
	}

	for _, token := range strings.Split(reason[separator+1:], ";") {
		token = strings.TrimSpace(token)
		prefix := key + "="
		if !strings.HasPrefix(token, prefix) {
			continue
		}

		return strings.TrimSpace(strings.TrimPrefix(token, prefix))
	}

	return ""
}
