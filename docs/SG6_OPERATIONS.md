# SG6 Seed-Send Operations

## Scope
SG6 is a separate, consent-gated evidence workflow. It does not replace primary verification output automatically.

## Preconditions
1. Verification job status is `completed`.
2. Customer consent exists and is approved.
3. SG6 add-on credits are available.
4. Webhook signature validation is healthy.

## Admin Workflow
1. Approve consent.
2. Start SG6 campaign.
3. Monitor dispatch/events/reconcile lanes.
4. Pause/cancel if policy thresholds are breached.
5. Download SG6 evidence report after completion.

## Safety Rules
- No consent: no send.
- Webhook integrity failure: auto pause.
- Use campaign-level reconciliation before settlement close.
