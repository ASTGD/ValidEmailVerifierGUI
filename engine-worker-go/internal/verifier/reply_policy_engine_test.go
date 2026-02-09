package verifier

import "testing"

func TestDefaultProviderReplyPolicyEngineIncludesGenericProfile(t *testing.T) {
	engine := DefaultProviderReplyPolicyEngine()
	if engine == nil {
		t.Fatal("expected default engine")
	}

	if _, ok := engine.Profiles["generic"]; !ok {
		t.Fatal("expected generic profile to exist")
	}
}

func TestParseProviderReplyPolicyEngineJSONAppliesDefaults(t *testing.T) {
	engine, err := ParseProviderReplyPolicyEngineJSON(`{
		"enabled": true,
		"profiles": {
			"gmail": {
				"name": "gmail",
				"retry": {
					"default_seconds": 120
				}
			}
		}
	}`)
	if err != nil {
		t.Fatalf("expected json to parse, got %v", err)
	}

	gmail, ok := engine.Profiles["gmail"]
	if !ok {
		t.Fatal("expected gmail profile")
	}
	if gmail.Retry.DefaultSeconds != 120 {
		t.Fatalf("expected gmail default retry 120, got %d", gmail.Retry.DefaultSeconds)
	}

	if len(gmail.EnhancedRules) == 0 {
		t.Fatal("expected gmail enhanced rules fallback from defaults")
	}
}
