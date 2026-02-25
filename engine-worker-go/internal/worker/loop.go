package worker

import (
	"bufio"
	"bytes"
	"context"
	"encoding/base64"
	"encoding/csv"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"engine-worker-go/internal/api"
	"engine-worker-go/internal/verifier"
)

type Config struct {
	PollInterval                  time.Duration
	HeartbeatInterval             time.Duration
	LeaseSeconds                  *int
	MaxConcurrency                int
	PolicyRefresh                 time.Duration
	Server                        api.EngineServerPayload
	WorkerID                      string
	WorkerCapability              string
	BaseVerifierConfig            verifier.Config
	ControlPlaneClient            *api.ControlPlaneClient
	ControlPlaneHeartbeatEnabled  bool
	LaravelHeartbeatEnabled       bool
	LaravelHeartbeatEveryN        int
	ControlPlanePolicySyncEnabled bool
	ProbeAttemptChainEnabled      bool
	UnknownReasonTaxonomyEnabled  bool
}

type Worker struct {
	client          *api.Client
	cfg             Config
	wg              sync.WaitGroup
	active          int64
	maxConcurrency  int64
	heartbeatCount  int64
	policyMu        sync.RWMutex
	policy          policyState
	lastPolicyFetch time.Time
	desiredState    atomic.Value
	telemetry       *workerTelemetry
}

type policyState struct {
	loaded               bool
	enginePaused         bool
	enhancedModeEnabled  bool
	roleAccountsBehavior string
	roleAccounts         map[string]struct{}
	heloName             string
	mailFromAddress      string
	identityDomain       string
	activePolicyVersion  string
	policyEngineEnabled  bool
	adaptiveRetryEnabled bool
	replyPolicyEngine    *verifier.ProviderReplyPolicyEngine
	providerModes        map[string]string
	providerPolicies     []verifier.ProviderPolicy
	standard             policyConfig
	enhanced             policyConfig
}

type policyConfig struct {
	Enabled                    bool
	DNSTimeoutMs               int
	SMTPConnectTimeoutMs       int
	SMTPReadTimeoutMs          int
	MaxMXAttempts              int
	MaxConcurrencyDefault      int
	PerDomainConcurrency       int
	CatchAllDetectionEnabled   bool
	GlobalConnectsPerMinute    *int
	TempfailBackoffSeconds     *int
	CircuitBreakerTempfailRate *float64
}

type chunkOutputs struct {
	ValidData    []byte
	InvalidData  []byte
	RiskyData    []byte
	EmailCount   int
	ValidCount   int
	InvalidCount int
	RiskyCount   int
	ReasonCounts map[string]int
	ReasonTags   map[string]int
}

func (c *chunkOutputs) baseReasonCount(reason string) int {
	if c == nil || c.ReasonCounts == nil {
		return 0
	}

	return c.ReasonCounts[reason]
}

func (c *chunkOutputs) baseReasonPrefixCount(prefix string) int {
	if c == nil || c.ReasonCounts == nil {
		return 0
	}

	total := 0
	for key, value := range c.ReasonCounts {
		if strings.HasPrefix(key, prefix) {
			total += value
		}
	}

	return total
}

func New(client *api.Client, cfg Config) *Worker {
	max := cfg.MaxConcurrency
	if max < 1 {
		max = 1
	}

	laravelHeartbeatEveryN := cfg.LaravelHeartbeatEveryN
	if laravelHeartbeatEveryN < 1 {
		laravelHeartbeatEveryN = 1
	}

	w := &Worker{
		client:         client,
		cfg:            cfg,
		maxConcurrency: int64(max),
		telemetry:      newWorkerTelemetry(),
	}
	w.cfg.LaravelHeartbeatEveryN = laravelHeartbeatEveryN
	w.desiredState.Store("running")
	return w
}

