# Context Handoff (ValidEmailVerifierGUI)

Last updated: 2026-02-26

This document is the handoff bundle for a fresh workspace. It summarizes the current system state, key decisions, and how to resume work without losing context. Do not place secrets in this file.

## 1) Current status (high level)
- Core platform is running on Laravel 12 + Livewire (portal) + Filament (admin) with Go workers for verification.
- Customer-facing verification is a single product flow (no mode selector in portal).
- Internally, verification runs a staged pipeline: `screening -> smtp_probe -> finalize`.
- Output CSV schema is: `email,status,sub_status,score,reason`.
- S3 storage is supported for uploads/results in local dev and production (storage disk is configurable).
- DynamoDB cache read (cache-only mode) is implemented and tested with a real table.
- DynamoDB cache write-back (miss-only, valid/invalid) is implemented with admin settings and retries.
- Cache write-back test mode (cache-only) can write `Cache_miss` to a separate DynamoDB test table.
- Engine monitoring (RBL checks) is implemented with `engine-monitor-go` and admin UI.
- Go control-plane UI has finalized IA:
  - Workers: runtime scheduling controls only.
  - Provisioning: onboarding/re-provisioning wizard only.
  - Servers: inventory + diagnostics + infrastructure process controls.
  - Pools: first-class server groups with provider profiles.
- Admin Ops Overview dashboard is available with system, queue, engine, and job health widgets.
- System + queue metrics are collected via scheduled commands and stored for trend charts.
- Developer Tools page provides capacity, queue pressure, poll load, and cost estimators.
- Global Settings include DevTools toggles (environment-scoped).

## 2) Tech stack + environment
- Laravel 12, PHP 8.4 (Sail mandatory), MySQL, Livewire (portal), Filament (admin), Sanctum (API auth).
- Local app port: 8082.
- Storage: local for dev, S3 for production (disk-configurable).
- Go worker: `engine-worker-go` via Docker image (GHCR) + provisioning bundle.
- Monitor: `engine-monitor-go` service (systemd supported).
- Go control-plane is primary operational heartbeat source; Laravel heartbeat is fallback identity/liveness.

## 3) Important docs
- `docs/README.md`
- `docs/ENGINE_CONTRACT.md`
- `docs/DEEP_VERIFICATION_ENGINE_PLAN.md`
- `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`
- `docs/S3_SETUP.md`
- `docs/DYNAMODB_CACHE_READ_CONTROLS_PLAN.md`

## 4) Verification pipeline (control plane)
- Upload list (portal) -> `VerificationJob` created.
- `PrepareVerificationJob` -> `ParseAndChunkJob`:
  - Normalize/dedupe
  - Cache lookup (EmailVerificationCacheStore)
  - Create stage-1 chunks (`screening`)
- Go worker processes stage-1 chunks, and screening handoff creates stage-2 probe chunks when policy allows.
- Go worker processes stage-2 probe chunks and posts results.
- Finalization merges outputs and computes score.

## 5) Internal policy stages
- `verification_mode` remains an internal compatibility field, but customer-facing mode selection is removed.
- Screening runs first for all jobs; SMTP probe runs by internal policy gates and worker capability routing.
- Catch-all policy is admin-only:
  - `risky_only` or `promote_if_score_gte` threshold.

## 6) Output schema + scoring
- Final output CSV header: `email,status,sub_status,score,reason`.
- Score computed in Laravel finalization (0-100). Scoring uses deterministic mapping from reason and cache outcomes.
- Backward-compatible with old `email,reason` chunk outputs.

## 7) Single Email Test (portal)
- Single-check uses the same pipeline as list uploads (job origin = `single_check`).
- Results are shown in the portal; job visibility can be hidden by admin setting.
- No customer mode selection is exposed; internal policy gates decide probe execution.

