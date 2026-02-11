package main

import (
	"encoding/json"
	"testing"
)

func TestValidatePolicyPayloadSchemaAcceptsRequiredFields(t *testing.T) {
	payload := map[string]any{
		"enabled": true,
		"version": "v2.5.0",
		"profiles": map[string]any{
			"generic": map[string]any{
				"retry": map[string]any{
					"default_seconds":        60,
					"tempfail_seconds":       90,
					"greylist_seconds":       180,
					"policy_blocked_seconds": 300,
					"unknown_seconds":        75,
				},
			},
		},
	}

	raw, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("failed to marshal payload: %v", err)
	}

	checksum, validationErr := validatePolicyPayloadSchema("v2.5.0", raw)
	if validationErr != nil {
		t.Fatalf("expected payload to validate, got %v", validationErr)
	}
	if checksum == "" {
		t.Fatal("expected checksum to be populated")
	}
}

func TestValidatePolicyPayloadSchemaRejectsMissingRetryFields(t *testing.T) {
	payload := map[string]any{
		"enabled": true,
		"version": "v2.6.0",
		"profiles": map[string]any{
			"generic": map[string]any{
				"retry": map[string]any{
					"default_seconds": 60,
				},
			},
		},
	}

	raw, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("failed to marshal payload: %v", err)
	}

	if _, validationErr := validatePolicyPayloadSchema("v2.6.0", raw); validationErr == nil {
		t.Fatal("expected payload validation to fail when retry fields are missing")
	}
}

func TestValidatePolicyPayloadSchemaRejectsVersionMismatch(t *testing.T) {
	payload := map[string]any{
		"enabled": true,
		"version": "v3.0.0",
		"profiles": map[string]any{
			"generic": map[string]any{
				"retry": map[string]any{
					"default_seconds":        60,
					"tempfail_seconds":       90,
					"greylist_seconds":       180,
					"policy_blocked_seconds": 300,
					"unknown_seconds":        75,
				},
			},
		},
	}

	raw, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("failed to marshal payload: %v", err)
	}

	if _, validationErr := validatePolicyPayloadSchema("v2.9.9", raw); validationErr == nil {
		t.Fatal("expected payload validation to fail on version mismatch")
	}
}