func (w *Worker) Run(ctx context.Context) error {
	lastHeartbeat := time.Time{}

	for {
		now := time.Now()

		select {
		case <-ctx.Done():
			w.wg.Wait()
			return ctx.Err()
		default:
		}

		if now.Sub(lastHeartbeat) >= w.cfg.HeartbeatInterval {
			w.heartbeatCount++
			w.sendHeartbeats(ctx)
			lastHeartbeat = now
		}

		w.refreshPolicyIfNeeded(ctx, now)

		if w.enginePaused() {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		switch w.currentDesiredState() {
		case "paused", "stopped":
			time.Sleep(w.cfg.PollInterval)
			continue
		case "draining":
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		if w.activeCount() >= w.currentMaxConcurrency() {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		claimReq := api.ClaimNextRequest{
			EngineServer:     w.cfg.Server,
			WorkerID:         w.cfg.WorkerID,
			WorkerCapability: w.workerCapability(),
			LeaseSeconds:     w.cfg.LeaseSeconds,
		}

		claim, ok, err := w.client.ClaimNext(ctx, claimReq)
		if err != nil {
			fmt.Printf("claim-next error: %v\n", err)
			time.Sleep(w.cfg.PollInterval)
			continue
		}
		if !ok {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		w.telemetry.recordClaimRouting(claimRoutingSnapshot{
			ProcessingStage:  claim.Data.ProcessingStage,
			RetryAttempt:     claim.Data.RetryAttempt,
			LastWorkerIDs:    claim.Data.LastWorkerIDs,
			WorkerID:         w.cfg.WorkerID,
			PreferredPool:    claim.Data.PreferredPool,
			WorkerPool:       stringFromMeta(w.cfg.Server.Meta, "pool"),
			RoutingProvider:  strings.ToLower(strings.TrimSpace(claim.Data.RoutingProvider)),
			ProviderAffinity: strings.ToLower(strings.TrimSpace(stringFromMeta(w.cfg.Server.Meta, "provider_affinity"))),
		})

		w.wg.Add(1)
		w.incrementActive()
		go func(claim *api.ClaimNextResponse) {
			defer w.wg.Done()
			defer w.decrementActive()

			if err := w.processChunk(ctx, claim); err != nil {
				fmt.Printf("chunk %s error: %v\n", claim.Data.ChunkID, err)
			}
		}(claim)
	}
}

func (w *Worker) processChunk(ctx context.Context, claim *api.ClaimNextResponse) error {
	chunkID := claim.Data.ChunkID
	correlationID := fmt.Sprintf("%s:%s", claim.Data.JobID, chunkID)

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_claimed",
		"message": "Chunk claimed by worker.",
		"context": map[string]interface{}{
			"worker_id":          w.cfg.WorkerID,
			"chunk_no":           claim.Data.ChunkNo,
			"processing_stage":   claim.Data.ProcessingStage,
			"routing_provider":   claim.Data.RoutingProvider,
			"preferred_pool":     claim.Data.PreferredPool,
			"max_probe_attempts": claim.Data.MaxProbeAttempts,
			"correlation_id":     correlationID,
		},
	})

	processingStage := normalizeProcessingStage(claim.Data.ProcessingStage)
	if !w.canProcessStage(processingStage) {
		return w.failChunk(ctx, chunkID, processingStage, "worker capability does not match chunk stage", fmt.Errorf("stage=%s capability=%s", processingStage, w.workerCapability()), true)
	}

	mode := modeForStage(processingStage, claim.Data.VerificationMode)
	policy, hasPolicy := w.policyForMode(mode)
	enhancedAllowed := hasPolicy && w.policyEnhancedAllowed() && policy.Enabled
	routingProvider := normalizeProviderForRuntime(claim.Data.RoutingProvider)
	providerMode := w.providerModeForRuntime(routingProvider)

	details, err := w.client.ChunkDetails(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to load chunk details", err, true)
	}

	inputURL, err := w.client.InputURL(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to fetch input url", err, true)
	}

	reader, err := downloadStream(ctx, inputURL.Data.URL)
	if err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to download input", err, true)
	}
	defer reader.Close()

	var engineVerifier verifier.Verifier

	if processingStage == "smtp_probe" {
		if providerMode == "quarantine" || providerMode == "drain" {
			_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
				"level":   "warning",
				"event":   "smtp_probe_quarantined",
				"message": "SMTP probe stage skipped due to provider mode.",
				"context": map[string]interface{}{
					"processing_stage":  processingStage,
					"verification_mode": mode,
					"provider_mode":     providerMode,
					"routing_provider":  routingProvider,
				},
			})
			engineVerifier = staticRiskyVerifier{reason: "smtp_probe_quarantine_mode"}
		} else if providerMode == "degraded_probe" && !w.isTrustedProbeWorker() {
			_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
				"level":   "warning",
				"event":   "smtp_probe_degraded_skip",
				"message": "SMTP probe stage skipped by degraded-probe mode for non-trusted worker.",
				"context": map[string]interface{}{
					"processing_stage":  processingStage,
					"verification_mode": mode,
					"provider_mode":     providerMode,
					"routing_provider":  routingProvider,
				},
			})
			engineVerifier = staticRiskyVerifier{reason: "smtp_probe_degraded_mode"}
		} else if !enhancedAllowed {
			_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
				"level":   "warning",
				"event":   "smtp_probe_disabled",
				"message": "SMTP probe stage requested while enhanced probe policy is disabled; writing conservative risky outcomes.",
				"context": map[string]interface{}{
					"processing_stage":  processingStage,
					"verification_mode": mode,
				},
			})
			engineVerifier = staticRiskyVerifier{reason: "smtp_probe_disabled"}
		} else if !w.hasMailFromIdentity() {
			_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
				"level":   "warning",
				"event":   "smtp_probe_identity_missing",
				"message": "SMTP probe stage requested without mail-from identity; writing conservative risky outcomes.",
				"context": map[string]interface{}{
					"processing_stage":  processingStage,
					"verification_mode": mode,
				},
			})
			engineVerifier = staticRiskyVerifier{reason: "smtp_probe_identity_missing"}
		} else {
			engineVerifier = w.verifierForMode(mode, policy, hasPolicy)
		}
	} else {
		engineVerifier = w.verifierForMode(mode, policy, hasPolicy)
	}
	outputs, err := buildOutputs(
		ctx,
		reader,
		engineVerifier,
		w.cfg.ProbeAttemptChainEnabled,
		w.cfg.UnknownReasonTaxonomyEnabled,
	)
	if err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to parse input", err, false)
	}

	outputURLs, err := w.client.OutputURLs(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to fetch output urls", err, true)
	}

	if err := uploadSigned(ctx, outputURLs.Data.Targets.Valid.URL, outputs.ValidData); err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to upload valid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Invalid.URL, outputs.InvalidData); err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to upload invalid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Risky.URL, outputs.RiskyData); err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to upload risky output", err, true)
	}

	completePayload := map[string]interface{}{
		"output_disk":   outputURLs.Data.Disk,
		"valid_key":     outputURLs.Data.Targets.Valid.Key,
		"invalid_key":   outputURLs.Data.Targets.Invalid.Key,
		"risky_key":     outputURLs.Data.Targets.Risky.Key,
		"email_count":   outputs.EmailCount,
		"valid_count":   outputs.ValidCount,
		"invalid_count": outputs.InvalidCount,
		"risky_count":   outputs.RiskyCount,
	}

	if err := w.client.CompleteChunk(ctx, chunkID, completePayload); err != nil {
		return w.failChunk(ctx, chunkID, processingStage, "failed to complete chunk", err, true)
	}

	w.telemetry.recordChunkSuccess(processingStage, claim.Data.RoutingProvider, outputs)

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_completed",
		"message": "Chunk completed by worker.",
		"context": map[string]interface{}{
			"chunk_no":         details.Data.ChunkNo,
			"email_count":      outputs.EmailCount,
			"valid_count":      outputs.ValidCount,
			"invalid_count":    outputs.InvalidCount,
			"risky_count":      outputs.RiskyCount,
			"processing_stage": processingStage,
			"routing_provider": claim.Data.RoutingProvider,
			"preferred_pool":   claim.Data.PreferredPool,
			"correlation_id":   correlationID,
		},
	})

	return nil
}

