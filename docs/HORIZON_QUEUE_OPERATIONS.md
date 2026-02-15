# Horizon Queue Operations

## Purpose
Horizon is the queue execution and visibility layer for Laravel lanes:
- `default`
- `prepare`
- `parse`
- `smtp_probe`
- `finalize`
- `imports`
- `cache_writeback`
- SG6 lanes

## What To Watch
1. Queue depth per lane
2. Wait time / oldest job age
3. Failed jobs by lane and class
4. Supervisor status (must stay active)

## Standard Recovery Sequence
1. `php artisan horizon:terminate`
2. Process manager restarts Horizon automatically.
3. Verify status and supervisors:
   - `php artisan horizon:status`
   - `php artisan horizon:supervisors`
4. Requeue only impacted failures, not full backlog.

## Tuning Guidance
- If `finalize` latency rises while `parse` spikes, increase parse capacity only if finalize stays protected.
- Keep `retry_after > timeout + safety buffer` to avoid duplicate execution.
- For persistent backlog, tune per-lane supervisor processes before changing global settings.
