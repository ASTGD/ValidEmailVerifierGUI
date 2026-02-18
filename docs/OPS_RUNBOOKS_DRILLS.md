# Ops Runbooks and Drills

## Core Recovery Order
1. Stabilize queue/finalization path.
2. Protect provider reputation (cautious/drain).
3. Rollback risky policy changes.
4. Recover throughput after stability is restored.

## Drill Scenarios
1. Control-plane unreachable.
2. Redis latency/degradation.
3. Provider tempfail spike.
4. Worker pool outage.

## Drill Output
Use:
- `docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md`

Record:
1. Detection time
2. First action time
3. Recovery time
4. Customer-facing impact
5. Preventive follow-up