func (w *Worker) failChunk(ctx context.Context, chunkID, stage, message string, err error, retryable bool) error {
	w.telemetry.recordChunkFailure(stage)

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "error",
		"event":   "chunk_error",
		"message": message,
		"context": map[string]interface{}{
			"error": err.Error(),
		},
	})

	_ = w.client.FailChunk(ctx, chunkID, map[string]interface{}{
		"error_message": message,
		"retryable":     retryable,
	})

	return err
}

func downloadStream(ctx context.Context, url string) (io.ReadCloser, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		defer resp.Body.Close()
		return nil, fmt.Errorf("download failed with status %d", resp.StatusCode)
	}

	return resp.Body, nil
}

func uploadSigned(ctx context.Context, url string, data []byte) error {
	req, err := http.NewRequestWithContext(ctx, http.MethodPut, url, bytes.NewReader(data))
	if err != nil {
		return err
	}

	req.Header.Set("Content-Type", "text/csv")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("upload failed with status %d", resp.StatusCode)
	}

	return nil
}

func buildOutputs(
	ctx context.Context,
	reader io.Reader,
	engineVerifier verifier.Verifier,
	probeAttemptChainEnabled bool,
	unknownReasonTaxonomyEnabled bool,
) (*chunkOutputs, error) {
	if engineVerifier == nil {
		return nil, fmt.Errorf("verifier not configured")
	}

	validBuf := &bytes.Buffer{}
	invalidBuf := &bytes.Buffer{}
	riskyBuf := &bytes.Buffer{}

	validWriter := csv.NewWriter(validBuf)
	invalidWriter := csv.NewWriter(invalidBuf)
	riskyWriter := csv.NewWriter(riskyBuf)

	header := []string{"email", "reason"}
	_ = validWriter.Write(header)
	_ = invalidWriter.Write(header)
	_ = riskyWriter.Write(header)

	scanner := bufio.NewScanner(reader)
	scanner.Buffer(make([]byte, 0, 64*1024), 1024*1024)

	output := &chunkOutputs{}
	output.ReasonCounts = map[string]int{}
	output.ReasonTags = map[string]int{}

	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" {
			continue
		}

		if isHeaderLine(line) {
			continue
		}

		output.EmailCount++
		result := engineVerifier.Verify(ctx, line)
		reason := reasonWithEvidence(result, probeAttemptChainEnabled, unknownReasonTaxonomyEnabled)
		baseReason := baseReasonOnly(reason)
		output.ReasonCounts[baseReason]++
		if reasonTag := reasonTagFrom(reason); reasonTag != "" {
			output.ReasonTags[reasonTag]++
		}

		switch result.Category {
		case verifier.CategoryInvalid:
			output.InvalidCount++
			_ = invalidWriter.Write([]string{line, reason})
		case verifier.CategoryRisky:
			output.RiskyCount++
			_ = riskyWriter.Write([]string{line, reason})
		case verifier.CategoryValid:
			output.ValidCount++
			_ = validWriter.Write([]string{line, reason})
		default:
			output.RiskyCount++
			_ = riskyWriter.Write([]string{line, reason})
		}
	}

	if err := scanner.Err(); err != nil {
		return nil, err
	}

	validWriter.Flush()
	invalidWriter.Flush()
	riskyWriter.Flush()

	if err := firstError(validWriter.Error(), invalidWriter.Error(), riskyWriter.Error()); err != nil {
		return nil, err
	}

	output.ValidData = validBuf.Bytes()
	output.InvalidData = invalidBuf.Bytes()
	output.RiskyData = riskyBuf.Bytes()

	return output, nil
}

func baseReasonOnly(reason string) string {
	normalized := strings.TrimSpace(reason)
	if normalized == "" {
		return "unknown"
	}

	if separator := strings.Index(normalized, ":"); separator >= 0 {
		normalized = normalized[:separator]
	}

	normalized = strings.TrimSpace(normalized)
	if normalized == "" {
		return "unknown"
	}

	return normalized
}

func reasonTagFrom(reason string) string {
	normalized := strings.TrimSpace(reason)
	if normalized == "" {
		return ""
	}

	separator := strings.Index(normalized, ":")
	if separator < 0 || separator+1 >= len(normalized) {
		return ""
	}

	metadata := strings.Split(normalized[separator+1:], ";")
	for _, token := range metadata {
		token = strings.TrimSpace(token)
		if !strings.HasPrefix(token, "tag=") {
			continue
		}

		value := strings.TrimSpace(strings.TrimPrefix(token, "tag="))
		if value != "" {
			return value
		}
	}

	return ""
}

func isHeaderLine(line string) bool {
	lower := strings.ToLower(strings.TrimSpace(line))
	if lower == "email" {
		return true
	}
	if strings.HasPrefix(lower, "email,") {
		return true
	}
	if strings.HasPrefix(lower, "email;") {
		return true
	}

	return !strings.Contains(line, "@")
}

