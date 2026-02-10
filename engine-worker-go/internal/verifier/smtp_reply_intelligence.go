package verifier

import (
	"bufio"
	"errors"
	"net"
	"regexp"
	"strconv"
	"strings"
	"time"
)

type smtpReply struct {
	Code         int
	EnhancedCode string
	Message      string
	Lines        []string
}

var enhancedStatusPattern = regexp.MustCompile(`\b([245]\.\d\.\d+)\b`)

func readSMTPReply(conn net.Conn, timeout time.Duration) (smtpReply, *Result) {
	_ = conn.SetReadDeadline(time.Now().Add(timeout))
	reader := bufio.NewReader(conn)

	line, err := reader.ReadString('\n')
	if err != nil {
		if isTimeout(err) {
			result := Result{
				Category:      CategoryRisky,
				Reason:        "smtp_timeout",
				ReasonCode:    "smtp_timeout",
				DecisionClass: DecisionRetryable,
			}
			return smtpReply{}, &result
		}

		result := Result{
			Category:      CategoryRisky,
			Reason:        "smtp_tempfail",
			ReasonCode:    "smtp_read_error",
			DecisionClass: DecisionRetryable,
		}
		return smtpReply{}, &result
	}

	trimmed := strings.TrimSpace(line)
	reply := smtpReply{
		Code:  parseSMTPCode(trimmed),
		Lines: []string{trimmed},
	}

	if hasContinuation(trimmed) {
		for {
			nextLine, readErr := reader.ReadString('\n')
			if readErr != nil {
				if isTimeout(readErr) || errors.Is(readErr, net.ErrClosed) {
					break
				}
				break
			}

			nextTrimmed := strings.TrimSpace(nextLine)
			reply.Lines = append(reply.Lines, nextTrimmed)

			if isTerminalLine(reply.Code, nextTrimmed) {
				break
			}
		}
	}

	reply.Message = pickReplyMessage(reply.Lines)
	reply.EnhancedCode = extractEnhancedStatus(reply.Lines)

	if reply.Code == 0 {
		result := Result{
			Category:      CategoryRisky,
			Reason:        "smtp_tempfail",
			ReasonCode:    "smtp_malformed_reply",
			DecisionClass: DecisionUnknown,
		}
		return smtpReply{}, &result
	}

	return reply, nil
}

func classifySMTPSessionReply(
	command string,
	reply smtpReply,
	providerHint, host string,
	engine *ProviderReplyPolicyEngine,
	adaptiveRetryEnabled bool,
) (Result, bool) {
	profile := detectSMTPProviderProfile(providerHint, host, reply.Message)

	if result, ok := classifySMTPWithPolicyEngine(command, reply, profile, engine, adaptiveRetryEnabled); ok {
		return result, true
	}

	switch {
	case reply.Code >= 200 && reply.Code < 300:
		return Result{}, false
	case reply.Code >= 400 && reply.Code < 500:
		return smtpResult(
			CategoryRisky,
			"smtp_tempfail",
			"smtp_tempfail",
			DecisionRetryable,
			reply,
			profile,
			resolveAdaptiveRetryDelay(
				reply,
				DecisionRetryable,
				profile,
				lookupProviderReplyProfile(engine, profile),
				adaptiveRetryEnabled,
			),
		), true
	case reply.Code >= 500:
		if isPolicyBlockedReply(reply) {
			return smtpResult(
				CategoryRisky,
				"smtp_tempfail",
				"smtp_policy_blocked",
				DecisionPolicyBlocked,
				reply,
				profile,
				0,
			), true
		}

		if command == "mail_from" {
			return smtpResult(
				CategoryRisky,
				"smtp_tempfail",
				"smtp_mailfrom_rejected",
				DecisionRetryable,
				reply,
				profile,
				resolveAdaptiveRetryDelay(
					reply,
					DecisionRetryable,
					profile,
					lookupProviderReplyProfile(engine, profile),
					adaptiveRetryEnabled,
				),
			), true
		}

		return smtpResult(
			CategoryInvalid,
			"smtp_unavailable",
			"smtp_unavailable",
			DecisionUndeliverable,
			reply,
			profile,
			0,
		), true
	default:
		return smtpResult(
			CategoryRisky,
			"smtp_tempfail",
			"smtp_unknown",
			DecisionUnknown,
			reply,
			profile,
			resolveAdaptiveRetryDelay(
				reply,
				DecisionUnknown,
				profile,
				lookupProviderReplyProfile(engine, profile),
				adaptiveRetryEnabled,
			),
		), true
	}
}

