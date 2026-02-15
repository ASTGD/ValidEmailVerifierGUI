# Go Runtime Settings Reference

This page explains each field in **Go Panel -> Settings -> Runtime Safety Settings**.

Use this baseline profile unless there is a measured incident:
- Profile: **Balanced**
- Change style: one setting block at a time
- Observation rule: wait one full window before next change

## Core Toggles

| Setting key | What it controls | Why | Standard value | If increased/enabled | If decreased/disabled | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `alerts_enabled` | Incident alert emission | No alerts means silent failures | `true` | More visibility | Missed incidents | Incident count, MTTA | Critical incident detected late |
| `auto_actions_enabled` | Auto drain/quarantine actions | Reduces manual response time | `true` after warm-up | Faster containment | Manual-only response | Auto action logs | Healthy workers are drained repeatedly |
| `autoscale_enabled` | Pool desired auto-adjustments | Keeps capacity aligned to load | `true` | Better burst handling | Backlog can grow | Queue latency, pool desired vs online | Flapping or unstable desired counts |
| `provider_policy_engine_enabled` | Provider rule logic in probe controls | Improves provider-specific behavior | `true` | Better provider handling | Generic behavior only | Unknown/tempfail by provider | Unknown rate worsens after enabling |
| `adaptive_retry_enabled` | Provider-aware retry pacing | Improves tempfail recovery | `true` | Better recovery | Retry becomes blunt/static | Tempfail recovery rate | Retry waste increases sharply |
| `provider_autoprotect_enabled` | Auto mode shift (`normal/cautious/drain`) | Protects pool reputation automatically | `true` | Faster protective mode shifts | Slower/manual mitigation | Provider mode transitions | False mode flips become frequent |
| `policy_canary_autopilot_enabled` | Automatic canary progression/rollback | Safer policy rollout automation | `false` initially | Less manual rollout work | Manual rollout only | Canary health windows | Unexpected rollback/progression churn |

## Alerting and Reliability

| Setting key | What it controls | Why | Standard value | If increased | If decreased | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `alert_error_rate_threshold` | Worker error/min threshold | Controls sensitivity | `10` | Fewer alerts | More alerts/noise | Error-rate alert frequency | Real errors are not alerted early |
| `alert_heartbeat_grace_seconds` | Offline grace window | Avoid false offline alerts | `120` | More tolerant to jitter | Faster offline detection | Worker offline incidents | Frequent false offline incidents |
| `alert_cooldown_seconds` | Alert dedupe cooldown | Prevents spam | `300` | Less spam, slower repeat alerts | More repeats/noise | Alert volume | Alert flood after single event |
| `alert_check_interval_seconds` | Alert loop frequency | Detection speed vs load | `30` | Lower control-plane load | Faster detection, more load | CPU, Redis ops, detection delay | Control-plane load spikes |
| `stale_worker_ttl_seconds` | Remove stale worker metadata | Prevent stale routing and incident drift | `86400` | Stale records remain longer | Faster stale cleanup | Stale removals/day | Active workers removed incorrectly |
| `stuck_desired_grace_seconds` | Desired-state convergence timeout | Detect stuck pause/drain transitions | `600` | Slower stuck detection | Faster stuck detection | `worker_stuck_desired` incidents | Too many false stuck incidents |
| `quarantine_error_rate_threshold` | Auto quarantine threshold | Hard stop for high-error workers | `15` | Fewer quarantines | More quarantines | Quarantine actions | Healthy capacity drops due to over-quarantine |

## Provider Health Thresholds

| Setting key | What it controls | Why | Standard value | If increased | If decreased | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `provider_tempfail_warn_rate` | Warn line for tempfail | Early provider instability signal | `0.30` | Later warning | Earlier warning | Tempfail trend/provider | Warning misses visible degradation |
| `provider_tempfail_critical_rate` | Critical line for tempfail | Drives critical state | `0.55` | Fewer critical states | More critical states | Critical provider incidents | Critical incidents over-trigger |
| `provider_reject_warn_rate` | Warn line for rejects | Detect reject trend | `0.20` | Later warning | Earlier warning | Reject trend/provider | Reject warning too late |
| `provider_reject_critical_rate` | Critical line for rejects | Protect against block conditions | `0.40` | Fewer critical mode shifts | More critical mode shifts | Reject critical incidents | Frequent unnecessary drains |
| `provider_unknown_warn_rate` | Warn line for unknown | Accuracy drift detection | `0.20` | Later unknown warning | Earlier unknown warning | Unknown rate/provider | Unknown spike not caught early |
| `provider_unknown_critical_rate` | Critical line for unknown | Strong accuracy protection | `0.35` | Fewer critical unknown alerts | More critical unknown alerts | Unknown critical incidents | Sustained false critical state |

