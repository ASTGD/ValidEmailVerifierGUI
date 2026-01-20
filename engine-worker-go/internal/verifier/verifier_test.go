package verifier

import (
	"context"
	"errors"
	"net"
	"testing"
)

type fakeResolver struct {
	records map[string][]*net.MX
	errs    map[string]error
}

func (f *fakeResolver) LookupMX(ctx context.Context, domain string) ([]*net.MX, error) {
	if err, ok := f.errs[domain]; ok {
		return nil, err
	}
	if records, ok := f.records[domain]; ok {
		return records, nil
	}
	return []*net.MX{}, nil
}

type fakeSMTP struct {
	results map[string]Result
}

func (f *fakeSMTP) Check(ctx context.Context, host string) Result {
	if result, ok := f.results[host]; ok {
		return result
	}
	return Result{Category: CategoryRisky, Reason: "smtp_timeout"}
}

type timeoutError struct{}

func (timeoutError) Error() string   { return "timeout" }
func (timeoutError) Timeout() bool   { return true }
func (timeoutError) Temporary() bool { return true }

func baseConfig(maxMX int) Config {
	return Config{
		DNSTimeout:              1,
		SMTPConnectTimeout:      1,
		SMTPReadTimeout:         1,
		SMTPEhloTimeout:         1,
		MaxMXAttempts:           maxMX,
		RetryableNetworkRetries: 0,
		BackoffBaseMs:           1,
		HeloName:                "worker.test",
		PerDomainConcurrency:    0,
		SMTPRateLimitPerMinute:  0,
		DisposableDomains:       map[string]struct{}{},
		RoleAccounts:            map[string]struct{}{},
		RoleAccountsBehavior:    "risky",
		DomainTypos:             map[string]string{},
	}
}

func newVerifier(resolver MXResolver, smtp SMTPChecker, maxMX int) *PipelineVerifier {
	return NewPipelineVerifier(baseConfig(maxMX), resolver, smtp)
}

