package verifier

import (
	"net"
	"strings"
	"testing"
	"time"
)

func TestClassifySMTPRcptReplyUndeliverable(t *testing.T) {
	reply := smtpReply{
		Code:         550,
		EnhancedCode: "5.1.1",
		Message:      "User unknown",
	}

	result := classifySMTPRcptReply(reply, "gmail", "gmail-smtp-in.l.google.com", true, nil, false)
	if result.Category != CategoryInvalid {
		t.Fatalf("expected invalid category, got %q", result.Category)
	}
	if result.Reason != "rcpt_rejected" {
		t.Fatalf("expected rcpt_rejected reason, got %q", result.Reason)
	}
	if result.DecisionClass != DecisionUndeliverable {
		t.Fatalf("expected undeliverable decision class, got %q", result.DecisionClass)
	}
	if result.EnhancedCode != "5.1.1" {
		t.Fatalf("expected enhanced code 5.1.1, got %q", result.EnhancedCode)
	}
}

func TestClassifySMTPRcptReplyPolicyBlocked(t *testing.T) {
	reply := smtpReply{
		Code:         550,
		EnhancedCode: "5.7.1",
		Message:      "Access denied by policy",
	}

	result := classifySMTPRcptReply(reply, "microsoft", "outlook-com.olc.protection.outlook.com", true, nil, false)
	if result.Category != CategoryRisky {
		t.Fatalf("expected risky category, got %q", result.Category)
	}
	if result.Reason != "smtp_tempfail" {
		t.Fatalf("expected smtp_tempfail reason, got %q", result.Reason)
	}
	if result.DecisionClass != DecisionPolicyBlocked {
		t.Fatalf("expected policy_blocked decision class, got %q", result.DecisionClass)
	}
	if isRetryableSMTPResult(result) {
		t.Fatal("policy-blocked decisions must not be retried blindly")
	}
}

func TestClassifySMTPRcptReplyTempfailRetryWindow(t *testing.T) {
	reply := smtpReply{
		Code:         451,
		EnhancedCode: "4.7.1",
		Message:      "Greylisted, try again later",
	}

	result := classifySMTPRcptReply(reply, "generic", "mx.example.test", true, nil, false)
	if result.Category != CategoryRisky {
		t.Fatalf("expected risky category, got %q", result.Category)
	}
	if result.DecisionClass != DecisionRetryable {
		t.Fatalf("expected retryable decision class, got %q", result.DecisionClass)
	}
	if result.RetryAfterSecond < 180 {
		t.Fatalf("expected retry delay >= 180, got %d", result.RetryAfterSecond)
	}
}

func TestReadSMTPReplyParsesMultilineEnhanced(t *testing.T) {
	client, server := net.Pipe()
	defer client.Close()

	go func() {
		defer server.Close()
		_, _ = server.Write([]byte("220-first line\r\n"))
		_, _ = server.Write([]byte("220 2.0.0 Ready\r\n"))
	}()

	reply, result := readSMTPReply(client, time.Second)
	if result != nil {
		t.Fatalf("expected nil result error, got %+v", result)
	}
	if reply.Code != 220 {
		t.Fatalf("expected code 220, got %d", reply.Code)
	}
	if reply.EnhancedCode != "2.0.0" {
		t.Fatalf("expected enhanced code 2.0.0, got %q", reply.EnhancedCode)
	}
	if !strings.Contains(strings.ToLower(reply.Message), "ready") {
		t.Fatalf("expected message to include ready, got %q", reply.Message)
	}
}

func TestClassifySMTPPolicyEngineUsesEnhancedRulePrecedence(t *testing.T) {
	engine := DefaultProviderReplyPolicyEngine()
	engine.Profiles["generic"] = ProviderReplyProfile{
		Name: "generic",
		EnhancedRules: []ProviderReplyRule{
			{
				EnhancedPrefixes: []string{"5.1.1"},
				DecisionClass:    DecisionUndeliverable,
				Category:         CategoryInvalid,
				Reason:           "rcpt_rejected",
				ReasonCode:       "mailbox_not_found",
			},
		},
		SMTPCodeRules: []ProviderReplyRule{
			{
				SMTPCodes:     []int{550},
				DecisionClass: DecisionPolicyBlocked,
				Category:      CategoryRisky,
				Reason:        "smtp_tempfail",
				ReasonCode:    "smtp_policy_blocked",
			},
		},
		MessageRules: nil,
		Retry: ProviderRetryPolicy{
			DefaultSeconds:      60,
			TempfailSeconds:     90,
			GreylistSeconds:     180,
			PolicyBlockedSecond: 300,
			UnknownSeconds:      75,
		},
	}

	reply := smtpReply{
		Code:         550,
		EnhancedCode: "5.1.1",
		Message:      "user unknown",
	}

	result := classifySMTPRcptReply(reply, "generic", "mx.example.test", true, engine, true)
	if result.DecisionClass != DecisionUndeliverable {
		t.Fatalf("expected enhanced rule precedence to choose undeliverable, got %q", result.DecisionClass)
	}
	if result.Category != CategoryInvalid {
		t.Fatalf("expected invalid from enhanced rule precedence, got %q", result.Category)
	}
}

func TestClassifySMTPPolicyEngineAdaptiveRetryProfile(t *testing.T) {
	engine := DefaultProviderReplyPolicyEngine()
	reply := smtpReply{
		Code:         451,
		EnhancedCode: "4.4.1",
		Message:      "temporarily deferred",
	}

	result := classifySMTPRcptReply(reply, "microsoft", "mx.microsoft.test", true, engine, true)
	if result.DecisionClass != DecisionRetryable {
		t.Fatalf("expected retryable decision class, got %q", result.DecisionClass)
	}
	if result.RetryAfterSecond < 150 {
		t.Fatalf("expected adaptive retry delay >= 150 for microsoft tempfail, got %d", result.RetryAfterSecond)
	}
}