## Autoscale Guardrails

| Setting key | What it controls | Why | Standard value | If increased | If decreased | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `autoscale_interval_seconds` | Autoscale evaluation frequency | Reaction speed vs stability | `30` | Slower reaction | Faster reaction, can flap | Desired change count/hour | Repeated oscillation |
| `autoscale_cooldown_seconds` | Time between scale actions per pool | Anti-flap protection | `120` | More stable, slower correction | Faster correction, more flaps | Scale action frequency | Pool desired churn spikes |
| `autoscale_canary_percent` | Percent of pools autoscale can touch | Controlled rollout scope | `100` | Broader impact | Narrower impact | Pools adjusted | Backlog grows in excluded pools |
| `autoscale_min_desired` | Lower bound per pool | Maintains base capacity | `1` | Higher idle cost | Lower idle capacity | Idle workers, queue latency | Queues age during low traffic |
| `autoscale_max_desired` | Upper bound per pool | Caps burst spend/capacity | `4` | Higher burst capacity/cost | Lower peak throughput | Pool backlog drain speed | Burst backlog fails to drain |

## Canary Autopilot Gates

| Setting key | What it controls | Why | Standard value | If increased | If decreased | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `policy_canary_window_minutes` | KPI evaluation window | Smooth noise vs speed | `15` | Slower but smoother decisions | Faster but noisier decisions | Window-to-window variance | Autopilot becomes unstable |
| `policy_canary_required_health_windows` | Healthy windows required before step-up | Promotion confidence | `4` | Slower promotions | Faster promotions | Canary progression pace | Regressions after fast step-ups |
| `policy_canary_min_provider_workers` | Minimum provider sample gate | Prevent low-sample decisions | `1` | Requires more volume | Allows decisions on smaller samples | Sample volume per provider | Decisions made on weak data |
| `policy_canary_unknown_regression_threshold` | Max allowed unknown increase vs baseline | Accuracy guardrail | `0.05` | More tolerant | Stricter | Unknown delta | Unknown rises past threshold |
| `policy_canary_tempfail_recovery_drop_threshold` | Max allowed tempfail recovery drop | Retry quality guardrail | `0.10` | More tolerant | Stricter | Recovery delta | Recovery falls persistently |
| `policy_canary_policy_block_spike_threshold` | Max allowed policy-block spike | Reputation safety guardrail | `0.10` | More tolerant | Stricter | Policy-block delta | Policy-block spikes continue |

## Live UI Refresh Intervals

| Setting key | What it controls | Why | Standard value | If increased | If decreased | Monitor | Rollback when |
|---|---|---|---|---|---|---|---|
| `ui_overview_live_interval_seconds` | Overview refresh cadence | Perceived freshness vs load | `5` | Less UI load | More UI load | Browser/network usage | Page becomes laggy |
| `ui_workers_refresh_seconds` | Workers page refresh | Worker status freshness | `10` | Less polling load | More polling load | API request volume | Status feels stale |
| `ui_pools_refresh_seconds` | Pools page refresh | Capacity visibility | `10` | Less polling load | More polling load | API request volume | Scaling actions feel delayed |
| `ui_alerts_refresh_seconds` | Alerts page refresh | Incident timeline freshness | `30` | Less polling load | More polling load | Alerts API usage | Alerts appear delayed |

## First 7 Days Admin Tuning Guide

1. Keep baseline values for first 24h.
2. Enable `policy_canary_autopilot_enabled` only after stable baseline.
3. If unknown rises:
   - reduce rollout aggressiveness (higher required windows)
   - tighten unknown thresholds only if sample size is healthy.
4. If pool flaps:
   - increase `autoscale_cooldown_seconds`
   - optionally increase `autoscale_interval_seconds`.
5. If alert noise is high:
   - increase cooldown first
   - avoid raising error threshold too fast.

## Safe Change Protocol

1. Change one setting block only.
2. Record reason and expected outcome.
3. Observe for one full evaluation window.
4. If KPI worsens:
   - revert immediately to previous value
   - annotate incident and follow runbook.
