package api

import (
	"encoding/json"
	"testing"
)

func TestPolicyResponseParsing(t *testing.T) {
	payload := `{
  "data": {
    "contract_version": "v1",
    "engine_paused": false,
    "enhanced_mode_enabled": true,
    "provider_policies": [
      {
        "name": "outlook",
        "enabled": true,
        "domains": ["outlook.com", "hotmail.com"],
        "per_domain_concurrency": 1,
        "connects_per_minute": 30,
        "tempfail_backoff_seconds": 8,
        "retryable_network_retries": 2
      }
    ],
    "policies": {
      "standard": {
        "mode": "standard",
        "enabled": true,
        "dns_timeout_ms": 2500,
        "smtp_connect_timeout_ms": 2000,
        "smtp_read_timeout_ms": 2100,
        "max_mx_attempts": 2,
        "max_concurrency_default": 4,
        "per_domain_concurrency": 2,
        "catch_all_detection_enabled": true,
        "global_connects_per_minute": 120,
        "tempfail_backoff_seconds": 3,
        "circuit_breaker_tempfail_rate": 0.25
      },
      "enhanced": {
        "mode": "enhanced",
        "enabled": false,
        "dns_timeout_ms": 2600,
        "smtp_connect_timeout_ms": 2100,
        "smtp_read_timeout_ms": 2200,
        "max_mx_attempts": 3,
        "max_concurrency_default": 2,
        "per_domain_concurrency": 1,
        "catch_all_detection_enabled": false,
        "global_connects_per_minute": null,
        "tempfail_backoff_seconds": null,
        "circuit_breaker_tempfail_rate": null
      }
    }
  }
}`

	var resp PolicyResponse
	if err := json.Unmarshal([]byte(payload), &resp); err != nil {
		t.Fatalf("failed to parse policy response: %v", err)
	}

	if resp.Data.ContractVersion != "v1" {
		t.Fatalf("expected contract_version v1, got %s", resp.Data.ContractVersion)
	}

	standard, ok := resp.Data.Policies["standard"]
	if !ok {
		t.Fatalf("missing standard policy")
	}
	if standard.DNSTimeoutMs != 2500 {
		t.Fatalf("expected dns timeout 2500, got %d", standard.DNSTimeoutMs)
	}
	if standard.GlobalConnectsPerMinute == nil || *standard.GlobalConnectsPerMinute != 120 {
		t.Fatalf("expected global connects per minute 120")
	}

	enhanced, ok := resp.Data.Policies["enhanced"]
	if !ok {
		t.Fatalf("missing enhanced policy")
	}
	if enhanced.Enabled {
		t.Fatalf("expected enhanced disabled")
	}
	if enhanced.GlobalConnectsPerMinute != nil {
		t.Fatalf("expected enhanced global connects to be nil")
	}

	if len(resp.Data.ProviderPolicies) != 1 {
		t.Fatalf("expected provider policies to be parsed")
	}
	if resp.Data.ProviderPolicies[0].Name != "outlook" {
		t.Fatalf("expected provider name outlook")
	}
}

func TestPolicyVersionPayloadResponseParsing(t *testing.T) {
	payload := `{
		"data": {
			"version": "v2.9.0",
			"is_active": true,
			"status": "active",
			"policy_payload": {
				"enabled": true,
				"version": "v2.9.0"
			}
		}
	}`

	var resp PolicyVersionPayloadResponse
	if err := json.Unmarshal([]byte(payload), &resp); err != nil {
		t.Fatalf("failed to parse policy version payload response: %v", err)
	}

	if resp.Data.Version != "v2.9.0" {
		t.Fatalf("expected version v2.9.0, got %s", resp.Data.Version)
	}
	if len(resp.Data.PolicyPayload) == 0 {
		t.Fatalf("expected policy payload content")
	}
}
