package verifier

import (
	"encoding/json"
	"fmt"
	"strings"
)

type ReplyEvidence struct {
	ReasonCode       string `json:"reason_code,omitempty"`
	ReasonTag        string `json:"reason_tag,omitempty"`
	MXHost           string `json:"mx_host,omitempty"`
	AttemptNumber    int    `json:"attempt_number,omitempty"`
	AttemptRoute     string `json:"attempt_route,omitempty"`
	EvidenceStrength string `json:"evidence_strength,omitempty"`
	SMTPCode         int    `json:"smtp_code,omitempty"`
	EnhancedCode     string `json:"enhanced_code,omitempty"`
	ProviderProfile  string `json:"provider_profile,omitempty"`
	DecisionClass    string `json:"decision_class,omitempty"`
	ConfidenceHint   string `json:"confidence_hint,omitempty"`
	SessionStrategy  string `json:"session_strategy_id,omitempty"`
}

type ProviderReplyPolicyEngine struct {
	Enabled       bool                            `json:"enabled"`
	Version       string                          `json:"version,omitempty"`
	SchemaVersion string                          `json:"schema_version,omitempty"`
	Profiles      map[string]ProviderReplyProfile `json:"profiles"`
	Modes         map[string]ProviderModeRule     `json:"modes,omitempty"`
}

type ProviderReplyProfile struct {
	Name          string              `json:"name,omitempty"`
	EnhancedRules []ProviderReplyRule `json:"enhanced_rules,omitempty"`
	SMTPCodeRules []ProviderReplyRule `json:"smtp_code_rules,omitempty"`
	MessageRules  []ProviderReplyRule `json:"message_rules,omitempty"`
	Retry         ProviderRetryPolicy `json:"retry"`
	Session       ProviderSessionRule `json:"session,omitempty"`
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
	RuleTag          string   `json:"rule_tag,omitempty"`
	ConfidenceHint   string   `json:"confidence_hint,omitempty"`
	ProviderScope    string   `json:"provider_scope,omitempty"`
	RetryAfterSecond int      `json:"retry_after_seconds,omitempty"`
}

type ProviderRetryPolicy struct {
	DefaultSeconds      int `json:"default_seconds,omitempty"`
	TempfailSeconds     int `json:"tempfail_seconds,omitempty"`
	GreylistSeconds     int `json:"greylist_seconds,omitempty"`
	PolicyBlockedSecond int `json:"policy_blocked_seconds,omitempty"`
	UnknownSeconds      int `json:"unknown_seconds,omitempty"`
}

type ProviderSessionRule struct {
	MaxConcurrency            int    `json:"max_concurrency,omitempty"`
	ConnectsPerMinute         int    `json:"connects_per_minute,omitempty"`
	ReuseConnectionForRetries bool   `json:"reuse_connection_for_retries"`
	RetryJitterPercent        int    `json:"retry_jitter_percent,omitempty"`
	EHLOProfile               string `json:"ehlo_profile,omitempty"`
}

type ProviderModeRule struct {
	ProbeEnabled                bool    `json:"probe_enabled"`
	MaxConcurrencyMultiplier    float64 `json:"max_concurrency_multiplier,omitempty"`
	ConnectsPerMinuteMultiplier float64 `json:"connects_per_minute_multiplier,omitempty"`
}

