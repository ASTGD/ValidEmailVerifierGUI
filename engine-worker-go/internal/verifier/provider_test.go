package verifier

import "testing"

func TestDomainMatchesSuffix(t *testing.T) {
	tests := []struct {
		domain string
		suffix string
		match  bool
	}{
		{"outlook.com", "outlook.com", true},
		{"mail.outlook.com", "outlook.com", true},
		{"mail.outlook.com", "*.outlook.com", true},
		{"outlook.com", ".outlook.com", true},
		{"example.com", "outlook.com", false},
	}

	for _, test := range tests {
		if domainMatchesSuffix(test.domain, test.suffix) != test.match {
			t.Fatalf("domainMatchesSuffix(%q, %q) expected %v", test.domain, test.suffix, test.match)
		}
	}
}

func TestApplyProviderOverrides(t *testing.T) {
	perDomain := 1
	connects := 15
	backoff := 7
	retries := 2

	config := Config{
		PerDomainConcurrency:    3,
		SMTPRateLimitPerMinute:  60,
		BackoffBaseMs:           200,
		RetryableNetworkRetries: 1,
	}

	policy := ProviderPolicy{
		PerDomainConcurrency:    &perDomain,
		ConnectsPerMinute:       &connects,
		TempfailBackoffSeconds:  &backoff,
		RetryableNetworkRetries: &retries,
	}

	updated := applyProviderOverrides(config, policy)

	if updated.PerDomainConcurrency != perDomain {
		t.Fatalf("expected per-domain concurrency %d", perDomain)
	}
	if updated.SMTPRateLimitPerMinute != connects {
		t.Fatalf("expected connects per minute %d", connects)
	}
	if updated.BackoffBaseMs != backoff*1000 {
		t.Fatalf("expected backoff base %d", backoff*1000)
	}
	if updated.RetryableNetworkRetries != retries {
		t.Fatalf("expected retries %d", retries)
	}
}

func TestWithProviderProfile(t *testing.T) {
	prober := NetSMTPProber{}
	updated := withProviderProfile(prober, "gmail")

	typed, ok := updated.(NetSMTPProber)
	if !ok {
		t.Fatalf("expected NetSMTPProber type")
	}
	if typed.ProviderProfile != "gmail" {
		t.Fatalf("expected provider profile gmail, got %q", typed.ProviderProfile)
	}
}

func TestApplyProviderOverridesUsesSessionPolicyAndModeMultipliers(t *testing.T) {
	config := Config{
		PerDomainConcurrency:      3,
		SMTPRateLimitPerMinute:    60,
		ProviderReplyPolicyEngine: DefaultProviderReplyPolicyEngine(),
		ProviderModes: map[string]string{
			"gmail": "cautious",
		},
	}

	policy := ProviderPolicy{
		Name:    "gmail",
		Enabled: true,
	}

	updated := applyProviderOverrides(config, policy)

	if updated.PerDomainConcurrency != 1 {
		t.Fatalf("expected cautious multiplier to reduce gmail concurrency to 1, got %d", updated.PerDomainConcurrency)
	}
	if updated.SMTPRateLimitPerMinute != 12 {
		t.Fatalf("expected cautious multiplier to reduce gmail connects/min to 12, got %d", updated.SMTPRateLimitPerMinute)
	}
	if updated.RetryJitterPercent <= 0 {
		t.Fatalf("expected retry jitter to be configured, got %d", updated.RetryJitterPercent)
	}
	if updated.EHLOProfile == "" {
		t.Fatal("expected EHLO profile to be populated from provider session policy")
	}
}
