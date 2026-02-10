package verifier

import "context"

const (
	CategoryValid   = "valid"
	CategoryInvalid = "invalid"
	CategoryRisky   = "risky"

	DecisionDeliverable   = "deliverable"
	DecisionUndeliverable = "undeliverable"
	DecisionRetryable     = "retryable"
	DecisionPolicyBlocked = "policy_blocked"
	DecisionUnknown       = "unknown"
)

type Result struct {
	Category           string
	Reason             string
	DecisionClass      string
	ReasonCode         string
	SMTPCode           int
	EnhancedCode       string
	ProviderProfile    string
	RetryAfterSecond   int
	PolicyVersion      string
	MatchedRuleID      string
	DecisionConfidence string
	RetryStrategy      string
	Evidence           *ReplyEvidence
}

type Verifier interface {
	Verify(ctx context.Context, email string) Result
}

type Config struct {
	DNSTimeout                  int
	SMTPConnectTimeout          int
	SMTPReadTimeout             int
	SMTPEhloTimeout             int
	MaxMXAttempts               int
	RetryableNetworkRetries     int
	BackoffBaseMs               int
	HeloName                    string
	MailFromAddress             string
	PerDomainConcurrency        int
	SMTPRateLimitPerMinute      int
	DisposableDomains           map[string]struct{}
	RoleAccounts                map[string]struct{}
	RoleAccountsBehavior        string
	CatchAllDetectionEnabled    bool
	DomainTypos                 map[string]string
	ProviderPolicyEngineEnabled bool
	AdaptiveRetryEnabled        bool
	ProviderReplyPolicyEngine   *ProviderReplyPolicyEngine
}