func DefaultProviderReplyPolicyEngine() *ProviderReplyPolicyEngine {
	engine := ProviderReplyPolicyEngine{
		Enabled:       true,
		Version:       "v4",
		SchemaVersion: "v4",
		Profiles:      defaultProviderReplyProfiles(),
		Modes:         defaultProviderModeRules(),
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
	defaults := ProviderReplyPolicyEngine{
		Version:       "v4",
		SchemaVersion: "v4",
		Profiles:      defaultProviderReplyProfiles(),
		Modes:         defaultProviderModeRules(),
	}
	genericDefaults := defaults.Profiles["generic"]
	out := engine
	if out.Profiles == nil {
		out.Profiles = map[string]ProviderReplyProfile{}
	}
	if out.Modes == nil {
		out.Modes = map[string]ProviderModeRule{}
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
		existing.EnhancedRules = normalizeProviderReplyRules(existing.EnhancedRules, key)
		existing.SMTPCodeRules = normalizeProviderReplyRules(existing.SMTPCodeRules, key)
		existing.MessageRules = normalizeProviderReplyRules(existing.MessageRules, key)
		existing.Retry = normalizeRetryProfile(existing.Retry, profile.Retry)
		existing.Session = normalizeSessionProfile(existing.Session, profile.Session)
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
	if strings.TrimSpace(out.SchemaVersion) == "" {
		if hasV3Signals(out) {
			out.SchemaVersion = "v3"
		} else {
			out.SchemaVersion = "v2"
		}
	}

	for mode, modeRule := range defaults.Modes {
		existing, ok := out.Modes[mode]
		if !ok {
			out.Modes[mode] = modeRule
			continue
		}

		if existing.MaxConcurrencyMultiplier <= 0 {
			existing.MaxConcurrencyMultiplier = modeRule.MaxConcurrencyMultiplier
		}
		if existing.ConnectsPerMinuteMultiplier <= 0 {
			existing.ConnectsPerMinuteMultiplier = modeRule.ConnectsPerMinuteMultiplier
		}
		if mode == "normal" && !existing.ProbeEnabled {
			existing.ProbeEnabled = true
		}
		if mode == "drain" || mode == "quarantine" {
			existing.ProbeEnabled = false
		}
		out.Modes[mode] = existing
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
					RuleTag:          "mailbox_not_found",
				},
				{
					RuleID:           "generic-enhanced-57-policy-blocked",
					EnhancedPrefixes: []string{"5.7."},
					DecisionClass:    DecisionPolicyBlocked,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_policy_blocked",
					RuleTag:          "policy_blocked",
				},
				{
					RuleID:           "generic-enhanced-42-44-retry",
					EnhancedPrefixes: []string{"4.2.", "4.4."},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
					RuleTag:          "unknown_transient",
				},
				{
					RuleID:           "generic-enhanced-422-mailbox-full",
					EnhancedPrefixes: []string{"4.2.2"},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "mailbox_full",
					RuleTag:          "mailbox_full",
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
					RuleTag:       "unknown_transient",
				},
				{
					RuleID:        "generic-smtp-550-553-undeliverable",
					SMTPCodes:     []int{550, 551, 553},
					DecisionClass: DecisionUndeliverable,
					Category:      CategoryInvalid,
					Reason:        "rcpt_rejected",
					ReasonCode:    "rcpt_rejected",
					RuleTag:       "mailbox_not_found",
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
					RuleTag:          "greylist",
				},
				{
					RuleID:          "generic-msg-policy-blocked",
					MessageContains: []string{"policy", "blocked", "denied", "forbidden", "authentication", "auth"},
					DecisionClass:   DecisionPolicyBlocked,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_policy_blocked",
					RuleTag:         "policy_blocked",
				},
				{
					RuleID:          "generic-msg-mailbox-not-found",
					MessageContains: []string{"user unknown", "no such user", "mailbox unavailable", "recipient address rejected"},
					DecisionClass:   DecisionUndeliverable,
					Category:        CategoryInvalid,
					Reason:          "rcpt_rejected",
					ReasonCode:      "mailbox_not_found",
					RuleTag:         "mailbox_not_found",
				},
				{
					RuleID:          "generic-msg-mailbox-full",
					MessageContains: []string{"mailbox full", "quota exceeded", "mailbox over quota"},
					DecisionClass:   DecisionRetryable,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "mailbox_full",
					RuleTag:         "mailbox_full",
				},
				{
					RuleID:          "generic-msg-auth-required",
					MessageContains: []string{"authentication required", "auth required", "sender verify failed"},
					DecisionClass:   DecisionPolicyBlocked,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_policy_blocked",
					RuleTag:         "auth_required",
				},
			},
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      60,
				TempfailSeconds:     90,
				GreylistSeconds:     180,
				PolicyBlockedSecond: 300,
				UnknownSeconds:      75,
			},
			Session: ProviderSessionRule{
				MaxConcurrency:            2,
				ConnectsPerMinute:         30,
				ReuseConnectionForRetries: false,
				RetryJitterPercent:        15,
				EHLOProfile:               "default",
			},
		},
		"gmail": {
			Name: "gmail",
			EnhancedRules: []ProviderReplyRule{
				{
					RuleID:           "gmail-enhanced-47-retry",
					EnhancedPrefixes: []string{"4.7."},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
					RuleTag:          "rate_limit",
				},
			},
			SMTPCodeRules: []ProviderReplyRule{
				{
					RuleID:        "gmail-smtp-421-451-retry",
					SMTPCodes:     []int{421, 450, 451},
					DecisionClass: DecisionRetryable,
					Category:      CategoryRisky,
					Reason:        "smtp_tempfail",
					ReasonCode:    "smtp_tempfail",
					RuleTag:       "rate_limit",
				},
			},
			MessageRules: []ProviderReplyRule{
				{
					RuleID:          "gmail-msg-rate-limit-retry",
					MessageContains: []string{"rate limited", "temporarily deferred", "try again later"},
					DecisionClass:   DecisionRetryable,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_tempfail",
					RuleTag:         "rate_limit",
				},
			},
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      90,
				TempfailSeconds:     120,
				GreylistSeconds:     240,
				PolicyBlockedSecond: 480,
				UnknownSeconds:      120,
			},
			Session: ProviderSessionRule{
				MaxConcurrency:            2,
				ConnectsPerMinute:         20,
				ReuseConnectionForRetries: false,
				RetryJitterPercent:        20,
				EHLOProfile:               "provider-safe-gmail",
			},
		},
		"microsoft": {
			Name: "microsoft",
			EnhancedRules: []ProviderReplyRule{
				{
					RuleID:           "microsoft-enhanced-47-retry",
					EnhancedPrefixes: []string{"4.7."},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
					RuleTag:          "rate_limit",
				},
			},
			SMTPCodeRules: []ProviderReplyRule{
				{
					RuleID:        "microsoft-smtp-421-451-retry",
					SMTPCodes:     []int{421, 451, 452},
					DecisionClass: DecisionRetryable,
					Category:      CategoryRisky,
					Reason:        "smtp_tempfail",
					ReasonCode:    "smtp_tempfail",
					RuleTag:       "rate_limit",
				},
			},
			MessageRules: []ProviderReplyRule{
				{
					RuleID:          "microsoft-msg-deferral-retry",
					MessageContains: []string{"temporarily deferred", "throttled", "try this again later"},
					DecisionClass:   DecisionRetryable,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_tempfail",
					RuleTag:         "rate_limit",
				},
			},
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      90,
				TempfailSeconds:     150,
				GreylistSeconds:     300,
				PolicyBlockedSecond: 600,
				UnknownSeconds:      120,
			},
			Session: ProviderSessionRule{
				MaxConcurrency:            2,
				ConnectsPerMinute:         18,
				ReuseConnectionForRetries: false,
				RetryJitterPercent:        20,
				EHLOProfile:               "provider-safe-microsoft",
			},
		},
		"yahoo": {
			Name: "yahoo",
			EnhancedRules: []ProviderReplyRule{
				{
					RuleID:           "yahoo-enhanced-47-retry",
					EnhancedPrefixes: []string{"4.7."},
					DecisionClass:    DecisionRetryable,
					Category:         CategoryRisky,
					Reason:           "smtp_tempfail",
					ReasonCode:       "smtp_tempfail",
					RuleTag:          "rate_limit",
				},
			},
			SMTPCodeRules: []ProviderReplyRule{
				{
					RuleID:        "yahoo-smtp-421-451-retry",
					SMTPCodes:     []int{421, 451, 452},
					DecisionClass: DecisionRetryable,
					Category:      CategoryRisky,
					Reason:        "smtp_tempfail",
					ReasonCode:    "smtp_tempfail",
					RuleTag:       "rate_limit",
				},
			},
			MessageRules: []ProviderReplyRule{
				{
					RuleID:          "yahoo-msg-tempfail-retry",
					MessageContains: []string{"temporarily deferred", "temporarily unavailable", "try again later"},
					DecisionClass:   DecisionRetryable,
					Category:        CategoryRisky,
					Reason:          "smtp_tempfail",
					ReasonCode:      "smtp_tempfail",
					RuleTag:         "rate_limit",
				},
			},
			Retry: ProviderRetryPolicy{
				DefaultSeconds:      75,
				TempfailSeconds:     120,
				GreylistSeconds:     240,
				PolicyBlockedSecond: 480,
				UnknownSeconds:      100,
			},
			Session: ProviderSessionRule{
				MaxConcurrency:            2,
				ConnectsPerMinute:         22,
				ReuseConnectionForRetries: false,
				RetryJitterPercent:        15,
				EHLOProfile:               "provider-safe-yahoo",
			},
		},
	}
}