## 8) Engine server provisioning (worker)
- Primary operator path is now Go panel (`/verifier-engine-room/*`), not Filament daily UI.
- Provisioning wizard (`/verifier-engine-room/provisioning`) handles:
  - create/select server
  - generate short-lived bundle
  - install command
  - post-install verification checks
- Servers page (`/verifier-engine-room/servers`) handles inventory visibility and manage/edit flows.
- Server Manage page (`/verifier-engine-room/servers/{id}`) handles infrastructure controls:
  - `start`, `stop`, `restart`, `status`
- Workers page (`/verifier-engine-room/workers`) handles runtime scheduler controls:
  - `pause/resume`, `drain`, `quarantine`

## 9) S3 storage (uploads/results)
- S3 support is working in local dev.
- Required env keys are documented in `docs/S3_SETUP.md`.
- Livewire uploads require Flysystem AWS v3 dependency (already added).
- Use `php artisan config:clear` after .env updates.

## 10) DynamoDB cache (read + write-back)
- DynamoDB cache store is implemented (DynamoDbEmailVerificationCacheStore).
- Cache-only mode (test mode) works: cache hit -> classified, miss -> configured status.
- Health check supports:
  - DescribeTable
  - Optional read test with user-provided emails
- Settings support read controls:
  - On-Demand vs Provisioned view toggle
  - Consistency (eventual vs consistent)
  - Batch size, max batches/min, retry/backoff
  - Failure handling (fail job / treat miss / skip cache)
- Write-back (optional):
  - Cache-miss emails tracked during parsing and stored in `cache_miss_key`.
  - Finalization streams merged outputs and writes only cache misses (valid/invalid) to DynamoDB.
  - Settings include enable toggle, batch size (<=25), soft throttle, retry/backoff, failure mode.
  - Test mode (cache-only) writes cache misses to a separate test table with result `Cache_miss`.
- Table in test: `emailresources` (partition key: `email` string), region `us-east-1`.
  - Attributes observed: `result`, `DateTime`.

## 11) Monitor (RBL) system
- `engine-monitor-go` fetches config from Laravel and posts check results.
- Admin UI: Operations -> Reputation Checks + Blacklist Events.
- RBL list is configurable in settings.
- Spamhaus requires a proper resolver (system DNS or custom DNS resolver).
- Settings allow System DNS (default) or Custom DNS (IP/port).

## 12) Known fixes already merged
- S3 driver error: `League\Flysystem\AwsS3V3\PortableVisibilityConverter` fixed by installing Flysystem AWS v3.
- Multiple Filament form closure errors (`Filament\Forms\Get` vs `Filament\Schemas\Components\Utilities\Get`) fixed in settings/delist pages.
- Provisioning bundle now respects `APP_URL` and does not hardcode localhost.
- Engine Settings UI consolidated under Operations group.

## 13) Test commands (local)
- Migrate: `./vendor/bin/sail artisan migrate`
- Queue worker: `./vendor/bin/sail artisan queue:work --timeout=1800`
- Tests: `./vendor/bin/sail test`

## 14) Latest Go segment update (2026-02-26)
- Phase 22.5 merged: Pools are first-class server groups.
- Added Laravel pool model + API + migrations:
  - `engine_worker_pools`
  - `engine_servers.worker_pool_id`
- Go UI finalized with left-nav grouped IA (Ops / Infra / Governance).
- Provisioning is wizard-only; Servers list is inventory-only with Manage deep-link.
- Workers page remains runtime-only controls.
- Pool/provider profile policy overlay now affects claim routing and worker provider-policy fetch.
- Migration-state blocker resolved; full test suite green (`210 passed`).

## 15) Go closeout status before Filament focus
- Completed:
  - Legacy `/ui/*` compatibility routes removed.
  - Legacy workers-registry mixed template removed.
  - Runtime vs infrastructure control semantics finalized in Go UI copy.
  - Provisioning Step 4 now runs explicit `claim-next` API auth probe (validation-only payload, no chunk claim).
  - Docs/runbooks aligned with current Go IA.