func classifySMTPRcptReply(
	reply smtpReply,
	providerHint, host string,
	allowValid bool,
	engine *ProviderReplyPolicyEngine,
	adaptiveRetryEnabled bool,
) Result {
	profile := detectSMTPProviderProfile(providerHint, host, reply.Message)

	if result, ok := classifySMTPWithPolicyEngine("rcpt_to", reply, profile, engine, adaptiveRetryEnabled); ok {
		if result.Category == CategoryValid && !allowValid {
			return smtpResult(
				CategoryRisky,
				"catch_all",
				"catch_all",
				DecisionUnknown,
				reply,
				profile,
				0,
			)
		}
		return result
	}

	switch {
	case reply.Code >= 200 && reply.Code < 300:
		if allowValid {
			return smtpResult(
				CategoryValid,
				"rcpt_ok",
				"rcpt_ok",
				DecisionDeliverable,
				reply,
				profile,
				0,
			)
		}
		return smtpResult(
			CategoryRisky,
			"catch_all",
			"catch_all",
			DecisionUnknown,
			reply,
			profile,
			0,
		)
	case reply.Code >= 400 && reply.Code < 500:
		return smtpResult(
			CategoryRisky,
			"smtp_tempfail",
			"smtp_tempfail",
			DecisionRetryable,
			reply,
			profile,
			resolveAdaptiveRetryDelay(
				reply,
				DecisionRetryable,
				profile,
				lookupProviderReplyProfile(engine, profile),
				adaptiveRetryEnabled,
			),
		)
	case reply.Code >= 500:
		if isPolicyBlockedReply(reply) {
			return smtpResult(
				CategoryRisky,
				"smtp_tempfail",
				"smtp_policy_blocked",
				DecisionPolicyBlocked,
				reply,
				profile,
				0,
			)
		}

		if isMailboxUndeliverableReply(reply) {
			return smtpResult(
				CategoryInvalid,
				"rcpt_rejected",
				"mailbox_not_found",
				DecisionUndeliverable,
				reply,
				profile,
				0,
			)
		}

		return smtpResult(
			CategoryInvalid,
			"rcpt_rejected",
			"rcpt_rejected",
			DecisionUndeliverable,
			reply,
			profile,
			0,
		)
	default:
		return smtpResult(
			CategoryRisky,
			"smtp_tempfail",
			"smtp_unknown",
			DecisionUnknown,
			reply,
			profile,
			resolveAdaptiveRetryDelay(
				reply,
				DecisionUnknown,
				profile,
				lookupProviderReplyProfile(engine, profile),
				adaptiveRetryEnabled,
			),
		)
	}
}