func TestPipelineSyntaxInvalid(t *testing.T) {
	v := newVerifier(&fakeResolver{}, &fakeSMTP{}, 1)
	res := v.Verify(context.Background(), "bad-email")
	if res.Category != CategoryInvalid || res.Reason != "syntax" {
		t.Fatalf("expected syntax invalid, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineMXMissing(t *testing.T) {
	resolver := &fakeResolver{records: map[string][]*net.MX{}}
	v := newVerifier(resolver, &fakeSMTP{}, 1)
	res := v.Verify(context.Background(), "test@nomx.local")
	if res.Category != CategoryInvalid || res.Reason != "mx_missing" {
		t.Fatalf("expected mx_missing invalid, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineDNSTimeout(t *testing.T) {
	resolver := &fakeResolver{errs: map[string]error{"timeout.local": timeoutError{}}}
	v := newVerifier(resolver, &fakeSMTP{}, 1)
	res := v.Verify(context.Background(), "user@timeout.local")
	if res.Category != CategoryRisky || res.Reason != "dns_timeout" {
		t.Fatalf("expected dns_timeout risky, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineDNSServfail(t *testing.T) {
	resolver := &fakeResolver{errs: map[string]error{"servfail.local": errors.New("SERVFAIL")}}
	v := newVerifier(resolver, &fakeSMTP{}, 1)
	res := v.Verify(context.Background(), "user@servfail.local")
	if res.Category != CategoryRisky || res.Reason != "dns_servfail" {
		t.Fatalf("expected dns_servfail risky, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineSMTPConnectOk(t *testing.T) {
	resolver := &fakeResolver{records: map[string][]*net.MX{
		"good.local": {{Host: "mx.good.local", Pref: 10}},
	}}
	smtp := &fakeSMTP{results: map[string]Result{
		"mx.good.local": {Category: CategoryValid, Reason: "smtp_connect_ok"},
	}}
	v := newVerifier(resolver, smtp, 1)
	res := v.Verify(context.Background(), "user@good.local")
	if res.Category != CategoryValid || res.Reason != "smtp_connect_ok" {
		t.Fatalf("expected smtp_connect_ok valid, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineSMTPFallbackToSecondMX(t *testing.T) {
	resolver := &fakeResolver{records: map[string][]*net.MX{
		"multi.local": {
			{Host: "mx1.multi.local", Pref: 10},
			{Host: "mx2.multi.local", Pref: 20},
		},
	}}
	smtp := &fakeSMTP{results: map[string]Result{
		"mx1.multi.local": {Category: CategoryRisky, Reason: "smtp_timeout"},
		"mx2.multi.local": {Category: CategoryValid, Reason: "smtp_connect_ok"},
	}}
	v := newVerifier(resolver, smtp, 2)
	res := v.Verify(context.Background(), "user@multi.local")
	if res.Category != CategoryValid {
		t.Fatalf("expected valid via second MX, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineMaxAttemptsStopsEarly(t *testing.T) {
	resolver := &fakeResolver{records: map[string][]*net.MX{
		"limit.local": {
			{Host: "mx1.limit.local", Pref: 10},
			{Host: "mx2.limit.local", Pref: 20},
		},
	}}
	smtp := &fakeSMTP{results: map[string]Result{
		"mx1.limit.local": {Category: CategoryRisky, Reason: "smtp_timeout"},
		"mx2.limit.local": {Category: CategoryValid, Reason: "smtp_connect_ok"},
	}}
	v := newVerifier(resolver, smtp, 1)
	res := v.Verify(context.Background(), "user@limit.local")
	if res.Category != CategoryRisky || res.Reason != "smtp_timeout" {
		t.Fatalf("expected risky after first MX, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineDomainTypoSuspected(t *testing.T) {
	config := baseConfig(1)
	config.DomainTypos["gmail.con"] = "gmail.com"

	v := NewPipelineVerifier(config, &fakeResolver{}, &fakeSMTP{})
	res := v.Verify(context.Background(), "user@gmail.con")

	if res.Category != CategoryRisky || res.Reason != "domain_typo_suspected:suggest=gmail.com" {
		t.Fatalf("expected domain typo risky, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineDisposableDomain(t *testing.T) {
	config := baseConfig(1)
	config.DisposableDomains["mailinator.com"] = struct{}{}

	resolver := &fakeResolver{errs: map[string]error{"mailinator.com": errors.New("SERVFAIL")}}
	v := NewPipelineVerifier(config, resolver, &fakeSMTP{})
	res := v.Verify(context.Background(), "user@mailinator.com")

	if res.Category != CategoryRisky || res.Reason != "disposable_domain" {
		t.Fatalf("expected disposable domain risky, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineRoleAccount(t *testing.T) {
	config := baseConfig(1)
	config.RoleAccounts["admin"] = struct{}{}

	resolver := &fakeResolver{errs: map[string]error{"example.local": errors.New("SERVFAIL")}}
	v := NewPipelineVerifier(config, resolver, &fakeSMTP{})
	res := v.Verify(context.Background(), "admin@example.local")

	if res.Category != CategoryRisky || res.Reason != "role_account" {
		t.Fatalf("expected role account risky, got %s/%s", res.Category, res.Reason)
	}
}

func TestPipelineIDNNormalization(t *testing.T) {
	config := baseConfig(1)
	domain := "xn--bcher-kva.de"
	resolver := &fakeResolver{records: map[string][]*net.MX{
		domain: {{Host: "mx.idn.local", Pref: 10}},
	}}
	smtp := &fakeSMTP{results: map[string]Result{
		"mx.idn.local": {Category: CategoryValid, Reason: "smtp_connect_ok"},
	}}

	v := NewPipelineVerifier(config, resolver, smtp)
	res := v.Verify(context.Background(), "user@b\u00fccher.de")

	if res.Category != CategoryValid || res.Reason != "smtp_connect_ok" {
		t.Fatalf("expected idn normalization to resolve, got %s/%s", res.Category, res.Reason)
	}
}
