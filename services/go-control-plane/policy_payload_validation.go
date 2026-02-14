package main

import (
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const (
	policyValidationStatusPending = "pending"
	policyValidationStatusValid   = "valid"
	policyValidationStatusInvalid = "invalid"
)

var requiredRetryFields = []string{
	"default_seconds",
	"tempfail_seconds",
	"greylist_seconds",
	"policy_blocked_seconds",
	"unknown_seconds",
}

type PolicyPayloadValidation struct {
	Version       string
	Checksum      string
	ValidatedAt   string
	Status        string
	ErrorMessage  string
	PayloadLoaded bool
}

type PolicyPayloadValidator interface {
	ValidateVersion(ctx context.Context, version string) (PolicyPayloadValidation, error)
}

type LaravelPolicyPayloadValidator struct {
	baseURL string
	token   string
	client  *http.Client
}

func NewLaravelPolicyPayloadValidator(cfg Config) *LaravelPolicyPayloadValidator {
	baseURL := strings.TrimRight(strings.TrimSpace(cfg.LaravelAPIBaseURL), "/")
	token := strings.TrimSpace(cfg.LaravelVerifierToken)
	if baseURL == "" || token == "" {
		return nil
	}

	return &LaravelPolicyPayloadValidator{
		baseURL: baseURL,
		token:   token,
		client:  &http.Client{Timeout: 8 * time.Second},
	}
}

func (v *LaravelPolicyPayloadValidator) ValidateVersion(ctx context.Context, version string) (PolicyPayloadValidation, error) {
	now := time.Now().UTC().Format(time.RFC3339)
	normalizedVersion := normalizePolicyVersion(version)
	result := PolicyPayloadValidation{
		Version:     normalizedVersion,
		ValidatedAt: now,
		Status:      policyValidationStatusInvalid,
	}

	if v == nil || v.baseURL == "" || v.token == "" {
		result.ErrorMessage = "laravel policy validator is not configured"
		return result, fmt.Errorf(result.ErrorMessage)
	}
	if normalizedVersion == "" {
		result.ErrorMessage = "version is required"
		return result, fmt.Errorf(result.ErrorMessage)
	}

	endpoint := fmt.Sprintf("%s/api/verifier/policy-versions/%s/payload", v.baseURL, url.PathEscape(normalizedVersion))
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint, nil)
	if err != nil {
		result.ErrorMessage = err.Error()
		return result, err
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", v.token))

	resp, err := v.client.Do(req)
	if err != nil {
		result.ErrorMessage = err.Error()
		return result, err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		result.ErrorMessage = fmt.Sprintf("policy payload endpoint returned status %d", resp.StatusCode)
		return result, fmt.Errorf(result.ErrorMessage)
	}

	body := struct {
		Data struct {
			Version       string          `json:"version"`
			PolicyPayload json.RawMessage `json:"policy_payload"`
		} `json:"data"`
	}{}
	if decodeErr := json.NewDecoder(resp.Body).Decode(&body); decodeErr != nil {
		result.ErrorMessage = decodeErr.Error()
		return result, decodeErr
	}

	if strings.TrimSpace(body.Data.Version) == "" {
		result.ErrorMessage = "payload response missing version"
		return result, fmt.Errorf(result.ErrorMessage)
	}
	if normalizePolicyVersion(body.Data.Version) != normalizedVersion {
		result.ErrorMessage = "payload version mismatch"
		return result, fmt.Errorf(result.ErrorMessage)
	}

	checksum, validationErr := validatePolicyPayloadSchema(normalizedVersion, body.Data.PolicyPayload)
	if validationErr != nil {
		result.ErrorMessage = validationErr.Error()
		return result, validationErr
	}

	result.Checksum = checksum
	result.Status = policyValidationStatusValid
	result.ErrorMessage = ""
	result.PayloadLoaded = true

	return result, nil
}

func validatePolicyPayloadSchema(version string, payload json.RawMessage) (string, error) {
	if len(payload) == 0 {
		return "", fmt.Errorf("policy payload is empty")
	}

	compact := bytes.Buffer{}
	if err := json.Compact(&compact, payload); err != nil {
		return "", fmt.Errorf("policy payload must be valid JSON: %w", err)
	}

	root := map[string]any{}
	if err := json.Unmarshal(compact.Bytes(), &root); err != nil {
		return "", fmt.Errorf("policy payload decode failed: %w", err)
	}

	enabledValue, hasEnabled := root["enabled"]
	if !hasEnabled {
		return "", fmt.Errorf("policy payload missing required field enabled")
	}
	if _, ok := enabledValue.(bool); !ok {
		return "", fmt.Errorf("policy payload field enabled must be boolean")
	}

	payloadVersion, ok := root["version"].(string)
	if !ok || strings.TrimSpace(payloadVersion) == "" {
		return "", fmt.Errorf("policy payload missing required field version")
	}
	if normalizePolicyVersion(payloadVersion) != normalizePolicyVersion(version) {
		return "", fmt.Errorf("policy payload version does not match requested version")
	}

	schemaVersion := "v2"
	if value, ok := root["schema_version"].(string); ok && strings.TrimSpace(value) != "" {
		schemaVersion = strings.ToLower(strings.TrimSpace(value))
	}
	if schemaVersion != "v2" && schemaVersion != "v3" {
		return "", fmt.Errorf("policy payload schema_version must be v2 or v3")
	}

	profiles, ok := root["profiles"].(map[string]any)
	if !ok {
		return "", fmt.Errorf("policy payload missing required object profiles")
	}
	genericProfile, ok := profiles["generic"].(map[string]any)
	if !ok {
		return "", fmt.Errorf("policy payload missing required profiles.generic block")
	}
	retry, ok := genericProfile["retry"].(map[string]any)
	if !ok {
		return "", fmt.Errorf("policy payload missing required profiles.generic.retry block")
	}

	for _, key := range requiredRetryFields {
		value, exists := retry[key]
		if !exists {
			return "", fmt.Errorf("policy payload missing retry field %s", key)
		}
		if !isJSONNumber(value) {
			return "", fmt.Errorf("policy payload retry field %s must be numeric", key)
		}
	}

	if schemaVersion == "v3" {
		session, ok := genericProfile["session"].(map[string]any)
		if !ok {
			return "", fmt.Errorf("policy payload missing required profiles.generic.session block")
		}
		for _, key := range []string{
			"max_concurrency",
			"connects_per_minute",
			"reuse_connection_for_retries",
			"retry_jitter_percent",
			"ehlo_profile",
		} {
			if _, exists := session[key]; !exists {
				return "", fmt.Errorf("policy payload missing session field %s", key)
			}
		}

		modes, ok := root["modes"].(map[string]any)
		if !ok {
			return "", fmt.Errorf("policy payload missing required modes object for v3")
		}
		requiredModes := []string{"normal", "cautious", "drain", "quarantine", "degraded_probe"}
		for _, mode := range requiredModes {
			modeConfig, ok := modes[mode].(map[string]any)
			if !ok {
				return "", fmt.Errorf("policy payload missing required modes.%s object", mode)
			}
			for _, field := range []string{
				"probe_enabled",
				"max_concurrency_multiplier",
				"connects_per_minute_multiplier",
			} {
				if _, exists := modeConfig[field]; !exists {
					return "", fmt.Errorf("policy payload missing required modes.%s.%s field", mode, field)
				}
			}
		}
	}

	sum := sha256.Sum256(compact.Bytes())
	return hex.EncodeToString(sum[:]), nil
}

func isJSONNumber(value any) bool {
	switch value.(type) {
	case int, int32, int64, uint, uint32, uint64, float32, float64, json.Number:
		return true
	default:
		return false
	}
}
