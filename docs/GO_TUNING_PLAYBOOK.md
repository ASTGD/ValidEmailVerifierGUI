# Go Tuning Playbook

## Scenario 1: Unknown Rate Spike
1. Confirm sample size per provider is healthy.
2. Set provider to `cautious` (manual override) for impacted provider.
3. Pause canary autopilot if rollout is in progress.
4. Tighten unknown guard only after stabilization.
5. If caused by new policy version, rollback policy.

## Scenario 2: Tempfail Recovery Collapse
1. Check provider tempfail trend and retry effectiveness.
2. Enable/confirm adaptive retry is on.
3. Reduce aggressive rollout and hold current canary.
4. If one pool is degraded, drain/quarantine that pool slice.

## Scenario 3: Autoscale Flapping
1. Increase `autoscale_cooldown_seconds`.
2. Increase `autoscale_interval_seconds` if still unstable.
3. Verify min/max desired values are realistic.
4. Re-check after one hour of traffic.

## Scenario 4: Policy-Block Spike
1. Shift provider to `cautious` immediately.
2. If continuing, move provider to `drain`.
3. Rollback canary policy if regression coincides with rollout.
4. Keep manual override until stable windows return.

## Scenario 5: Alerts Too Noisy
1. Increase `alert_cooldown_seconds`.
2. Review stale worker cleanup and desired-state grace values.
3. Do not increase error threshold aggressively without evidence.