func reasonWithEvidence(
	result verifier.Result,
	probeAttemptChainEnabled bool,
	unknownReasonTaxonomyEnabled bool,
) string {
	reason := strings.TrimSpace(result.Reason)
	if reason == "" {
		reason = "unknown"
	}

	segments := make([]string, 0, 12)
	if decisionClass := strings.TrimSpace(result.DecisionClass); decisionClass != "" {
		segments = append(segments, "decision="+decisionClass)
	}
	if decisionConfidence := strings.TrimSpace(result.DecisionConfidence); decisionConfidence != "" {
		segments = append(segments, "confidence="+decisionConfidence)
	}
	if retryStrategy := strings.TrimSpace(result.RetryStrategy); retryStrategy != "" {
		segments = append(segments, "retry="+retryStrategy)
	}
	if policyVersion := strings.TrimSpace(result.PolicyVersion); policyVersion != "" {
		segments = append(segments, "policy="+policyVersion)
	}
	if rule := strings.TrimSpace(result.MatchedRuleID); rule != "" {
		segments = append(segments, "rule="+rule)
	}
	reasonTag := strings.TrimSpace(result.ReasonTag)
	if unknownReasonTaxonomyEnabled {
		reasonTag = normalizedReasonTag(result, reason)
	}
	if reasonTag != "" {
		segments = append(segments, "tag="+reasonTag)
	}
	if mode := strings.TrimSpace(result.ProviderMode); mode != "" {
		segments = append(segments, "mode="+mode)
	}
	if strategyID := strings.TrimSpace(result.SessionStrategyID); strategyID != "" {
		segments = append(segments, "session="+strategyID)
	}
	if provider := strings.TrimSpace(result.ProviderProfile); provider != "" {
		segments = append(segments, "provider="+provider)
	}
	if mxHost := strings.TrimSpace(result.MXHost); mxHost != "" {
		segments = append(segments, "mx="+mxHost)
	}
	if result.AttemptNumber > 0 {
		segments = append(segments, "attempt="+strconv.Itoa(result.AttemptNumber))
	}
	if route := strings.TrimSpace(result.AttemptRoute); route != "" {
		segments = append(segments, "route="+route)
	}
	if evidenceStrength := strings.TrimSpace(result.EvidenceStrength); evidenceStrength != "" {
		segments = append(segments, "evidence="+evidenceStrength)
	}
	if probeAttemptChainEnabled {
		if attemptChain := encodeAttemptChain(result.AttemptChain); attemptChain != "" {
			segments = append(segments, "attempt_chain="+attemptChain)
		}
	}

	if len(segments) == 0 {
		return reason
	}

	return reason + ":" + strings.Join(segments, ";")
}

func encodeAttemptChain(chain []verifier.AttemptEvidence) string {
	if len(chain) == 0 {
		return ""
	}

	encoded, err := json.Marshal(chain)
	if err != nil {
		return ""
	}

	return base64.RawURLEncoding.EncodeToString(encoded)
}

func normalizedReasonTag(result verifier.Result, baseReason string) string {
	currentTag := strings.ToLower(strings.TrimSpace(result.ReasonTag))
	baseReason = strings.ToLower(strings.TrimSpace(baseReason))
	decisionClass := strings.ToLower(strings.TrimSpace(result.DecisionClass))

	if result.Category != verifier.CategoryRisky {
		return currentTag
	}

	switch {
	case baseReason == "smtp_probe_identity_missing",
		strings.Contains(baseReason, "mailfrom"),
		currentTag == "auth_required":
		return "identity_rejected"
	case strings.Contains(baseReason, "connect_timeout"),
		strings.Contains(baseReason, "read_error"),
		baseReason == "smtp_timeout":
		return "connection_unstable"
	case decisionClass == verifier.DecisionPolicyBlocked,
		currentTag == "policy_blocked",
		strings.Contains(baseReason, "policy_blocked"):
		return "policy_blocked_ambiguous"
	case decisionClass == verifier.DecisionRetryable,
		baseReason == "smtp_tempfail",
		currentTag == "greylist",
		currentTag == "rate_limit":
		return "provider_tempfail_unresolved"
	case decisionClass == verifier.DecisionUnknown:
		if currentTag == "unknown_transient" || currentTag == "" {
			return "unknown_transient"
		}
		return "other_unknown"
	}

	if currentTag == "" {
		return "other_unknown"
	}

	switch currentTag {
	case "unknown_transient",
		"policy_blocked_ambiguous",
		"provider_tempfail_unresolved",
		"connection_unstable",
		"identity_rejected",
		"other_unknown":
		return currentTag
	case "policy_blocked":
		return "policy_blocked_ambiguous"
	case "greylist", "rate_limit":
		return "provider_tempfail_unresolved"
	}

	return "other_unknown"
}

func firstError(errors ...error) error {
	for _, err := range errors {
		if err != nil {
			return err
		}
	}

	return nil
}

func (w *Worker) refreshPolicyIfNeeded(ctx context.Context, now time.Time) {
	if w.cfg.PolicyRefresh <= 0 {
		return
	}

	if !w.lastPolicyFetch.IsZero() && now.Sub(w.lastPolicyFetch) < w.cfg.PolicyRefresh {
		return
	}

	w.lastPolicyFetch = now

	resp, err := w.client.Policy(ctx)
	if err != nil {
		fmt.Printf("policy fetch error: %v\n", err)
		return
	}

	existing := w.policySnapshot()
	state := policyStateFrom(resp)
	runtime := w.resolvePolicyRuntime(ctx, existing)
	state.heloName = existing.heloName
	state.mailFromAddress = existing.mailFromAddress
	state.identityDomain = existing.identityDomain
	state.activePolicyVersion = runtime.activeVersion
	state.policyEngineEnabled = runtime.policyEngineEnabled
	state.adaptiveRetryEnabled = runtime.adaptiveRetryEnabled
	state.replyPolicyEngine = runtime.replyPolicyEngine
	state.providerModes = runtime.providerModes

	w.policyMu.Lock()
	w.policy = state
	w.policyMu.Unlock()

	w.updateMaxConcurrency(state)
}