func classifySMTPWithPolicyEngine(
	command string,
	reply smtpReply,
	profile string,
	engine *ProviderReplyPolicyEngine,
	adaptiveRetryEnabled bool,
) (Result, bool) {
	if engine == nil || !engine.Enabled {
		return Result{}, false
	}

	replyProfile := lookupProviderReplyProfile(engine, profile)
	rule, ok := matchProviderReplyRule(replyProfile, reply)
	if !ok {
		return Result{}, false
	}

	category := strings.TrimSpace(rule.Category)
	reason := strings.TrimSpace(rule.Reason)
	reasonCode := strings.TrimSpace(rule.ReasonCode)
	decisionClass := strings.TrimSpace(rule.DecisionClass)

	if category == "" {
		category = CategoryRisky
	}
	if reason == "" {
		reason = "smtp_tempfail"
	}
	if reasonCode == "" {
		reasonCode = "smtp_unknown"
	}
	if decisionClass == "" {
		decisionClass = DecisionUnknown
	}

	if command == "mail_from" && decisionClass == DecisionUndeliverable {
		category = CategoryRisky
		reason = "smtp_tempfail"
		reasonCode = "smtp_mailfrom_rejected"
		decisionClass = DecisionRetryable
	}

	if command != "rcpt_to" && category == CategoryValid {
		// Session-level commands should not force a valid terminal state.
		return Result{}, false
	}

	retryAfter := rule.RetryAfterSecond
	if retryAfter <= 0 && (decisionClass == DecisionRetryable || decisionClass == DecisionUnknown || decisionClass == DecisionPolicyBlocked) {
		retryAfter = resolveAdaptiveRetryDelay(reply, decisionClass, profile, replyProfile, adaptiveRetryEnabled)
	}

	return smtpResult(
		category,
		reason,
		reasonCode,
		decisionClass,
		reply,
		profile,
		retryAfter,
		withPolicyContext(engine, rule),
	), true
}

func smtpResult(category, reason, reasonCode, decisionClass string, reply smtpReply, profile string, retryAfter int, options ...func(*Result)) Result {
	result := Result{
		Category:           category,
		Reason:             reason,
		ReasonCode:         reasonCode,
		DecisionClass:      decisionClass,
		SMTPCode:           reply.Code,
		EnhancedCode:       reply.EnhancedCode,
		ProviderProfile:    profile,
		RetryAfterSecond:   retryAfter,
		DecisionConfidence: decisionConfidenceFor(decisionClass, category, reply),
		RetryStrategy:      retryStrategyForDecision(decisionClass, reasonCode, reply),
		Evidence: &ReplyEvidence{
			ReasonCode:      reasonCode,
			SMTPCode:        reply.Code,
			EnhancedCode:    reply.EnhancedCode,
			ProviderProfile: profile,
			DecisionClass:   decisionClass,
		},
	}

	for _, applyOption := range options {
		if applyOption == nil {
			continue
		}

		applyOption(&result)
	}

	return result
}

func withPolicyContext(engine *ProviderReplyPolicyEngine, rule ProviderReplyRule) func(*Result) {
	return func(result *Result) {
		if result == nil {
			return
		}

		if engine != nil {
			result.PolicyVersion = strings.TrimSpace(engine.Version)
		}

		ruleID := strings.TrimSpace(rule.RuleID)
		if ruleID == "" {
			ruleID = strings.TrimSpace(rule.ReasonCode)
		}
		if ruleID == "" {
			ruleID = strings.TrimSpace(rule.DecisionClass)
		}
		result.MatchedRuleID = ruleID
	}
}

func retryStrategyForDecision(decisionClass string, reasonCode string, reply smtpReply) string {
	switch decisionClass {
	case DecisionDeliverable, DecisionUndeliverable:
		return "none"
	case DecisionPolicyBlocked:
		return "policy_delay"
	case DecisionRetryable, DecisionUnknown:
		message := strings.ToLower(strings.TrimSpace(reply.Message))
		enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
		reason := strings.ToLower(strings.TrimSpace(reasonCode))
		if strings.Contains(message, "greylist") || strings.HasPrefix(enhanced, "4.7.") || strings.Contains(reason, "greylist") {
			return "greylist"
		}
		return "tempfail"
	default:
		return "none"
	}
}

func decisionConfidenceFor(decisionClass string, category string, reply smtpReply) string {
	switch decisionClass {
	case DecisionDeliverable:
		if reply.Code >= 200 && reply.Code < 300 {
			return "high"
		}
		return "medium"
	case DecisionUndeliverable:
		if reply.Code >= 500 {
			return "high"
		}
		return "medium"
	case DecisionPolicyBlocked:
		return "medium"
	case DecisionRetryable:
		if strings.HasPrefix(strings.ToLower(strings.TrimSpace(reply.EnhancedCode)), "4.7.") {
			return "medium"
		}
		return "low"
	case DecisionUnknown:
		return "low"
	default:
		if category == CategoryValid || category == CategoryInvalid {
			return "medium"
		}
		return "low"
	}
}

