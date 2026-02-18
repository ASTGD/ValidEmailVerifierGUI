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

func TestValidatePolicyPayloadSchemaAcceptsV4RuleMetadata(t *testing.T) {
	payload := map[string]any{
		"schema_version": "v4",
		"enabled":        true,
		"version":        "v4.1.0",
		"modes": map[string]any{
			"normal": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     1.0,
				"connects_per_minute_multiplier": 1.0,
			},
			"cautious": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     0.7,
				"connects_per_minute_multiplier": 0.6,
			},
			"drain": map[string]any{
				"probe_enabled":                  false,
				"max_concurrency_multiplier":     0.0,
				"connects_per_minute_multiplier": 0.0,
			},
			"quarantine": map[string]any{
				"probe_enabled":                  false,
				"max_concurrency_multiplier":     0.0,
				"connects_per_minute_multiplier": 0.0,
			},
			"degraded_probe": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     0.5,
				"connects_per_minute_multiplier": 0.5,
			},
		},
		"profiles": map[string]any{
			"generic": map[string]any{
				"enhanced_rules": []any{
					map[string]any{
						"rule_id":           "generic-enhanced-511",
						"enhanced_prefixes": []string{"5.1.1"},
						"decision_class":    "undeliverable",
						"category":          "invalid",
						"reason":            "rcpt_rejected",
						"reason_code":       "mailbox_not_found",
						"rule_tag":          "mailbox_not_found",
						"confidence_hint":   "high",
						"provider_scope":    "generic",
					},
				},
				"retry": map[string]any{
					"default_seconds":        60,
					"tempfail_seconds":       90,
					"greylist_seconds":       180,
					"policy_blocked_seconds": 300,
					"unknown_seconds":        75,
				},
				"session": map[string]any{
					"max_concurrency":              2,
					"connects_per_minute":          30,
					"reuse_connection_for_retries": true,
					"retry_jitter_percent":         15,
					"ehlo_profile":                 "default",
				},
			},
		},
	}

	raw, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("failed to marshal payload: %v", err)
	}

	if _, validationErr := validatePolicyPayloadSchema("v4.1.0", raw); validationErr != nil {
		t.Fatalf("expected v4 payload to validate, got %v", validationErr)
	}
}

func TestValidatePolicyPayloadSchemaRejectsUnsupportedV4RuleTag(t *testing.T) {
	payload := map[string]any{
		"schema_version": "v4",
		"enabled":        true,
		"version":        "v4.1.1",
		"modes": map[string]any{
			"normal": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     1.0,
				"connects_per_minute_multiplier": 1.0,
			},
			"cautious": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     0.7,
				"connects_per_minute_multiplier": 0.6,
			},
			"drain": map[string]any{
				"probe_enabled":                  false,
				"max_concurrency_multiplier":     0.0,
				"connects_per_minute_multiplier": 0.0,
			},
			"quarantine": map[string]any{
				"probe_enabled":                  false,
				"max_concurrency_multiplier":     0.0,
				"connects_per_minute_multiplier": 0.0,
			},
			"degraded_probe": map[string]any{
				"probe_enabled":                  true,
				"max_concurrency_multiplier":     0.5,
				"connects_per_minute_multiplier": 0.5,
			},
		},
		"profiles": map[string]any{
			"generic": map[string]any{
				"enhanced_rules": []any{
					map[string]any{
						"rule_id":           "generic-enhanced-511",
						"enhanced_prefixes": []string{"5.1.1"},
						"decision_class":    "undeliverable",
						"category":          "invalid",
						"reason":            "rcpt_rejected",
						"reason_code":       "mailbox_not_found",
						"rule_tag":          "custom_invalid_tag",
						"confidence_hint":   "high",
						"provider_scope":    "generic",
					},
				},
				"retry": map[string]any{
					"default_seconds":        60,
					"tempfail_seconds":       90,
					"greylist_seconds":       180,
					"policy_blocked_seconds": 300,
					"unknown_seconds":        75,
				},
				"session": map[string]any{
					"max_concurrency":              2,
					"connects_per_minute":          30,
					"reuse_connection_for_retries": true,
					"retry_jitter_percent":         15,
					"ehlo_profile":                 "default",
				},
			},
		},
	}

	raw, err := json.Marshal(payload)
	if err != nil {
		t.Fatalf("failed to marshal payload: %v", err)
	}

	if _, validationErr := validatePolicyPayloadSchema("v4.1.1", raw); validationErr == nil {
		t.Fatal("expected v4 payload with unsupported rule_tag to fail validation")
	}
}
