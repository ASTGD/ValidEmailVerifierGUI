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
	}

	verifier := NewPipelineVerifier(config, p.resolver, smtpChecker)
	p.cache[key] = verifier

	return verifier
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

	return config
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
