package verifier

import (
	"context"
	"strings"
	"sync"

	"golang.org/x/net/idna"
)

type ProviderPolicy struct {
	Name                    string
	Enabled                 bool
	Domains                 []string
	PerDomainConcurrency    *int
	ConnectsPerMinute       *int
	TempfailBackoffSeconds  *int
	RetryableNetworkRetries *int
}

type SMTPCheckerFactory func(Config) SMTPChecker

type ProviderAwareVerifier struct {
	baseConfig       Config
	resolver         MXResolver
	smtpFactory      SMTPCheckerFactory
	providerPolicies []ProviderPolicy
	mu               sync.Mutex
	cache            map[string]Verifier
}

func NewProviderAwareVerifier(
	baseConfig Config,
	resolver MXResolver,
	smtpFactory SMTPCheckerFactory,
	providerPolicies []ProviderPolicy,
) Verifier {
	return &ProviderAwareVerifier{
		baseConfig:       baseConfig,
		resolver:         resolver,
		smtpFactory:      smtpFactory,
		providerPolicies: providerPolicies,
		cache:            map[string]Verifier{},
	}
}

func (p *ProviderAwareVerifier) Verify(ctx context.Context, email string) Result {
	domain := domainFromEmail(email)
	key, policy := p.matchPolicy(domain)

	verifier := p.verifierFor(key, policy)
	return verifier.Verify(ctx, email)
}

func (p *ProviderAwareVerifier) verifierFor(key string, policy *ProviderPolicy) Verifier {
	p.mu.Lock()
	defer p.mu.Unlock()

	if cached, ok := p.cache[key]; ok {
		return cached
	}

	config := p.baseConfig
	if policy != nil {
		config = applyProviderOverrides(config, *policy)
	}

	var smtpChecker SMTPChecker
	if p.smtpFactory != nil {
		smtpChecker = p.smtpFactory(config)
		if policy != nil && policy.Name != "" {
			smtpChecker = withProviderProfile(smtpChecker, policy.Name)
			mode := providerModeFor(config.ProviderModes, policy.Name)
			strategyID := sessionStrategyID(policy.Name, mode)
			smtpChecker = withSessionStrategyContext(smtpChecker, mode, strategyID)
		}
	}

	verifier := NewPipelineVerifier(config, p.resolver, smtpChecker)
	p.cache[key] = verifier

	return verifier
}

func withProviderProfile(checker SMTPChecker, profile string) SMTPChecker {
	switch typed := checker.(type) {
	case NetSMTPProber:
		typed.ProviderProfile = profile
		return typed
	case *NetSMTPProber:
		typed.ProviderProfile = profile
		return typed
	case NetSMTPChecker:
		typed.ProviderProfile = profile
		return typed
	case *NetSMTPChecker:
		typed.ProviderProfile = profile
		return typed
	default:
		return checker
	}
}

func withSessionStrategyContext(checker SMTPChecker, mode string, strategyID string) SMTPChecker {
	switch typed := checker.(type) {
	case NetSMTPProber:
		typed.ProviderMode = mode
		typed.SessionStrategyID = strategyID
		return typed
	case *NetSMTPProber:
		typed.ProviderMode = mode
		typed.SessionStrategyID = strategyID
		return typed
	case NetSMTPChecker:
		typed.ProviderMode = mode
		typed.SessionStrategyID = strategyID
		return typed
	case *NetSMTPChecker:
		typed.ProviderMode = mode
		typed.SessionStrategyID = strategyID
		return typed
	default:
		return checker
	}
}

func sessionStrategyID(provider, mode string) string {
	provider = strings.ToLower(strings.TrimSpace(provider))
	if provider == "" {
		provider = "generic"
	}
	mode = strings.ToLower(strings.TrimSpace(mode))
	if mode == "" {
		mode = "normal"
	}

	return provider + ":" + mode
}

func (p *ProviderAwareVerifier) matchPolicy(domain string) (string, *ProviderPolicy) {
	if domain == "" {
		return "default", nil
	}

	for _, policy := range p.providerPolicies {
		if !policy.Enabled || len(policy.Domains) == 0 {
			continue
		}

		for _, suffix := range policy.Domains {
			if domainMatchesSuffix(domain, suffix) {
				return policy.Name, &policy
			}
		}
	}

	return "default", nil
}

func applyProviderOverrides(config Config, policy ProviderPolicy) Config {
	if policy.PerDomainConcurrency != nil {
		config.PerDomainConcurrency = *policy.PerDomainConcurrency
	}
	if policy.ConnectsPerMinute != nil {
		config.SMTPRateLimitPerMinute = *policy.ConnectsPerMinute
	}
	if policy.TempfailBackoffSeconds != nil {
		config.BackoffBaseMs = *policy.TempfailBackoffSeconds * 1000
	}
	if policy.RetryableNetworkRetries != nil {
		config.RetryableNetworkRetries = *policy.RetryableNetworkRetries
	}

	providerName := strings.ToLower(strings.TrimSpace(policy.Name))
	if providerName != "" && config.ProviderReplyPolicyEngine != nil {
		profile := lookupProviderReplyProfile(config.ProviderReplyPolicyEngine, providerName)
		mode := providerModeFor(config.ProviderModes, providerName)
		modeRule := lookupProviderModeRule(config.ProviderReplyPolicyEngine, mode)
		session := profile.Session
		if session.MaxConcurrency > 0 {
			config.PerDomainConcurrency = applyMultiplier(session.MaxConcurrency, modeRule.MaxConcurrencyMultiplier)
		}
		if session.ConnectsPerMinute > 0 {
			config.SMTPRateLimitPerMinute = applyMultiplier(session.ConnectsPerMinute, modeRule.ConnectsPerMinuteMultiplier)
		}
		config.RetryJitterPercent = session.RetryJitterPercent
		config.ReuseConnectionForRetries = session.ReuseConnectionForRetries
		config.EHLOProfile = strings.TrimSpace(session.EHLOProfile)
		if config.EHLOProfile == "" {
			config.EHLOProfile = "default"
		}
	}

	return config
}

func providerModeFor(modes map[string]string, provider string) string {
	if modes == nil {
		return "normal"
	}

	mode := strings.ToLower(strings.TrimSpace(modes[provider]))
	if mode == "" {
		return "normal"
	}

	return mode
}

func applyMultiplier(base int, multiplier float64) int {
	if base <= 0 {
		return 0
	}
	if multiplier <= 0 {
		return 1
	}

	adjusted := int(float64(base) * multiplier)
	if adjusted < 1 {
		return 1
	}

	return adjusted
}

func domainMatchesSuffix(domain, suffix string) bool {
	domain = strings.ToLower(strings.TrimSpace(domain))
	suffix = strings.ToLower(strings.TrimSpace(suffix))
	suffix = strings.TrimPrefix(suffix, ".")
	suffix = strings.TrimPrefix(suffix, "*.")
	if domain == "" || suffix == "" {
		return false
	}

	return domain == suffix || strings.HasSuffix(domain, "."+suffix)
}

func domainFromEmail(email string) string {
	normalized := strings.TrimSpace(strings.ToLower(email))
	if normalized == "" {
		return ""
	}

	parts := strings.SplitN(normalized, "@", 2)
	if len(parts) != 2 {
		return ""
	}

	domain := strings.TrimSpace(parts[1])
	if domain == "" {
		return ""
	}

	asciiDomain, err := idna.Lookup.ToASCII(domain)
	if err != nil {
		return ""
	}

	asciiDomain = strings.ToLower(strings.TrimSuffix(asciiDomain, "."))
	if asciiDomain == "" {
		return ""
	}

	return asciiDomain
}