type policyRuntimeState struct {
	activeVersion        string
	policyEngineEnabled  bool
	adaptiveRetryEnabled bool
	replyPolicyEngine    *verifier.ProviderReplyPolicyEngine
	providerModes        map[string]string
}

func (w *Worker) resolvePolicyRuntime(ctx context.Context, previous policyState) policyRuntimeState {
	result := policyRuntimeState{
		activeVersion:        previous.activePolicyVersion,
		policyEngineEnabled:  w.cfg.BaseVerifierConfig.ProviderPolicyEngineEnabled,
		adaptiveRetryEnabled: w.cfg.BaseVerifierConfig.AdaptiveRetryEnabled,
		replyPolicyEngine:    cloneProviderReplyPolicyEngine(w.cfg.BaseVerifierConfig.ProviderReplyPolicyEngine),
		providerModes:        cloneProviderModes(previous.providerModes),
	}

	if previous.replyPolicyEngine != nil {
		result.replyPolicyEngine = cloneProviderReplyPolicyEngine(previous.replyPolicyEngine)
	}

	if w.cfg.ControlPlanePolicySyncEnabled && w.cfg.ControlPlaneClient != nil {
		workerPool := stringFromMeta(w.cfg.Server.Meta, "pool")
		policies, err := w.cfg.ControlPlaneClient.ProviderPoliciesForPool(ctx, workerPool)
		if err != nil {
			fmt.Printf("control-plane policies fetch error: %v\n", err)
		} else {
			result.policyEngineEnabled = policies.Data.PolicyEngineEnabled
			result.adaptiveRetryEnabled = policies.Data.AdaptiveRetryEnabled
			result.activeVersion = strings.TrimSpace(policies.Data.ActiveVersion)
			if parsedModes := providerModesFromControlPlane(policies.Data.Modes); len(parsedModes) > 0 {
				result.providerModes = parsedModes
			}
		}
	}

	if result.activeVersion != "" {
		payloadResp, err := w.client.PolicyVersionPayload(ctx, result.activeVersion)
		if err != nil {
			var apiErr api.APIError
			if !errors.As(err, &apiErr) || apiErr.Status != http.StatusNotFound {
				fmt.Printf("policy version payload fetch error (%s): %v\n", result.activeVersion, err)
			}
		} else if len(payloadResp.Data.PolicyPayload) > 0 {
			parsed, parseErr := verifier.ParseProviderReplyPolicyEngineJSON(string(payloadResp.Data.PolicyPayload))
			if parseErr != nil {
				fmt.Printf("policy version payload parse error (%s): %v\n", result.activeVersion, parseErr)
			} else {
				result.replyPolicyEngine = parsed
			}
		}
	}

	if result.replyPolicyEngine == nil && result.policyEngineEnabled {
		result.replyPolicyEngine = verifier.DefaultProviderReplyPolicyEngine()
	}

	if result.replyPolicyEngine != nil {
		result.replyPolicyEngine.Enabled = result.policyEngineEnabled
		if strings.TrimSpace(result.activeVersion) != "" {
			result.replyPolicyEngine.Version = strings.TrimSpace(result.activeVersion)
		}
	}

	return result
}

func policyStateFrom(resp *api.PolicyResponse) policyState {
	state := policyState{
		loaded:               true,
		enginePaused:         resp.Data.EnginePaused,
		enhancedModeEnabled:  resp.Data.EnhancedModeEnabled,
		roleAccountsBehavior: normalizeRoleAccountsBehavior(resp.Data.RoleAccountsBehavior),
		roleAccounts:         mapFromSlice(resp.Data.RoleAccountsList),
		providerPolicies:     providerPoliciesFrom(resp.Data.ProviderPolicies),
	}

	if policy, ok := resp.Data.Policies["standard"]; ok {
		state.standard = policyConfigFrom(policy)
	}
	if policy, ok := resp.Data.Policies["enhanced"]; ok {
		state.enhanced = policyConfigFrom(policy)
	}

	return state
}

func policyConfigFrom(policy api.Policy) policyConfig {
	return policyConfig{
		Enabled:                    policy.Enabled,
		DNSTimeoutMs:               policy.DNSTimeoutMs,
		SMTPConnectTimeoutMs:       policy.SMTPConnectTimeoutMs,
		SMTPReadTimeoutMs:          policy.SMTPReadTimeoutMs,
		MaxMXAttempts:              policy.MaxMXAttempts,
		MaxConcurrencyDefault:      policy.MaxConcurrencyDefault,
		PerDomainConcurrency:       policy.PerDomainConcurrency,
		CatchAllDetectionEnabled:   policy.CatchAllDetectionEnabled,
		GlobalConnectsPerMinute:    policy.GlobalConnectsPerMinute,
		TempfailBackoffSeconds:     policy.TempfailBackoffSeconds,
		CircuitBreakerTempfailRate: policy.CircuitBreakerTempfailRate,
	}
}

func providerPoliciesFrom(policies []api.ProviderPolicy) []verifier.ProviderPolicy {
	if len(policies) == 0 {
		return nil
	}

	output := make([]verifier.ProviderPolicy, 0, len(policies))

	for _, policy := range policies {
		name := strings.TrimSpace(policy.Name)
		if name == "" {
			continue
		}

		domains := normalizeProviderDomains(policy.Domains)
		if len(domains) == 0 {
			continue
		}

		output = append(output, verifier.ProviderPolicy{
			Name:                    name,
			Enabled:                 policy.Enabled,
			Domains:                 domains,
			PerDomainConcurrency:    policy.PerDomainConcurrency,
			ConnectsPerMinute:       policy.ConnectsPerMinute,
			TempfailBackoffSeconds:  policy.TempfailBackoffSeconds,
			RetryableNetworkRetries: policy.RetryableNetworkRetries,
		})
	}

	return output
}

