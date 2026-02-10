package verifier

import (
	"encoding/json"
	"fmt"
	"strings"
)

type ReplyEvidence struct {
	ReasonCode      string `json:"reason_code,omitempty"`
	SMTPCode        int    `json:"smtp_code,omitempty"`
	EnhancedCode    string `json:"enhanced_code,omitempty"`
	ProviderProfile string `json:"provider_profile,omitempty"`
	DecisionClass   string `json:"decision_class,omitempty"`
}

type ProviderReplyPolicyEngine struct {
	Enabled  bool                            `json:"enabled"`
	Version  string                          `json:"version,omitempty"`
	Profiles map[string]ProviderReplyProfile `json:"profiles"`
}

type ProviderReplyProfile struct {
	Name          string              `json:"name,omitempty"`
	EnhancedRules []ProviderReplyRule `json:"enhanced_rules,omitempty"`
	SMTPCodeRules []ProviderReplyRule `json:"smtp_code_rules,omitempty"`
	MessageRules  []ProviderReplyRule `json:"message_rules,omitempty"`
	Retry         ProviderRetryPolicy `json:"retry"`
}

type ProviderReplyRule struct {
	RuleID           string   `json:"rule_id,omitempty"`
	EnhancedPrefixes []string `json:"enhanced_prefixes,omitempty"`
	SMTPCodes        []int    `json:"smtp_codes,omitempty"`
	MessageContains  []string `json:"message_contains,omitempty"`
	DecisionClass    string   `json:"decision_class"`
	Category         string   `json:"category"`
	Reason           string   `json:"reason"`
	ReasonCode       string   `json:"reason_code"`
	RetryAfterSecond int      `json:"retry_after_seconds,omitempty"`
}

type ProviderRetryPolicy struct {
	DefaultSeconds      int `json:"default_seconds,omitempty"`
	TempfailSeconds     int `json:"tempfail_seconds,omitempty"`
	GreylistSeconds     int `json:"greylist_seconds,omitempty"`
	PolicyBlockedSecond int `json:"policy_blocked_seconds,omitempty"`
	UnknownSeconds      int `json:"unknown_seconds,omitempty"`
}

func DefaultProviderReplyPolicyEngine() *ProviderReplyPolicyEngine {
	engine := ProviderReplyPolicyEngine{
		Enabled:  true,
		Version:  "v2",
		Profiles: defaultProviderReplyProfiles(),
	}

	normalized := normalizeProviderReplyPolicyEngine(engine)
	return &normalized
}

func ParseProviderReplyPolicyEngineJSON(raw string) (*ProviderReplyPolicyEngine, error) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return nil, fmt.Errorf("policy json is empty")
	}

	var parsed ProviderReplyPolicyEngine
	if err := json.Unmarshal([]byte(raw), &parsed); err != nil {
		return nil, fmt.Errorf("invalid provider reply policy json: %w", err)
	}

	normalized := normalizeProviderReplyPolicyEngine(parsed)
	return &normalized, nil
}

func normalizeProviderReplyPolicyEngine(engine ProviderReplyPolicyEngine) ProviderReplyPolicyEngine {
	defaults := ProviderReplyPolicyEngine{Version: "v2", Profiles: defaultProviderReplyProfiles()}
	genericDefaults := defaults.Profiles["generic"]
	out := engine
	if out.Profiles == nil {
		out.Profiles = map[string]ProviderReplyProfile{}
	}

	for key, profile := range defaults.Profiles {
		existing, ok := out.Profiles[key]
		if !ok {
			out.Profiles[key] = profile
			continue
		}

		if len(existing.EnhancedRules) == 0 {
			existing.EnhancedRules = profile.EnhancedRules
			if len(existing.EnhancedRules) == 0 {
				existing.EnhancedRules = genericDefaults.EnhancedRules
			}
		}
		if len(existing.SMTPCodeRules) == 0 {
			existing.SMTPCodeRules = profile.SMTPCodeRules
			if len(existing.SMTPCodeRules) == 0 {
				existing.SMTPCodeRules = genericDefaults.SMTPCodeRules
			}
		}
		if len(existing.MessageRules) == 0 {
			existing.MessageRules = profile.MessageRules
			if len(existing.MessageRules) == 0 {
				existing.MessageRules = genericDefaults.MessageRules
			}
		}
		existing.Retry = normalizeRetryProfile(existing.Retry, profile.Retry)
		if strings.TrimSpace(existing.Name) == "" {
			existing.Name = profile.Name
		}

		out.Profiles[key] = existing
	}

	if _, ok := out.Profiles["generic"]; !ok {
		out.Profiles["generic"] = defaults.Profiles["generic"]
	}

	if strings.TrimSpace(out.Version) == "" {
		out.Version = defaults.Version
	}

	return out
}