func defaultProviderModeRules() map[string]ProviderModeRule {
	return map[string]ProviderModeRule{
		"normal": {
			ProbeEnabled:                true,
			MaxConcurrencyMultiplier:    1,
			ConnectsPerMinuteMultiplier: 1,
		},
		"cautious": {
			ProbeEnabled:                true,
			MaxConcurrencyMultiplier:    0.65,
			ConnectsPerMinuteMultiplier: 0.6,
		},
		"drain": {
			ProbeEnabled:                false,
			MaxConcurrencyMultiplier:    0,
			ConnectsPerMinuteMultiplier: 0,
		},
		"quarantine": {
			ProbeEnabled:                false,
			MaxConcurrencyMultiplier:    0,
			ConnectsPerMinuteMultiplier: 0,
		},
		"degraded_probe": {
			ProbeEnabled:                true,
			MaxConcurrencyMultiplier:    0.4,
			ConnectsPerMinuteMultiplier: 0.5,
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

func normalizeSessionProfile(value ProviderSessionRule, fallback ProviderSessionRule) ProviderSessionRule {
	if value.MaxConcurrency <= 0 {
		value.MaxConcurrency = fallback.MaxConcurrency
	}
	if value.ConnectsPerMinute <= 0 {
		value.ConnectsPerMinute = fallback.ConnectsPerMinute
	}
	if value.RetryJitterPercent < 0 {
		value.RetryJitterPercent = fallback.RetryJitterPercent
	}
	if value.RetryJitterPercent > 50 {
		value.RetryJitterPercent = 50
	}
	if strings.TrimSpace(value.EHLOProfile) == "" {
		value.EHLOProfile = fallback.EHLOProfile
	}

	return value
}

func normalizeProviderReplyRules(rules []ProviderReplyRule, provider string) []ProviderReplyRule {
	output := make([]ProviderReplyRule, 0, len(rules))

	for _, rule := range rules {
		normalized := rule
		if strings.TrimSpace(normalized.RuleTag) == "" {
			normalized.RuleTag = inferRuleTag(normalized.ReasonCode, normalized.DecisionClass)
		}
		if strings.TrimSpace(normalized.ConfidenceHint) == "" {
			normalized.ConfidenceHint = inferConfidenceHint(normalized.DecisionClass, normalized.Category)
		}
		if strings.TrimSpace(normalized.ProviderScope) == "" {
			normalized.ProviderScope = provider
		}
		output = append(output, normalized)
	}

	return output
}

func inferRuleTag(reasonCode string, decisionClass string) string {
	reasonCode = strings.ToLower(strings.TrimSpace(reasonCode))
	decisionClass = strings.ToLower(strings.TrimSpace(decisionClass))

	switch {
	case strings.Contains(reasonCode, "greylist"):
		return "greylist"
	case strings.Contains(reasonCode, "mailbox_full"):
		return "mailbox_full"
	case strings.Contains(reasonCode, "policy_blocked"):
		return "policy_blocked"
	case strings.Contains(reasonCode, "mailbox_not_found"):
		return "mailbox_not_found"
	case strings.Contains(reasonCode, "auth"):
		return "auth_required"
	case strings.Contains(reasonCode, "rate_limit"):
		return "rate_limit"
	case decisionClass == DecisionPolicyBlocked:
		return "policy_blocked"
	case decisionClass == DecisionUndeliverable:
		return "mailbox_not_found"
	default:
		return "unknown_transient"
	}
}

func inferConfidenceHint(decisionClass string, category string) string {
	decisionClass = strings.ToLower(strings.TrimSpace(decisionClass))
	category = strings.ToLower(strings.TrimSpace(category))

	switch decisionClass {
	case DecisionDeliverable, DecisionUndeliverable:
		return "high"
	case DecisionPolicyBlocked, DecisionRetryable:
		return "medium"
	case DecisionUnknown:
		return "low"
	default:
		if category == CategoryValid || category == CategoryInvalid {
			return "medium"
		}
		return "low"
	}
}

func hasV3Signals(engine ProviderReplyPolicyEngine) bool {
	if len(engine.Modes) > 0 {
		return true
	}

	for _, profile := range engine.Profiles {
		if profile.Session.MaxConcurrency > 0 || profile.Session.ConnectsPerMinute > 0 || strings.TrimSpace(profile.Session.EHLOProfile) != "" {
			return true
		}
		for _, rules := range [][]ProviderReplyRule{profile.EnhancedRules, profile.SMTPCodeRules, profile.MessageRules} {
			for _, rule := range rules {
				if strings.TrimSpace(rule.RuleTag) != "" ||
					strings.TrimSpace(rule.ConfidenceHint) != "" ||
					strings.TrimSpace(rule.ProviderScope) != "" {
					return true
				}
			}
		}
	}

	return false
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

func lookupProviderModeRule(engine *ProviderReplyPolicyEngine, mode string) ProviderModeRule {
	normalizedMode := strings.ToLower(strings.TrimSpace(mode))
	if normalizedMode == "" {
		normalizedMode = "normal"
	}

	defaults := defaultProviderModeRules()

	if engine == nil || len(engine.Modes) == 0 {
		if modeRule, ok := defaults[normalizedMode]; ok {
			return modeRule
		}
		return defaults["normal"]
	}

	modeRule, ok := engine.Modes[normalizedMode]
	if !ok {
		if fallback, ok := defaults[normalizedMode]; ok {
			return fallback
		}
		return defaults["normal"]
	}

	if modeRule.MaxConcurrencyMultiplier <= 0 {
		modeRule.MaxConcurrencyMultiplier = defaults[normalizedMode].MaxConcurrencyMultiplier
	}
	if modeRule.ConnectsPerMinuteMultiplier <= 0 {
		modeRule.ConnectsPerMinuteMultiplier = defaults[normalizedMode].ConnectsPerMinuteMultiplier
	}
	if normalizedMode == "drain" || normalizedMode == "quarantine" {
		modeRule.ProbeEnabled = false
	}

	return modeRule
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