func providerModesFromControlPlane(modes []api.ControlPlaneProviderModeState) map[string]string {
	output := map[string]string{}

	for _, mode := range modes {
		provider := strings.ToLower(strings.TrimSpace(mode.Provider))
		if provider == "" {
			continue
		}

		switch provider {
		case "gmail", "microsoft", "yahoo", "generic":
		default:
			continue
		}

		normalizedMode := strings.ToLower(strings.TrimSpace(mode.Mode))
		switch normalizedMode {
		case "normal", "cautious", "drain", "quarantine", "degraded_probe":
		default:
			normalizedMode = "normal"
		}

		output[provider] = normalizedMode
	}

	return output
}

func cloneProviderModes(source map[string]string) map[string]string {
	if len(source) == 0 {
		return map[string]string{}
	}

	cloned := make(map[string]string, len(source))
	for key, value := range source {
		cloned[key] = value
	}

	return cloned
}

func normalizeProviderDomains(domains []string) []string {
	output := make([]string, 0, len(domains))
	seen := map[string]struct{}{}

	for _, domain := range domains {
		domain = strings.ToLower(strings.TrimSpace(domain))
		domain = strings.TrimPrefix(domain, ".")
		domain = strings.TrimPrefix(domain, "*.")
		if domain == "" {
			continue
		}
		if _, ok := seen[domain]; ok {
			continue
		}
		seen[domain] = struct{}{}
		output = append(output, domain)
	}

	return output
}

func (w *Worker) enginePaused() bool {
	state := w.policySnapshot()
	if !state.loaded {
		return false
	}

	return state.enginePaused
}

func (w *Worker) policyEnhancedAllowed() bool {
	state := w.policySnapshot()
	if !state.loaded {
		return false
	}

	return state.enhancedModeEnabled
}

func (w *Worker) policyForMode(mode string) (policyConfig, bool) {
	state := w.policySnapshot()
	if !state.loaded {
		return policyConfig{}, false
	}

	if mode == "enhanced" {
		return state.enhanced, true
	}

	return state.standard, true
}

func (w *Worker) policySnapshot() policyState {
	w.policyMu.RLock()
	defer w.policyMu.RUnlock()

	return w.policy
}

func (w *Worker) hasMailFromIdentity() bool {
	if w.cfg.BaseVerifierConfig.MailFromAddress != "" {
		return true
	}

	state := w.policySnapshot()
	return state.mailFromAddress != ""
}

func (w *Worker) isHeartbeatIdentityMissing() bool {
	if strings.TrimSpace(w.cfg.BaseVerifierConfig.MailFromAddress) != "" {
		return false
	}

	state := w.policySnapshot()
	return strings.TrimSpace(state.mailFromAddress) == ""
}

func (w *Worker) verifierForMode(mode string, policy policyConfig, hasPolicy bool) verifier.Verifier {
	config := w.cfg.BaseVerifierConfig
	state := w.policySnapshot()
	config = applyGlobalOverrides(config, state)

	if hasPolicy {
		config = applyPolicy(config, policy)
	}

	config.ProviderPolicyEngineEnabled = state.policyEngineEnabled
	config.AdaptiveRetryEnabled = state.adaptiveRetryEnabled
	config.ProviderModes = cloneProviderModes(state.providerModes)
	if state.replyPolicyEngine != nil {
		config.ProviderReplyPolicyEngine = cloneProviderReplyPolicyEngine(state.replyPolicyEngine)
	} else {
		config.ProviderReplyPolicyEngine = cloneProviderReplyPolicyEngine(config.ProviderReplyPolicyEngine)
	}

	if config.ProviderReplyPolicyEngine != nil {
		config.ProviderReplyPolicyEngine.Enabled = config.ProviderPolicyEngineEnabled
	}

	var smtpFactory verifier.SMTPCheckerFactory
	if mode == "enhanced" {
		smtpFactory = func(cfg verifier.Config) verifier.SMTPChecker {
			return verifier.NetSMTPProber{
				Dialer:                   nil,
				ConnectTimeout:           time.Duration(cfg.SMTPConnectTimeout) * time.Millisecond,
				ReadTimeout:              time.Duration(cfg.SMTPReadTimeout) * time.Millisecond,
				EhloTimeout:              time.Duration(cfg.SMTPEhloTimeout) * time.Millisecond,
				HeloName:                 cfg.HeloName,
				MailFromAddress:          cfg.MailFromAddress,
				ProviderMode:             "normal",
				SessionStrategyID:        "generic:normal",
				RateLimiter:              verifier.NewRateLimiter(cfg.SMTPRateLimitPerMinute),
				CatchAllDetectionEnabled: cfg.CatchAllDetectionEnabled,
				ReplyPolicyEngine:        cfg.ProviderReplyPolicyEngine,
				AdaptiveRetryEnable:      cfg.AdaptiveRetryEnabled,
			}
		}
	} else {
		smtpFactory = func(cfg verifier.Config) verifier.SMTPChecker {
			return verifier.NetSMTPChecker{
				Dialer:              nil,
				ConnectTimeout:      time.Duration(cfg.SMTPConnectTimeout) * time.Millisecond,
				ReadTimeout:         time.Duration(cfg.SMTPReadTimeout) * time.Millisecond,
				EhloTimeout:         time.Duration(cfg.SMTPEhloTimeout) * time.Millisecond,
				HeloName:            cfg.HeloName,
				ProviderMode:        "normal",
				SessionStrategyID:   "generic:normal",
				RateLimiter:         verifier.NewRateLimiter(cfg.SMTPRateLimitPerMinute),
				ReplyPolicyEngine:   cfg.ProviderReplyPolicyEngine,
				AdaptiveRetryEnable: cfg.AdaptiveRetryEnabled,
			}
		}
	}

	return verifier.NewProviderAwareVerifier(config, verifier.NetMXResolver{}, smtpFactory, state.providerPolicies)
}

