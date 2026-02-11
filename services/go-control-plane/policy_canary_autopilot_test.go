package main

import "testing"

func TestNextPolicyCanaryStep(t *testing.T) {
	tests := []struct {
		current  int
		expected int
		ok       bool
	}{
		{current: 1, expected: 5, ok: true},
		{current: 5, expected: 25, ok: true},
		{current: 25, expected: 50, ok: true},
		{current: 50, expected: 100, ok: true},
		{current: 100, expected: 100, ok: false},
	}

	for _, test := range tests {
		next, ok := nextPolicyCanaryStep(test.current)
		if next != test.expected || ok != test.ok {
			t.Fatalf("nextPolicyCanaryStep(%d) = (%d,%t), expected (%d,%t)", test.current, next, ok, test.expected, test.ok)
		}
	}
}

func TestEvaluatePolicyCanaryRollback(t *testing.T) {
	state := policyCanaryAutopilotState{
		BaselineUnknownRate:     0.10,
		BaselineTempfailRecover: 0.85,
		BaselinePolicyBlockRate: 0.05,
		HealthyWindows:          2,
	}

	if reason := evaluatePolicyCanaryRollback(
		state,
		policyCanaryKPI{UnknownRate: 0.20, TempfailRecovery: 0.80, PolicyBlockRate: 0.05},
		0.05,
		0.10,
		0.10,
	); reason == "" {
		t.Fatal("expected unknown-rate rollback reason")
	}

	if reason := evaluatePolicyCanaryRollback(
		state,
		policyCanaryKPI{UnknownRate: 0.10, TempfailRecovery: 0.70, PolicyBlockRate: 0.05},
		0.05,
		0.10,
		0.10,
	); reason == "" {
		t.Fatal("expected tempfail-recovery rollback reason")
	}

	if reason := evaluatePolicyCanaryRollback(
		state,
		policyCanaryKPI{UnknownRate: 0.10, TempfailRecovery: 0.85, PolicyBlockRate: 0.20},
		0.05,
		0.10,
		0.10,
	); reason == "" {
		t.Fatal("expected policy-block rollback reason")
	}

	if reason := evaluatePolicyCanaryRollback(
		state,
		policyCanaryKPI{UnknownRate: 0.12, TempfailRecovery: 0.82, PolicyBlockRate: 0.08},
		0.05,
		0.10,
		0.10,
	); reason != "" {
		t.Fatalf("expected no rollback reason, got %q", reason)
	}
}

func TestHasManualProviderOverride(t *testing.T) {
	modes := map[string]ProviderModeState{
		"gmail": {
			Provider: "gmail",
			Mode:     "drain",
			Source:   "manual",
		},
	}

	if !hasManualProviderOverride(modes) {
		t.Fatal("expected manual override to be detected")
	}

	modes["gmail"] = ProviderModeState{
		Provider: "gmail",
		Mode:     "normal",
		Source:   "manual",
	}
	if hasManualProviderOverride(modes) {
		t.Fatal("expected normal mode override not to block autopilot")
	}
}
