# Go Reliability Drill Report Template

Use this template after each Go control-plane/worker reliability drill.

## Drill Metadata
- Date (UTC):
- Scenario:
- Environment:
- Drill owner:
- Participants:

## Objective
- What condition was simulated:
- Expected detection signal:
- Expected mitigation path:

## Timeline
- `T0` (fault injected):
- `T_detected` (incident/alert observed):
- `T_mitigated` (first mitigation applied):
- `T_recovered` (system healthy again):

## Detection and Signals
- Alerts/incidents raised:
- Queue/worker/provider metrics affected:
- Dashboard pages/endpoints checked:

## Actions Taken
1. 
2. 
3. 

## Outcome
- Detection time (seconds):
- Mitigation time (seconds):
- Recovery time (seconds):
- SLA impact summary:

## Validation Checklist
- [ ] Worker desired-state controls still worked.
- [ ] Finalize/smtp_probe queues remained within expected limits.
- [ ] No data loss observed.
- [ ] Incident moved to recovered state.
- [ ] Follow-up action items recorded.

## Follow-ups
- Immediate fixes:
- Backlog items:
- Runbook updates needed:
