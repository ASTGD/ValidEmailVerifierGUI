#!/usr/bin/env bash
set -euo pipefail

SCENARIO="${1:-}"

if [[ -z "${SCENARIO}" ]]; then
  cat <<'USAGE'
Usage: ./scripts/run_reliability_drill.sh <scenario>

Scenarios:
  control-plane-unreachable
  redis-degradation
  provider-tempfail-spike
  worker-pool-outage
USAGE
  exit 1
fi

echo "== Go Control-Plane Reliability Drill =="
echo "Scenario: ${SCENARIO}"
echo "Timestamp: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo

case "${SCENARIO}" in
  control-plane-unreachable)
    cat <<'EOF'
1) Stop control-plane process/service.
2) Confirm worker processing continues (Laravel fallback heartbeat still logs warnings only).
3) Restart control-plane.
4) Verify:
   - /api/health/ready returns 200
   - incidents recover
   - policy/pool controls become available again.
EOF
    ;;
  redis-degradation)
    cat <<'EOF'
1) Introduce Redis latency (local throttling/proxy) or short outage window.
2) Observe:
   - control-plane readiness degradation
   - incident creation + recovery alerts
   - no process crash loops.
3) Restore Redis and verify normal worker/pool state refresh.
EOF
    ;;
  provider-tempfail-spike)
    cat <<'EOF'
1) Simulate tempfail-heavy provider replies in smtp_probe workers.
2) Verify provider health changes to warning/critical.
3) If auto-protect enabled, verify cautious/drain transitions.
4) If canary autopilot enabled, verify rollback gates trigger when thresholds are exceeded.
EOF
    ;;
  worker-pool-outage)
    cat <<'EOF'
1) Stop one smtp_probe worker pool.
2) Verify pool under-capacity incident, desired-state handling, and queue pressure behavior.
3) Restore pool and verify recovery event and stabilization.
EOF
    ;;
  *)
    echo "Unknown scenario: ${SCENARIO}" >&2
    exit 1
    ;;
esac

echo
echo "Drill checklist printed. Record outcomes in docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md."