- Operator sign-off still required (manual on real VPS):
  - final multi-VPS regression sweep using checklist in `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`.

## 16) Pre-release flag burn-down plan (make v1 permanent)
- Goal: before first production release, remove temporary rollout feature flags and keep only operational/safety flags.
- Completed in current Go UI segment:
  - Removed `GO_WORKER_OPS_REORG_ENABLED` runtime branching.
  - Removed `GO_SERVER_INVENTORY_REARCH_ENABLED` runtime branching.
  - Removed `GO_SERVER_REGISTRY_UI_ENABLED` toggle behavior (server registry UI path is now permanent).
- Pending across other segments (track and remove before release):
  - Audit all `*_ENABLED` feature-cutover flags added during dev-only migrations.
  - Convert finalized features to permanent defaults in code.
  - Keep only true runtime controls (timeouts, retry, thresholds, safeguards).
  - Delete obsolete env keys from `.env.example` after each conversion.


---

## Update log

### 2026-02-26 — Go Phase 22.5 + migration/test hardening (main)
- Added pool domain model and Laravel internal APIs:
  - `EngineWorkerPool` model
  - `EnginePoolController` (+ upsert request)
  - CRUD/archive/set-default with guard rules
- Added DB changes:
  - `engine_worker_pools` table
  - `engine_servers.worker_pool_id` foreign key + backfill to default pool
- Added provisioning + server payload support for pool assignment and `WORKER_POOL` in generated bundle env.
- Added pool profile policy service and pool-aware routing/provider-policy behavior in:
  - claim-next scoring
  - Go control-plane provider policy endpoint
  - Go worker policy fetch path
- Finalized Go UI IA:
  - workers runtime-only
  - provisioning wizard-only
  - servers inventory/manage/edit
  - pools first-class page
- Hardened duplicate checkout-intent migrations for safe re-run/state drift.
- Hardened base test state to ensure required engine defaults are present after schema dumps.
- Full verification:
  - Laravel migrations pass
  - full Laravel suite pass (`210 passed`)
  - Go control-plane tests pass
  - Go worker tests pass

### 2026-02-26 — Go closeout pass (legacy cleanup + provisioning auth probe)
- Removed legacy Go UI compatibility surface:
  - deleted `/ui/*` route aliases
  - removed old workers-registry template fragments
- Cleaned control semantics:
  - workers page copy now explicitly runtime-only
  - servers manage page copy now explicitly infrastructure-only
- Provisioning Step 4 hardening:
  - added explicit `claim-next` auth probe using latest bundle `worker.env` token
  - probe uses validation-only payload to avoid claiming chunks
  - UI now shows probe status (`pass|pending|fail`) and detail message
- Added test coverage in Go control-plane for probe pass/fail cases.
- Added final VPS regression + Go release freeze checklists to `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`.

### 2026-02-19 — Docs cleanup and reorganization (feature/docs-cleanup-reorg)
- Consolidated Go UI reference into `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`.
- Moved historical docs into `docs/archive/`:
  - `ADMIN_UI_PLAN.md`
  - `SG5_AND_MONITORING_PLAN.md`
  - `deep-verification-engine-plan.md` (legacy full copy)
  - `GO_WORKER_DASHBOARD_UI.md` (legacy full copy)
- Added canonical docs map: `docs/README.md`.
- Added PM/Admin-facing operational changelog: `docs/OPS_CHANGELOG.md`.
- Internal docs portal release-notes page now points to `docs/OPS_CHANGELOG.md` instead of `docs/CONTEXT_HANDOFF.md`.

### 2026-02-01 — Settings tabs (feature/settings-tabs)
- Reorganized Engine Settings form into Filament tabs: Verification, Cache (DynamoDB), Monitoring.
- All existing cards/sections were grouped under these tabs.
- File updated: `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`.

### 2026-02-01 — Fix Filament Tabs class
- Tabs/Tab imports corrected to use Filament\Schemas\Components\Tabs.*
- Fixes "Class Filament\\Forms\\Components\\Tabs not found" on settings page.

### 2026-02-01 — Settings tabs layout width
- Wrapped each settings tab content in a Grid (2 columns on lg) for full-width layout.
- File updated: `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`.

### 2026-02-01 — Settings page full-width layout
- Set Engine Settings edit page to use full-width layout via maxContentWidth.
- File updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`.

### 2026-02-01 — Settings width 1280px + 3-column tabs
- Settings page max width set to 1280px.
- Tab grids updated to 3 columns on large screens.
- Files updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`, `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`.

### 2026-02-01 — Fix maxContentWidth type
- Engine Settings maxContentWidth set to Filament Width::ScreenExtraLarge to satisfy type constraints.
- File updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`.

### 2026-02-01 — Settings width fixed to 1280px
- Engine Settings maxContentWidth set to explicit 1280px string with correct type.
- File updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`.

### 2026-02-01 — Settings page switched to full width
- Engine Settings maxContentWidth set to Width::Full to remove right-side empty space.
- File updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`.

### 2026-02-01 — Tabs container full width
- Added `w-full` class to the tabs container to ensure it spans the full content width.
- File updated: `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`.

### 2026-02-01 — Tabs span full form width
- Tabs component now `columnSpanFull()` to occupy full form grid width.
- File updated: `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`.

### 2026-02-01 — Settings page full width override (method)
- Replaced maxContentWidth property with getMaxContentWidth() override returning Width::Full.
- File updated: `app/Filament/Resources/EngineSettings/Pages/EditEngineSetting.php`.

### 2026-02-01 — Cache miss tracking for write-back
- Parse stage writes cache-miss emails to `cache_miss_key` (emails.txt) and stores the key on jobs.
- Files updated: `app/Jobs/ParseAndChunkJob.php`, `app/Services/JobStorage.php`, migration added.

### 2026-02-01 — DynamoDB cache write-back
- Finalization streams merged results and writes only cache-miss valid/invalid rows to DynamoDB.
- Added admin settings for write-back (toggle, statuses, batch size, throttle, retries, failure mode).
- Tests added for write-back filtering and disabled behavior.
- Files updated: `app/Jobs/FinalizeVerificationJob.php`, `app/Services/EmailVerificationCache/DynamoDbCacheWriteBackService.php`, `app/Support/EngineSettings.php`, `app/Models/EngineSetting.php`, `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`, migrations + tests.

### 2026-02-01 — Write-back docs updates
- Updated engine contract and cache plan notes for write-back.
- Files updated: `docs/ENGINE_CONTRACT.md`, `docs/DYNAMODB_CACHE_READ_CONTROLS_PLAN.md`.

### 2026-02-01 — Cache write-back test mode
- Added test mode toggle + test table setting for cache write-back.
- Cache-only runs now record cache-miss emails for test writes.
- Tests cover test-mode writes.
- Files updated: `app/Services/EmailVerificationCache/DynamoDbCacheWriteBackService.php`, `app/Jobs/ParseAndChunkJob.php`, `app/Support/EngineSettings.php`, `app/Filament/Resources/EngineSettings/Schemas/EngineSettingForm.php`, migrations + tests.

### 2026-02-02 — Admin ops observability (feature/admin-ops-observability)
- Added Ops Overview page with system/queue/engine/job health widgets and trend charts.
- Added system + queue metrics collectors (scheduled every minute).
- Added per-job metrics tracking and progress bars in admin jobs list/detail view.
- Logs section collapsed by default in job detail view.
- Fixed Ops Overview charts to use reindexed data arrays; progress bar now has fixed width in tables.
- Files updated: new metrics models/migrations, `app/Services/Metrics/*`, `app/Services/JobMetricsRecorder.php`, `app/Filament/Pages/OpsOverview.php`, `app/Filament/Widgets/Ops*`, `app/Filament/Resources/VerificationJobs/*`, `bootstrap/app.php`, `config/engine.php`, `app/Support/EngineSettings.php`, `app/Models/*`.

### 2026-02-03 — Redis queue + Horizon scaffolding (feature/redis-horizon-queue)
- Added Redis service to Sail compose and switched queue/cache defaults to Redis in `.env.example`.
- Installed Laravel Horizon with admin-only access gate (requires Horizon enabled in settings).
- Added Engine Settings -> Queue Engine tab to control queue connection, cache store, and Horizon toggle.
- Runtime config overrides apply queue/cache settings from Engine Settings, with Redis availability fallback to avoid errors.
- Added Operations menu link **Queue Engine** that opens Horizon in a new tab.

### 2026-02-05 — Ops queue UX improvements (feature/redis-queue-ops)
- Added Ops Overview widgets: Queue quick links, queue status, and Redis fallback alert banner.
- Added Queue Engine worker tuning fields and command helpers (Horizon + queue:work).
- Added DB columns for queue worker settings on engine_settings.

### 2026-02-05 — Verifier Engine dashboard (feature/verifier-engine-control-center)
- Removed Queue Engine quick links from Ops Overview.
- Added Verifier Engine dashboard page with engine health, warmup stats, activity table, and quick links to Engine Servers.
- Renamed Verifier Engine dashboard to "Verifier Engine Room" and paired it with "Queue Engine Room".
- Engine Servers navigation hidden from sidebar (still accessible from Verifier Engine Room).
- Files updated: `app/Filament/Pages/OpsOverview.php`, `app/Filament/Pages/VerifierEngine.php`, `app/Filament/Widgets/OpsVerifierEngineLinks.php`, `resources/views/filament/widgets/ops-verifier-engine-links.blade.php`, `app/Filament/Resources/EngineServers/EngineServerResource.php`, `app/Providers/Filament/AdminPanelProvider.php`.

### 2026-02-05 — Hosting plan (EC2 + CyberPanel)
- Documented single-host deployment plan for Laravel + Horizon + Redis + Go workers under CyberPanel.
- Files updated: `docs/HOSTING_PLAN.md`.

### 2026-02-05 — Go worker control plane + dashboard specs
- Added control plane API spec and Go dashboard UI plan for a Horizon-style Go worker control center.
- Files updated: `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`, `docs/GO_WORKER_DASHBOARD_UI.md`.

### 2026-02-05 — Go control plane (Phase 1)
- Added Go control plane service skeleton (API endpoints + Redis storage) under `services/go-control-plane`.
- Includes heartbeat handling, desired state commands, pool scaling, and auth middleware.
- Files updated: `services/go-control-plane/*`, `docs/GO_WORKER_CONTROL_PLANE_SPEC.md`.

### 2026-02-05 — Go control plane UI (Phase 2)
- Added Go-native dashboard UI (overview, workers, pools) with Tailwind + Chart.js assets.
- Added UI handlers, templates, and asset build pipeline under `services/go-control-plane`.
- Files updated: `services/go-control-plane/templates/*`, `services/go-control-plane/ui/*`, `services/go-control-plane/assets/*`, `services/go-control-plane/ui_handlers.go`, `services/go-control-plane/views.go`, `services/go-control-plane/server.go`, `services/go-control-plane/http_helpers.go`.

### 2026-02-05 — Go dashboard branded path
- Updated Go control plane UI to serve under `/verifier-engine-room/*` with legacy `/ui/*` redirects.
- Files updated: `services/go-control-plane/server.go`, `services/go-control-plane/ui_handlers.go`, `services/go-control-plane/templates/*`, `services/go-control-plane/views.go`, `services/go-control-plane/README.md`, `docs/GO_WORKER_DASHBOARD_UI.md`.

### 2026-02-05 — Go control plane Phase 3 snapshots
- Added MySQL snapshot storage for worker/pool history and overview trend chart.
- Added Laravel migrations for `go_worker_snapshots` and `go_pool_snapshots`.
- Files updated: `services/go-control-plane/snapshots*.go`, `services/go-control-plane/main.go`, `services/go-control-plane/config.go`, `services/go-control-plane/templates/overview.html`, `services/go-control-plane/.env.example`, `database/migrations/*`, docs updates.

### 2026-02-05 — Go control plane Phase 4 alerts
- Added alert checks (offline workers, pool under-capacity, error-rate threshold) with optional auto-actions.
- Added Slack + SMTP notifications and MySQL alert storage.
- Added Laravel migration for `go_alerts` and new env settings in control plane `.env.example`.
- Files updated: `services/go-control-plane/alerts.go`, `services/go-control-plane/notifiers.go`, `services/go-control-plane/store.go`, `services/go-control-plane/snapshots_store.go`, `services/go-control-plane/config.go`, `services/go-control-plane/main.go`, `database/migrations/*`, docs updates.

### 2026-02-07 — Go control plane Phase 5 polish + safety
- Added protected Prometheus metrics endpoint at `/metrics`.
- Added SSE stream for UI live updates at `/verifier-engine-room/events`.

### 2026-02-11 — Go Phase 17 closure (policy safety + rotation observability + autopilot)
- Added strict policy payload safety coupling in Go control-plane:
  - promote/rollback preflight now fetches policy payload from Laravel and validates required schema.
  - rollout metadata now stores validation state, checksum, and validation timestamp.
- Added policy lifecycle controls:
  - new control-plane `validate` action and UI flow.
  - Laravel `smtp_policy_versions` lifecycle fields (`validation_status`, errors, validated_at/by) with admin validation action.
- Added routing-quality observability:
  - worker routing counters in heartbeat payload.
  - control-plane aggregate panel + Prometheus routing metrics.
- Added KPI canary autopilot foundation:
  - step progression (`5 -> 25 -> 50 -> 100`) with healthy-window gating.
  - auto-rollback gates for unknown regression, tempfail-recovery drop, and policy-block spikes.
  - manual override protection.
- Added reliability drill tooling:
  - `services/go-control-plane/scripts/run_reliability_drill.sh`
  - `docs/GO_RELIABILITY_DRILL_REPORT_TEMPLATE.md`
- Added Alerts page (`/verifier-engine-room/alerts`) reading `go_alerts` records.
- Added Settings page (`/verifier-engine-room/settings`) backed by Redis runtime settings (no `.env` edits).
- Updated alert service to read runtime settings dynamically (alerts toggle, auto-actions toggle, thresholds/cooldowns).
- Files updated: `services/go-control-plane/server.go`, `services/go-control-plane/ui_handlers.go`, `services/go-control-plane/templates/*`, `services/go-control-plane/metrics.go`, `services/go-control-plane/stats.go`, `services/go-control-plane/store.go`, `services/go-control-plane/alerts.go`, `services/go-control-plane/snapshots_store.go`, `services/go-control-plane/views.go`, `services/go-control-plane/main.go`, `services/go-control-plane/README.md`.

### 2026-02-05 — Local control plane runtime state (not committed)
- Control plane running on `http://localhost:9091` with branded UI at `/verifier-engine-room/overview`.
- Local `services/go-control-plane/.env` contains:
  - `CONTROL_PLANE_TOKEN=<local-secret-not-committed>`
  - `MYSQL_DSN` pointing to Sail MySQL on `127.0.0.1:3307`
  - `SNAPSHOT_INTERVAL_SECONDS=10`
  - `ALERTS_ENABLED=true`, `AUTO_ACTIONS_ENABLED=true`
- Demo heartbeats were seeded for workers `worker-1..3` and pools `default`, `reputation-a`, `reputation-b`.
