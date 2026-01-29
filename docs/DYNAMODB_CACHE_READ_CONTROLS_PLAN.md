# DynamoDB Cache Read Controls — Plan

## Goal
Add admin‑only controls for DynamoDB cache reads so production can safely switch between **Provisioned** and **On‑Demand** operational profiles without code changes, while keeping connection secrets in `.env`.

This plan covers:
- Settings fields (DB + UI)
- Behavior mapping in `DynamoDbEmailVerificationCacheStore`
- Tooltips/descriptions for admin clarity

> Note: These controls do **not** change AWS capacity; they only adjust application read behavior.

---

## 1) Connection (read‑only / env‑backed)
Shown in Settings for visibility only. Values come from `.env` / `config/engine.php`.

**Fields (display only)**
- Table name
- Region
- Partition key attribute
- Result attribute
- DateTime attribute (optional)

**Purpose**
Help admins confirm the app points at the right DynamoDB table without exposing secrets.

---

## 2) Capacity Mode (UI toggle)
**Field**
- `cache_capacity_mode` (enum: `on_demand`, `provisioned`)

**UI behavior**
Switching mode shows a different set of controls:
- **On‑Demand**: optimistic settings, minimal throttling.
- **Provisioned**: strict throttling and backoff controls.

**Tooltip**
“Select which read profile the app should use. This does not change AWS capacity.”

---

## 3) Read Behavior
These settings control how fast and how efficiently we read from DynamoDB.

### Common (applies to both modes)
**Fields**
- `cache_batch_size` (int, 1–100)
- `cache_consistent_read` (bool)

**Tooltips**
- **Batch size**: “Number of emails to check per DynamoDB batch call (max 100). Higher is faster but can throttle more easily.”
- **Consistent read**: “Strongly consistent reads cost more and are slower; eventually consistent is cheaper and usually fine for cache.”

### On‑Demand‑specific
**Fields**
- `ondemand_max_batches_per_second` (int, optional)
- `ondemand_sleep_ms_between_batches` (int, optional)

**Tooltips**
- **Max batches/sec**: “Soft limit for DynamoDB batch calls per second. Leave blank to allow max throughput.”
- **Sleep between batches**: “Optional delay to smooth spikes even in On‑Demand mode.”

---

## 4) Throttle / RCU Safety (Provisioned‑specific)
These controls help prevent `ProvisionedThroughputExceededException` by throttling reads in the app.

**Fields**
- `provisioned_max_batches_per_second` (int)
- `provisioned_sleep_ms_between_batches` (int)
- `provisioned_max_retries` (int)
- `provisioned_backoff_base_ms` (int)
- `provisioned_backoff_max_ms` (int)
- `provisioned_jitter_enabled` (bool)

**Tooltips**
- **Max batches/sec**: “Hard cap on DynamoDB batch calls per second.”
- **Sleep between batches**: “Delay between batch calls to stay under RCU limits.”
- **Max retries**: “How many times to retry when DynamoDB throttles.”
- **Backoff base**: “Initial delay before retry (milliseconds).”
- **Backoff max**: “Maximum delay between retries (milliseconds).”
- **Jitter**: “Randomizes backoff delays to reduce synchronized spikes.”

---

## 5) Failure Handling
Defines what happens when DynamoDB read fails.

**Fields**
- `cache_failure_mode` (enum: `fail_job`, `treat_miss`, `skip_cache`)

**Behavior**
- **fail_job**: Stop job and mark failed (strict).
- **treat_miss**: Treat all remaining emails as cache misses (fast but less accurate).
- **skip_cache**: Bypass cache and proceed to normal verification (standard pipeline).

**Tooltip**
“Choose how the system should behave if DynamoDB is unavailable or throttled.”

---

## 6) Health Check (existing)
Health check will continue to:
- Run `DescribeTable` (if permitted)
- Optionally run **read test** with entered emails

Display:
- “Healthy/Unhealthy”
- Table status
- Read test results: found X of Y

---

## 7) Data Model (Engine Settings)
Add columns to `engine_settings`:
- `cache_capacity_mode`
- `cache_batch_size`
- `cache_consistent_read`
- `ondemand_max_batches_per_second`
- `ondemand_sleep_ms_between_batches`
- `provisioned_max_batches_per_second`
- `provisioned_sleep_ms_between_batches`
- `provisioned_max_retries`
- `provisioned_backoff_base_ms`
- `provisioned_backoff_max_ms`
- `provisioned_jitter_enabled`
- `cache_failure_mode`

Defaults:
- `cache_capacity_mode = on_demand`
- `cache_batch_size = 100`
- `cache_consistent_read = false`
- `ondemand_max_batches_per_second = null`
- `ondemand_sleep_ms_between_batches = 0`
- `provisioned_max_batches_per_second = 5`
- `provisioned_sleep_ms_between_batches = 100`
- `provisioned_max_retries = 5`
- `provisioned_backoff_base_ms = 200`
- `provisioned_backoff_max_ms = 2000`
- `provisioned_jitter_enabled = true`
- `cache_failure_mode = fail_job`

---

## 8) Implementation Notes
- Extend `DynamoDbEmailVerificationCacheStore` to:
  - Respect `cache_batch_size` and `cache_consistent_read`
  - Apply throttling + backoff based on `cache_capacity_mode`
  - Honor `cache_failure_mode` on errors
- Update `ParseAndChunkJob` to use cache store response and failure mode.
- Log throttling events (`cache_lookup_throttled`) for visibility.
- Keep secrets in `.env`, only surface read‑only connection info in Settings.

---

## 9) Optional: AWS Control via CLI/SDK
**Possible** but **not recommended**:
- You *can* call AWS SDK to update table capacity or switch modes.
- Requires elevated IAM permissions.
- Increases risk of misconfiguration from app UI.

**Recommended**: manage AWS capacity externally (AWS Console / Terraform / scripts).
