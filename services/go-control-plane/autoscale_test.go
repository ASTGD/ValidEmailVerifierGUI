package main

import "testing"

func TestCalculatePoolTargetDesiredNoDownscaleWhenUnderCapacity(t *testing.T) {
	pool := PoolSummary{
		Pool:        "smtp_probe",
		Online:      1,
		Desired:     4,
		HealthScore: 0.60,
	}

	target := calculatePoolTargetDesired(pool, 0, 1, 8)
	if target != pool.Desired {
		t.Fatalf("expected desired to stay %d during under-capacity, got %d", pool.Desired, target)
	}
}

func TestCalculatePoolTargetDesiredDownscalesOnLowLoadWhenCapacityHealthy(t *testing.T) {
	pool := PoolSummary{
		Pool:        "screening",
		Online:      5,
		Desired:     5,
		HealthScore: 0.75,
	}

	target := calculatePoolTargetDesired(pool, 0, 1, 8)
	if target != 4 {
		t.Fatalf("expected downscale to 4 on healthy low-load pool, got %d", target)
	}
}