func defaultProviderReplyProfiles() map[string]ProviderReplyProfile {
	return map[string]ProviderReplyProfile{
		"generic": {
			Name: "generic",
			EnhancedRules: []ProviderReplyRule{
				{
					RuleID:           "generic-enhanced-511-mailbox-not-found",
					EnhancedPrefixes: []string{"5.1.1", "5.1.10", "5.2."},
					DecisionClass:    DecisionUndeliverable,
					Category:         CategoryInvalid,
					Reason:           "rcpt_rejected",
					ReasonCode:       "mailbox_not_found",
				},
				{
					RuleID:           "generic-enhanced-57-policy-blocked",
					EnhancedPrefixes: []string{"5.7."},
					DecisionClass:    DecisionPolicyBlocked,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_policy_blocked",
				},
				{
					RuleID:           "generic-enhanced-42-44-retry",
					EnhancedPrefixes: []string{"4.2.", "4.4."},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
				},
			},
			SMTPCodeRules: []ProviderReplyRule{
				{
					RuleID:        "generic-smtp-421-452-retry",
					SMTPCodes:     []int{421, 450, 451, 452},
					DecisionClass: DecisionRetryable,
					Category:      CategoryRisky,
					Reason:        "smtp_tempfail",
					ReasonCode:    "smtp_tempfail",
				},
				{
					RuleID:        "generic-smtp-550-553-undeliverable",
					SMTPCodes:     []int{550, 551, 553},
					DecisionClass: DecisionUndeliverable,
					Category:      CategoryInvalid,
					Reason:        "rcpt_rejected",
					ReasonCode:    "rcpt_rejected",
				},
			},
			MessageRules: []ProviderReplyRule{
				{
					RuleID:           "generic-msg-greylist-retry",
					MessageContains:  []string{"greylist", "try again later", "temporarily deferred"},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
					RetryAfterSecond: 180,
				},
				{
					RuleID:          "generic-msg-policy-blocked",
					MessageContains: []string{"policy", "blocked", "denied", "forbidden", "authentication", "auth"},
					DecisionClass:   DecisionPolicyBlocked,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_policy_blocked",
				},
				{
					RuleID:          "generic-msg-mailbox-not-found",
					MessageContains: []string{"user unknown", "no such user", "mailbox unavailable", "recipient address rejected"},
					DecisionClass:   DecisionUndeliverable,
					Category:        CategoryInvalid,
					Reason:          "rcpt_rejected",
					ReasonCode:      "mailbox_not_found",
				},
			},
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      60,
				TempfailSeconds:     90,
				GreylistSeconds:     180,
				PolicyBlockedSecond: 300,
				UnknownSeconds:      75,
			},
		},
		"gmail": {
			Name: "gmail",
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      90,
				TempfailSeconds:     120,
				GreylistSeconds:     240,
				PolicyBlockedSecond: 480,
				UnknownSeconds:      120,
			},
		},
		"microsoft": {
			Name: "microsoft",
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      90,
				TempfailSeconds:     150,
				GreylistSeconds:     300,
				PolicyBlockedSecond: 600,
				UnknownSeconds:      120,
			},
		},
		"yahoo": {
			Name: "yahoo",
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      75,
				TempfailSeconds:     120,
				GreylistSeconds:     240,
				PolicyBlockedSecond: 480,
				UnknownSeconds:      100,
			},
		},
	}
}

