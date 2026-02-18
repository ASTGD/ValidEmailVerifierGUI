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
	ReasonTag          string
	MXHost             string
	AttemptNumber      int
	AttemptRoute       string
	AttemptChain       []AttemptEvidence
	EvidenceStrength   string
	SMTPCode           int
	EnhancedCode       string
	ProviderProfile    string
	ProviderMode       string
	SessionStrategyID  string
	RetryAfterSecond   int
	PolicyVersion      string
	MatchedRuleID      string
	DecisionConfidence string
	RetryStrategy      string
	Evidence           *ReplyEvidence
}

type AttemptEvidence struct {
	AttemptNumber    int    `json:"attempt_number,omitempty"`
	MXHost           string `json:"mx_host,omitempty"`
	AttemptRoute     string `json:"attempt_route,omitempty"`
	DecisionClass    string `json:"decision_class,omitempty"`
	ReasonCode       string `json:"reason_code,omitempty"`
	ReasonTag        string `json:"reason_tag,omitempty"`
	RetryStrategy    string `json:"retry_strategy,omitempty"`
	SMTPCode         int    `json:"smtp_code,omitempty"`
	EnhancedCode     string `json:"enhanced_code,omitempty"`
	ProviderProfile  string `json:"provider_profile,omitempty"`
	ConfidenceHint   string `json:"confidence_hint,omitempty"`
	EvidenceStrength string `json:"evidence_strength,omitempty"`
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
	RetryJitterPercent          int
	ReuseConnectionForRetries   bool
	EHLOProfile                 string
	ProviderModes               map[string]string
	DisposableDomains           map[string]struct{}
	RoleAccounts                map[string]struct{}
	RoleAccountsBehavior        string
	CatchAllDetectionEnabled    bool
	DomainTypos                 map[string]string
	ProviderPolicyEngineEnabled bool
	AdaptiveRetryEnabled        bool
	ProviderReplyPolicyEngine   *ProviderReplyPolicyEngine
}
