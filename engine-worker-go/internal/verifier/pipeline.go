package verifier

import (
	"context"
	"errors"
	"fmt"
	"net"
	"sort"
	"strings"
	"time"

	"golang.org/x/net/idna"
)

type PipelineVerifier struct {
	resolver          MXResolver
	smtpChecker       SMTPChecker
	limiter           *DomainLimiter
	rateLimiter       *RateLimiter
	config            Config
	disposableDomains map[string]struct{}
	roleAccounts      map[string]struct{}
	domainTypos       map[string]string
}

func NewPipelineVerifier(config Config, resolver MXResolver, smtpChecker SMTPChecker) *PipelineVerifier {
	limiter := NewDomainLimiter(config.PerDomainConcurrency)
	rateLimiter := NewRateLimiter(config.SMTPRateLimitPerMinute)

	if smtpChecker == nil {
		smtpChecker = NetSMTPChecker{
			Dialer:         nil,
			ConnectTimeout: time.Duration(config.SMTPConnectTimeout) * time.Millisecond,
			ReadTimeout:    time.Duration(config.SMTPReadTimeout) * time.Millisecond,
			EhloTimeout:    time.Duration(config.SMTPEhloTimeout) * time.Millisecond,
			HeloName:       config.HeloName,
			RateLimiter:    rateLimiter,
		}
	}

	disposableDomains := config.DisposableDomains
	if disposableDomains == nil {
		disposableDomains = map[string]struct{}{}
	}

	roleAccounts := config.RoleAccounts
	if roleAccounts == nil {
		roleAccounts = map[string]struct{}{}
	}

	domainTypos := config.DomainTypos
	if domainTypos == nil {
		domainTypos = map[string]string{}
	}

	return &PipelineVerifier{
		resolver:          resolver,
		smtpChecker:       smtpChecker,
		limiter:           limiter,
		rateLimiter:       rateLimiter,
		config:            config,
		disposableDomains: disposableDomains,
		roleAccounts:      roleAccounts,
		domainTypos:       domainTypos,
	}
}

func (p *PipelineVerifier) Verify(ctx context.Context, email string) Result {
	parsed, parseResult := parseEmail(email)
	if parseResult.Reason != "" {
		return parseResult
	}

	if suggestion, ok := p.domainTypos[parsed.domain]; ok {
		return Result{Category: CategoryRisky, Reason: fmt.Sprintf("domain_typo_suspected:suggest=%s", suggestion)}
	}

	if p.isDisposableDomain(parsed.domain) {
		return Result{Category: CategoryRisky, Reason: "disposable_domain"}
	}

	behavior := strings.ToLower(strings.TrimSpace(p.config.RoleAccountsBehavior))
	if behavior == "" || behavior == "risky" {
		if _, ok := p.roleAccounts[parsed.local]; ok {
			return Result{Category: CategoryRisky, Reason: "role_account"}
		}
	}

	emailAddress := fmt.Sprintf("%s@%s", parsed.local, parsed.domain)

	mxRecords, dnsResult := p.lookupMX(ctx, parsed.domain)
	if dnsResult.Reason != "" {
		return dnsResult
	}
	if len(mxRecords) == 0 {
		return Result{Category: CategoryInvalid, Reason: "mx_missing"}
	}

	sort.Slice(mxRecords, func(i, j int) bool {
		return mxRecords[i].Pref < mxRecords[j].Pref
	})

	return p.checkSMTP(ctx, parsed.domain, emailAddress, mxRecords)
}

func (p *PipelineVerifier) lookupMX(ctx context.Context, domain string) ([]*net.MX, Result) {
	if p.resolver == nil {
		p.resolver = NetMXResolver{}
	}

	timeout := time.Duration(p.config.DNSTimeout) * time.Millisecond
	if timeout <= 0 {
		timeout = 2 * time.Second
	}

	retries := maxInt(0, p.config.RetryableNetworkRetries)

	var lastErr error
	for attempt := 0; attempt <= retries; attempt++ {
		lookupCtx, cancel := context.WithTimeout(ctx, timeout)
		records, err := p.resolver.LookupMX(lookupCtx, domain)
		cancel()

		if err == nil {
			return records, Result{}
		}

		lastErr = err
		if !isRetryableDNSError(err) || attempt == retries {
			return nil, classifyDNSError(err)
		}

		backoffSleep(ctx, p.config.BackoffBaseMs, attempt, 0)
	}

	if lastErr != nil {
		return nil, classifyDNSError(lastErr)
	}

	return nil, Result{}
}

