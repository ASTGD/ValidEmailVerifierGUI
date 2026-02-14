package main

import "testing"

func TestDefaultProviderModeSemanticsIncludesExtendedModes(t *testing.T) {
	semantics := defaultProviderModeSemantics()

	required := []string{"normal", "cautious", "drain", "quarantine", "degraded_probe"}
	for _, key := range required {
		if _, ok := semantics[key]; !ok {
			t.Fatalf("expected mode semantics to include %s", key)
		}
	}

	if semantics["quarantine"].ProbeEnabled {
		t.Fatal("expected quarantine mode to disable probe")
	}
	if !semantics["degraded_probe"].ProbeEnabled {
		t.Fatal("expected degraded_probe mode to allow probe")
	}
}