func normalizeRetryProfile(value ProviderRetryPolicy, fallback ProviderRetryPolicy) ProviderRetryPolicy {
	if value.DefaultSeconds <= 0 {
		value.DefaultSeconds = fallback.DefaultSeconds
	}
	if value.TempfailSeconds <= 0 {
		value.TempfailSeconds = fallback.TempfailSeconds
	}
	if value.GreylistSeconds <= 0 {
		value.GreylistSeconds = fallback.GreylistSeconds
	}
	if value.PolicyBlockedSecond <= 0 {
		value.PolicyBlockedSecond = fallback.PolicyBlockedSecond
	}
	if value.UnknownSeconds <= 0 {
		value.UnknownSeconds = fallback.UnknownSeconds
	}

	return value
}

func lookupProviderReplyProfile(engine *ProviderReplyPolicyEngine, provider string) ProviderReplyProfile {
	normalizedProvider := strings.ToLower(strings.TrimSpace(provider))
	if normalizedProvider == "" {
		normalizedProvider = "generic"
	}

	if engine == nil || len(engine.Profiles) == 0 {
		defaults := DefaultProviderReplyPolicyEngine()
		return defaults.Profiles["generic"]
	}

	if profile, ok := engine.Profiles[normalizedProvider]; ok {
		if strings.TrimSpace(profile.Name) == "" {
			profile.Name = normalizedProvider
		}
		return profile
	}

	generic, ok := engine.Profiles["generic"]
	if !ok {
		defaults := DefaultProviderReplyPolicyEngine()
		generic = defaults.Profiles["generic"]
	}
	if strings.TrimSpace(generic.Name) == "" {
		generic.Name = "generic"
	}
	return generic
}

func matchProviderReplyRule(profile ProviderReplyProfile, reply smtpReply) (ProviderReplyRule, bool) {
	enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
	message := strings.ToLower(strings.TrimSpace(reply.Message))

	for _, rule := range profile.EnhancedRules {
		for _, prefix := range rule.EnhancedPrefixes {
			prefix = strings.ToLower(strings.TrimSpace(prefix))
			if prefix == "" {
				continue
			}
			if strings.HasPrefix(enhanced, prefix) {
				return rule, true
			}
		}
	}

	for _, rule := range profile.SMTPCodeRules {
		for _, code := range rule.SMTPCodes {
			if code == reply.Code {
				return rule, true
			}
		}
	}

	for _, rule := range profile.MessageRules {
		for _, token := range rule.MessageContains {
			token = strings.ToLower(strings.TrimSpace(token))
			if token == "" {
				continue
			}
			if strings.Contains(message, token) {
				return rule, true
			}
		}
	}

	return ProviderReplyRule{}, false
}

func resolveAdaptiveRetryDelay(
	reply smtpReply,
	decisionClass string,
	provider string,
	profile ProviderReplyProfile,
	adaptiveRetryEnabled bool,
) int {
	if !adaptiveRetryEnabled {
		return retryDelaySeconds(reply)
	}

	retry := normalizeRetryProfile(profile.Retry, ProviderRetryPolicy{
		DefaultSeconds:      60,
		TempfailSeconds:     90,
		GreylistSeconds:     180,
		PolicyBlockedSecond: 300,
		UnknownSeconds:      75,
	})

	message := strings.ToLower(strings.TrimSpace(reply.Message))
	enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
	_ = provider

	if strings.Contains(message, "greylist") || strings.Contains(message, "try again later") {
		return retry.GreylistSeconds
	}
	if strings.HasPrefix(enhanced, "4.7.") {
		return retry.GreylistSeconds
	}

	switch decisionClass {
	case DecisionRetryable:
		return retry.TempfailSeconds
	case DecisionPolicyBlocked:
		return retry.PolicyBlockedSecond
	case DecisionUnknown:
		return retry.UnknownSeconds
	default:
		return retry.DefaultSeconds
	}
}