func applyGlobalOverrides(config verifier.Config, state policyState) verifier.Config {
	if state.heloName != "" {
		config.HeloName = state.heloName
	}
	if state.mailFromAddress != "" {
		config.MailFromAddress = state.mailFromAddress
	}
	if state.roleAccountsBehavior != "" {
		config.RoleAccountsBehavior = state.roleAccountsBehavior
	}
	if state.roleAccounts != nil {
		config.RoleAccounts = state.roleAccounts
	}

	return config
}

func applyPolicy(config verifier.Config, policy policyConfig) verifier.Config {
	if policy.DNSTimeoutMs > 0 {
		config.DNSTimeout = policy.DNSTimeoutMs
	}
	if policy.SMTPConnectTimeoutMs > 0 {
		config.SMTPConnectTimeout = policy.SMTPConnectTimeoutMs
	}
	if policy.SMTPReadTimeoutMs > 0 {
		config.SMTPReadTimeout = policy.SMTPReadTimeoutMs
	}
	if policy.MaxMXAttempts > 0 {
		config.MaxMXAttempts = policy.MaxMXAttempts
	}
	if policy.PerDomainConcurrency > 0 {
		config.PerDomainConcurrency = policy.PerDomainConcurrency
	}
	config.CatchAllDetectionEnabled = policy.CatchAllDetectionEnabled
	if policy.GlobalConnectsPerMinute != nil {
		config.SMTPRateLimitPerMinute = *policy.GlobalConnectsPerMinute
	}
	if policy.TempfailBackoffSeconds != nil {
		config.BackoffBaseMs = *policy.TempfailBackoffSeconds * 1000
	}

	return config
}

func normalizeRoleAccountsBehavior(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "" {
		return "risky"
	}

	if value == "risky" || value == "allow" {
		return value
	}

	return "risky"
}

func mapFromSlice(values []string) map[string]struct{} {
	output := map[string]struct{}{}

	for _, value := range values {
		value = strings.ToLower(strings.TrimSpace(value))
		if value == "" {
			continue
		}

		output[value] = struct{}{}
	}

	return output
}

func (w *Worker) updateMaxConcurrency(state policyState) {
	max := w.cfg.MaxConcurrency
	if max < 1 {
		max = 1
	}

	if state.loaded {
		if state.standard.MaxConcurrencyDefault > 0 {
			max = state.standard.MaxConcurrencyDefault
		}

		if state.enhanced.MaxConcurrencyDefault > 0 {
			max = minInt(max, state.enhanced.MaxConcurrencyDefault)
		}
	}

	if max < 1 {
		max = 1
	}

	atomic.StoreInt64(&w.maxConcurrency, int64(max))
}

func (w *Worker) activeCount() int64 {
	return atomic.LoadInt64(&w.active)
}

func (w *Worker) currentMaxConcurrency() int64 {
	return atomic.LoadInt64(&w.maxConcurrency)
}

func (w *Worker) incrementActive() {
	atomic.AddInt64(&w.active, 1)
}

func (w *Worker) decrementActive() {
	atomic.AddInt64(&w.active, -1)
}

func minInt(a, b int) int {
	if a == 0 {
		return b
	}
	if b == 0 {
		return a
	}
	if a < b {
		return a
	}

	return b
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}

	return b
}

func (w *Worker) workerCapability() string {
	return normalizeWorkerCapability(w.cfg.WorkerCapability)
}

func (w *Worker) canProcessStage(stage string) bool {
	capability := w.workerCapability()

	switch capability {
	case "all":
		return true
	case "screening":
		return stage == "screening"
	case "smtp_probe":
		return stage == "smtp_probe"
	default:
		return true
	}
}

func normalizeWorkerCapability(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "screening" || value == "smtp_probe" || value == "all" {
		return value
	}

	return "all"
}

func normalizeProcessingStage(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "smtp_probe" {
		return "smtp_probe"
	}

	return "screening"
}

func normalizeProviderForRuntime(value string) string {
	normalized := strings.ToLower(strings.TrimSpace(value))
	switch normalized {
	case "gmail", "microsoft", "yahoo", "generic":
		return normalized
	default:
		return "generic"
	}
}

func modeForStage(stage, requestedMode string) string {
	_ = requestedMode
	if stage == "smtp_probe" {
		return "enhanced"
	}

	return "standard"
}

func normalizeVerificationMode(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "enhanced" {
		return "enhanced"
	}

	return "standard"
}

