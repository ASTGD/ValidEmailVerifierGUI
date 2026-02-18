package main

func defaultProviderModeSemantics() map[string]ProviderModeSemantics {
	return map[string]ProviderModeSemantics{
		"normal": {
			ProbeEnabled:                true,
			MaxConcurrencyMultiplier:    1,
			ConnectsPerMinuteMultiplier: 1,
		},
		"cautious": {
			ProbeEnabled:                true,
			MaxConcurrencyMultiplier:    0.65,
			ConnectsPerMinuteMultiplier: 0.60,
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
			MaxConcurrencyMultiplier:    0.40,
			ConnectsPerMinuteMultiplier: 0.50,
		},
	}
}
