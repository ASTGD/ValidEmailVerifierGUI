package worker

import (
	"bufio"
	"bytes"
	"context"
	"encoding/csv"
	"fmt"
	"io"
	"net/http"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"engine-worker-go/internal/api"
	"engine-worker-go/internal/verifier"
)

type Config struct {
	PollInterval       time.Duration
	HeartbeatInterval  time.Duration
	LeaseSeconds       *int
	MaxConcurrency     int
	PolicyRefresh      time.Duration
	Server             api.EngineServerPayload
	WorkerID           string
	BaseVerifierConfig verifier.Config
}

type Worker struct {
	client          *api.Client
	cfg             Config
	wg              sync.WaitGroup
	active          int64
	maxConcurrency  int64
	policyMu        sync.RWMutex
	policy          policyState
	lastPolicyFetch time.Time
}

type policyState struct {
	loaded               bool
	enginePaused         bool
	enhancedModeEnabled  bool
	roleAccountsBehavior string
	roleAccounts         map[string]struct{}
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
}

func New(client *api.Client, cfg Config) *Worker {
	max := cfg.MaxConcurrency
	if max < 1 {
		max = 1
	}

	return &Worker{
		client:         client,
		cfg:            cfg,
		maxConcurrency: int64(max),
	}
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
			if err := w.client.Heartbeat(ctx, w.cfg.Server); err != nil {
				fmt.Printf("heartbeat error: %v\n", err)
			}
			lastHeartbeat = now
		}

		w.refreshPolicyIfNeeded(ctx, now)

		if w.enginePaused() {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		if w.activeCount() >= w.currentMaxConcurrency() {
			time.Sleep(w.cfg.PollInterval)
			continue
		}

		claimReq := api.ClaimNextRequest{
			EngineServer: w.cfg.Server,
			WorkerID:     w.cfg.WorkerID,
			LeaseSeconds: w.cfg.LeaseSeconds,
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

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_claimed",
		"message": "Chunk claimed by worker.",
		"context": map[string]interface{}{
			"worker_id": w.cfg.WorkerID,
			"chunk_no":  claim.Data.ChunkNo,
		},
	})

	mode := normalizeVerificationMode(claim.Data.VerificationMode)
	policy, hasPolicy := w.policyForMode(mode)
	enhancedAllowed := hasPolicy && w.policyEnhancedAllowed() && policy.Enabled
	if mode == "enhanced" && !enhancedAllowed {
		_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
			"level":   "warning",
			"event":   "enhanced_mode_requested_but_not_enabled",
			"message": "Enhanced mode requested but not enabled; running standard pipeline.",
			"context": map[string]interface{}{
				"verification_mode": mode,
			},
		})
		mode = "standard"
		policy, hasPolicy = w.policyForMode(mode)
	}

	details, err := w.client.ChunkDetails(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to load chunk details", err, true)
	}

	inputURL, err := w.client.InputURL(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to fetch input url", err, true)
	}

	reader, err := downloadStream(ctx, inputURL.Data.URL)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to download input", err, true)
	}
	defer reader.Close()

	engineVerifier := w.verifierForMode(mode, policy, hasPolicy)
	outputs, err := buildOutputs(ctx, reader, engineVerifier)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to parse input", err, false)
	}

	outputURLs, err := w.client.OutputURLs(ctx, chunkID)
	if err != nil {
		return w.failChunk(ctx, chunkID, "failed to fetch output urls", err, true)
	}

	if err := uploadSigned(ctx, outputURLs.Data.Targets.Valid.URL, outputs.ValidData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload valid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Invalid.URL, outputs.InvalidData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload invalid output", err, true)
	}
	if err := uploadSigned(ctx, outputURLs.Data.Targets.Risky.URL, outputs.RiskyData); err != nil {
		return w.failChunk(ctx, chunkID, "failed to upload risky output", err, true)
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
		return w.failChunk(ctx, chunkID, "failed to complete chunk", err, true)
	}

	_ = w.client.LogChunk(ctx, chunkID, map[string]interface{}{
		"level":   "info",
		"event":   "chunk_completed",
		"message": "Chunk completed by worker.",
		"context": map[string]interface{}{
			"chunk_no":      details.Data.ChunkNo,
			"email_count":   outputs.EmailCount,
			"valid_count":   outputs.ValidCount,
			"invalid_count": outputs.InvalidCount,
			"risky_count":   outputs.RiskyCount,
		},
	})

	return nil
}

func (w *Worker) failChunk(ctx context.Context, chunkID, message string, err error, retryable bool) error {
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

func buildOutputs(ctx context.Context, reader io.Reader, engineVerifier verifier.Verifier) (*chunkOutputs, error) {
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

		switch result.Category {
		case verifier.CategoryInvalid:
			output.InvalidCount++
			_ = invalidWriter.Write([]string{line, result.Reason})
		case verifier.CategoryRisky:
			output.RiskyCount++
			_ = riskyWriter.Write([]string{line, result.Reason})
		case verifier.CategoryValid:
			output.ValidCount++
			_ = validWriter.Write([]string{line, result.Reason})
		default:
			output.RiskyCount++
			reason := result.Reason
			if reason == "" {
				reason = "unknown"
			}
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

	state := policyStateFrom(resp)

	w.policyMu.Lock()
	w.policy = state
	w.policyMu.Unlock()

	w.updateMaxConcurrency(state)
}

func policyStateFrom(resp *api.PolicyResponse) policyState {
	state := policyState{
		loaded:               true,
		enginePaused:         resp.Data.EnginePaused,
		enhancedModeEnabled:  resp.Data.EnhancedModeEnabled,
		roleAccountsBehavior: normalizeRoleAccountsBehavior(resp.Data.RoleAccountsBehavior),
		roleAccounts:         mapFromSlice(resp.Data.RoleAccountsList),
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
		GlobalConnectsPerMinute:    policy.GlobalConnectsPerMinute,
		TempfailBackoffSeconds:     policy.TempfailBackoffSeconds,
		CircuitBreakerTempfailRate: policy.CircuitBreakerTempfailRate,
	}
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

func (w *Worker) verifierForMode(mode string, policy policyConfig, hasPolicy bool) verifier.Verifier {
	config := w.cfg.BaseVerifierConfig
	state := w.policySnapshot()

	if state.loaded {
		config = applyGlobalOverrides(config, state)
	}

	if hasPolicy {
		config = applyPolicy(config, policy)
	}

	return verifier.NewPipelineVerifier(config, verifier.NetMXResolver{}, nil)
}

func applyGlobalOverrides(config verifier.Config, state policyState) verifier.Config {
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

func normalizeVerificationMode(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	if value == "enhanced" {
		return "enhanced"
	}

	return "standard"
}