func (w *Worker) sendHeartbeats(ctx context.Context) {
	if w.cfg.ControlPlaneHeartbeatEnabled && w.cfg.ControlPlaneClient != nil {
		snapshot := w.telemetry.snapshot()
		payload := api.ControlPlaneHeartbeatRequest{
			WorkerID:  w.cfg.WorkerID,
			Host:      w.cfg.Server.Name,
			IPAddress: w.cfg.Server.IPAddress,
			Pool:      stringFromMeta(w.cfg.Server.Meta, "pool"),
			Tags: []string{
				fmt.Sprintf("laravel_heartbeat:%t", w.cfg.LaravelHeartbeatEnabled),
				fmt.Sprintf("laravel_heartbeat_every_n:%d", maxInt(1, w.cfg.LaravelHeartbeatEveryN)),
				fmt.Sprintf("policy_sync:%t", w.cfg.ControlPlanePolicySyncEnabled),
			},
			Status:                w.currentDesiredState(),
			StageMetrics:          snapshot.stageMetrics,
			SMTPMetrics:           snapshot.smtpMetrics,
			ProviderMetrics:       snapshot.providerMetrics,
			RoutingMetrics:        snapshot.routingMetrics,
			SessionMetrics:        snapshot.sessionMetrics,
			AttemptRouteMetrics:   snapshot.attemptRouteMetrics,
			RetryAntiAffinityHits: snapshot.retryAntiAffinityHits,
			UnknownReasonTags:     snapshot.unknownReasonTags,
			SessionStrategyID:     w.currentSessionStrategyID(),
			ReasonTagCounts:       snapshot.reasonTagCounts,
		}

		response, err := w.cfg.ControlPlaneClient.Heartbeat(ctx, payload)
		if err != nil {
			fmt.Printf("control-plane heartbeat error: %v\n", err)
		} else {
			w.applyControlPlaneHeartbeat(response)
		}
	}

	if !w.cfg.LaravelHeartbeatEnabled {
		return
	}

	heartbeatEvery := maxInt(1, w.cfg.LaravelHeartbeatEveryN)
	if !w.isHeartbeatIdentityMissing() && int(w.heartbeatCount)%heartbeatEvery != 0 {
		return
	}

	resp, err := w.client.Heartbeat(ctx, w.cfg.Server)
	if err != nil {
		fmt.Printf("laravel heartbeat warning: %v\n", err)
		return
	}

	w.applyHeartbeatIdentity(resp)
}

func (w *Worker) applyControlPlaneHeartbeat(resp *api.ControlPlaneHeartbeatResponse) {
	if resp == nil {
		return
	}

	desiredState := normalizeDesiredState(resp.DesiredState)
	if desiredState != "" {
		w.desiredState.Store(desiredState)
	}

	for _, command := range resp.Commands {
		command = strings.ToLower(strings.TrimSpace(command))
		switch command {
		case "pause":
			w.desiredState.Store("paused")
		case "resume", "run":
			w.desiredState.Store("running")
		case "drain":
			w.desiredState.Store("draining")
		case "stop":
			w.desiredState.Store("stopped")
		}
	}
}

func (w *Worker) currentDesiredState() string {
	state, ok := w.desiredState.Load().(string)
	if !ok {
		return "running"
	}

	state = normalizeDesiredState(state)
	if state == "" {
		return "running"
	}

	return state
}

func (w *Worker) currentSessionStrategyID() string {
	state := w.policySnapshot()
	version := strings.TrimSpace(state.activePolicyVersion)
	if version == "" {
		version = "unversioned"
	}

	mode := "standard"
	if state.policyEngineEnabled || state.adaptiveRetryEnabled {
		mode = "provider_policy"
	}

	return fmt.Sprintf("%s:%s", mode, version)
}

func (w *Worker) providerModeForRuntime(provider string) string {
	provider = normalizeProviderForRuntime(provider)

	state := w.policySnapshot()
	if state.providerModes == nil {
		return "normal"
	}

	mode := strings.ToLower(strings.TrimSpace(state.providerModes[provider]))
	switch mode {
	case "normal", "cautious", "drain", "quarantine", "degraded_probe":
		return mode
	default:
		return "normal"
	}
}

func (w *Worker) isTrustedProbeWorker() bool {
	trustTier := strings.ToLower(strings.TrimSpace(stringFromMeta(w.cfg.Server.Meta, "trust_tier")))
	switch trustTier {
	case "trusted", "premium", "high":
		return true
	default:
		return false
	}
}

func normalizeDesiredState(value string) string {
	normalized := strings.ToLower(strings.TrimSpace(value))
	switch normalized {
	case "running", "paused", "draining", "stopped":
		return normalized
	default:
		return ""
	}
}

func stringFromMeta(meta map[string]interface{}, key string) string {
	if meta == nil {
		return ""
	}

	value, ok := meta[key]
	if !ok {
		return ""
	}

	normalized := strings.TrimSpace(fmt.Sprintf("%v", value))
	return normalized
}

func (w *Worker) applyHeartbeatIdentity(resp *api.HeartbeatResponse) {
	if resp == nil {
		return
	}

	identity := resp.Data.Identity

	w.policyMu.Lock()
	w.policy.heloName = strings.TrimSpace(identity.HeloName)
	w.policy.mailFromAddress = strings.TrimSpace(identity.MailFromAddress)
	w.policy.identityDomain = strings.TrimSpace(identity.IdentityDomain)
	w.policyMu.Unlock()
}

func cloneProviderReplyPolicyEngine(
	engine *verifier.ProviderReplyPolicyEngine,
) *verifier.ProviderReplyPolicyEngine {
	if engine == nil {
		return nil
	}

	payload, err := json.Marshal(engine)
	if err != nil {
		clone := *engine
		return &clone
	}

	parsed, parseErr := verifier.ParseProviderReplyPolicyEngineJSON(string(payload))
	if parseErr != nil {
		clone := *engine
		return &clone
	}

	parsed.Enabled = engine.Enabled
	if strings.TrimSpace(engine.Version) != "" {
		parsed.Version = strings.TrimSpace(engine.Version)
	}

	return parsed
}

type staticRiskyVerifier struct {
	reason string
}

func (v staticRiskyVerifier) Verify(context.Context, string) verifier.Result {
	reason := strings.TrimSpace(v.reason)
	if reason == "" {
		reason = "smtp_tempfail"
	}

	return verifier.Result{
		Category:      verifier.CategoryRisky,
		Reason:        reason,
		ReasonCode:    reason,
		DecisionClass: verifier.DecisionUnknown,
	}
}