func (p *PipelineVerifier) checkSMTP(ctx context.Context, domain, email string, mxRecords []*net.MX) Result {
	maxAttempts := p.config.MaxMXAttempts
	if maxAttempts <= 0 {
		maxAttempts = 2
	}

	var best Result

	for i, mx := range mxRecords {
		if i >= maxAttempts {
			break
		}

		host := strings.TrimSuffix(mx.Host, ".")
		attemptResult := p.checkSMTPHost(ctx, domain, host, email)

		if attemptResult.Category == CategoryValid {
			return attemptResult
		}

		if best.Category == "" || rankCategory(attemptResult.Category) > rankCategory(best.Category) {
			best = attemptResult
		}
	}

	if best.Category == "" {
		return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
	}

	return best
}

func (p *PipelineVerifier) checkSMTPHost(ctx context.Context, domain, host, email string) Result {
	limiterRelease, err := p.limiter.Acquire(ctx, domain)
	if err != nil {
		return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
	}
	defer limiterRelease()

	retries := maxInt(0, p.config.RetryableNetworkRetries)

	var last Result

	for attempt := 0; attempt <= retries; attempt++ {
		result := p.smtpChecker.Check(ctx, host, email)

		if result.Category == CategoryValid {
			return result
		}

		last = result

		if !isRetryableSMTPResult(result) || attempt == retries {
			return result
		}

		backoffSleep(ctx, p.config.BackoffBaseMs, attempt, result.RetryAfterSecond)
	}

	if last.Category == "" {
		return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
	}

	return last
}

type parsedEmail struct {
	local  string
	domain string
}

func parseEmail(email string) (parsedEmail, Result) {
	normalized := strings.TrimSpace(strings.ToLower(email))
	if normalized == "" || !strings.Contains(normalized, "@") {
		return parsedEmail{}, Result{Category: CategoryInvalid, Reason: "syntax"}
	}

	parts := strings.SplitN(normalized, "@", 2)
	if len(parts) != 2 {
		return parsedEmail{}, Result{Category: CategoryInvalid, Reason: "syntax"}
	}

	local := strings.TrimSpace(parts[0])
	domain := strings.TrimSpace(parts[1])
	if local == "" || domain == "" {
		return parsedEmail{}, Result{Category: CategoryInvalid, Reason: "syntax"}
	}

	asciiDomain, err := idna.Lookup.ToASCII(domain)
	if err != nil {
		return parsedEmail{}, Result{Category: CategoryInvalid, Reason: "syntax"}
	}

	asciiDomain = strings.ToLower(strings.TrimSuffix(asciiDomain, "."))
	if asciiDomain == "" {
		return parsedEmail{}, Result{Category: CategoryInvalid, Reason: "syntax"}
	}

	return parsedEmail{
		local:  local,
		domain: asciiDomain,
	}, Result{}
}

func (p *PipelineVerifier) isDisposableDomain(domain string) bool {
	if len(p.disposableDomains) == 0 || domain == "" {
		return false
	}

	if _, ok := p.disposableDomains[domain]; ok {
		return true
	}

	for {
		dot := strings.Index(domain, ".")
		if dot == -1 || dot+1 >= len(domain) {
			return false
		}
		domain = domain[dot+1:]
		if _, ok := p.disposableDomains[domain]; ok {
			return true
		}
	}
}

func isRetryableDNSError(err error) bool {
	var netErr net.Error
	if errors.As(err, &netErr) {
		return netErr.Timeout()
	}

	if strings.Contains(strings.ToLower(err.Error()), "servfail") {
		return true
	}

	return false
}

func isRetryableSMTPResult(result Result) bool {
	switch result.DecisionClass {
	case DecisionPolicyBlocked, DecisionUndeliverable:
		return false
	case DecisionRetryable:
		return true
	}

	switch result.Reason {
	case "smtp_timeout", "smtp_connect_timeout", "smtp_tempfail":
		return true
	default:
		return false
	}
}

func backoffSleep(ctx context.Context, baseMs int, attempt int, retryAfterSeconds int) {
	if baseMs <= 0 {
		baseMs = 200
	}

	delay := time.Duration(baseMs*(attempt+1)) * time.Millisecond
	if retryAfterSeconds > 0 {
		retryDelay := time.Duration(retryAfterSeconds) * time.Second
		if retryDelay > delay {
			delay = retryDelay
		}
	}
	timer := time.NewTimer(delay)
	defer timer.Stop()

	select {
	case <-ctx.Done():
	case <-timer.C:
	}
}

func rankCategory(category string) int {
	switch category {
	case CategoryValid:
		return 3
	case CategoryRisky:
		return 2
	case CategoryInvalid:
		return 1
	default:
		return 0
	}
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}
	return b
}