func hasContinuation(line string) bool {
	return len(line) >= 4 && line[3] == '-'
}

func isTerminalLine(code int, line string) bool {
	if len(line) < 4 {
		return false
	}
	if parseSMTPCode(line) != code {
		return false
	}

	return line[3] == ' '
}

func pickReplyMessage(lines []string) string {
	for i := len(lines) - 1; i >= 0; i-- {
		line := strings.TrimSpace(lines[i])
		if line == "" {
			continue
		}
		if len(line) >= 4 {
			return strings.TrimSpace(line[4:])
		}
		return line
	}
	return ""
}

func extractEnhancedStatus(lines []string) string {
	for _, line := range lines {
		match := enhancedStatusPattern.FindStringSubmatch(line)
		if len(match) > 1 {
			return match[1]
		}
	}
	return ""
}

func isPolicyBlockedReply(reply smtpReply) bool {
	enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
	if strings.HasPrefix(enhanced, "5.7.") {
		return true
	}

	if strings.HasPrefix(enhanced, "4.7.") {
		// 4.7.x is usually greylist/tempfail; only treat as policy-blocked when message confirms policy enforcement.
		message := strings.ToLower(reply.Message)
		keywords := []string{"policy", "blocked", "blocklist", "spam", "denied", "forbidden", "authentication", "auth"}
		for _, keyword := range keywords {
			if strings.Contains(message, keyword) {
				return true
			}
		}

		return false
	}

	message := strings.ToLower(reply.Message)
	keywords := []string{"policy", "blocked", "blocklist", "spam", "denied", "forbidden", "authentication", "auth"}
	for _, keyword := range keywords {
		if strings.Contains(message, keyword) {
			return true
		}
	}

	return false
}

func isMailboxUndeliverableReply(reply smtpReply) bool {
	enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
	if strings.HasPrefix(enhanced, "5.1.1") || strings.HasPrefix(enhanced, "5.1.10") {
		return true
	}
	if strings.HasPrefix(enhanced, "5.2.") {
		return true
	}

	message := strings.ToLower(reply.Message)
	keywords := []string{"user unknown", "no such user", "mailbox unavailable", "recipient address rejected", "unknown recipient", "does not exist"}
	for _, keyword := range keywords {
		if strings.Contains(message, keyword) {
			return true
		}
	}

	return reply.Code == 550 || reply.Code == 551 || reply.Code == 553
}

func retryDelaySeconds(reply smtpReply) int {
	enhanced := strings.ToLower(strings.TrimSpace(reply.EnhancedCode))
	message := strings.ToLower(reply.Message)

	if strings.Contains(message, "greylist") || strings.Contains(message, "try again later") {
		return 180
	}
	if strings.HasPrefix(enhanced, "4.7.") {
		return 180
	}
	if strings.HasPrefix(enhanced, "4.4.") || strings.HasPrefix(enhanced, "4.2.") {
		return 90
	}
	if reply.Code >= 400 && reply.Code < 500 {
		return 60
	}

	return 30
}

func detectSMTPProviderProfile(providerHint, host, message string) string {
	hint := strings.ToLower(strings.TrimSpace(providerHint))
	if hint != "" && hint != "default" {
		return hint
	}

	combined := strings.ToLower(host + " " + message)
	switch {
	case strings.Contains(combined, "google") || strings.Contains(combined, "gmail"):
		return "gmail"
	case strings.Contains(combined, "outlook") || strings.Contains(combined, "hotmail") || strings.Contains(combined, "microsoft"):
		return "microsoft"
	case strings.Contains(combined, "yahoo"):
		return "yahoo"
	default:
		return "generic"
	}
}

func formatSMTPCode(code int) string {
	if code <= 0 {
		return ""
	}
	return strconv.Itoa(code)
}
