# Context Handoff (ValidEmailVerifierGUI)

Last updated: 2026-02-01

This document is the handoff bundle for a fresh workspace. It summarizes the current system state, key decisions, and how to resume work without losing context. Do not place secrets in this file.

## 1) Current status (high level)
- Core platform is running on Laravel 12 + Livewire (portal) + Filament (admin) with Go workers for verification.
- Standard (SG1-SG4) and Enhanced (SG5 RCPT probing) modes exist; Enhanced gating can be toggled in settings.
- Output CSV schema is: `email,status,sub_status,score,reason`.
- S3 storage is supported for uploads/results in local dev and production (storage disk is configurable).
- DynamoDB cache read (cache-only mode) is implemented and tested with a real table.
- Engine monitoring (RBL checks) is implemented with `engine-monitor-go` and admin UI.

## 2) Tech stack + environment
- Laravel 12, PHP 8.4 (Sail mandatory), MySQL, Livewire (portal), Filament (admin), Sanctum (API auth).
- Local app port: 8082.
- Storage: local for dev, S3 for production (disk-configurable).
- Go worker: `engine-worker-go` via Docker image (GHCR) + provisioning bundle.
- Monitor: `engine-monitor-go` service (systemd supported).

## 3) Important docs
- `docs/ENGINE_CONTRACT.md`
- `docs/DEEP_VERIFICATION_ENGINE_PLAN.md`
- `docs/SG5_AND_MONITORING_PLAN.md`
- `docs/S3_SETUP.md`
- `docs/DYNAMODB_CACHE_READ_CONTROLS_PLAN.md`

## 4) Verification pipeline (control plane)
- Upload list (portal) -> `VerificationJob` created.
- `PrepareVerificationJob` -> `ParseAndChunkJob`:
  - Normalize/dedupe
  - Cache lookup (EmailVerificationCacheStore)
  - Create chunks for worker
- Go worker pulls chunks, runs checks, posts results.
- Finalization merges outputs and computes score.

## 5) Verification modes
- `verification_mode` is stored on jobs: `standard` or `enhanced`.
- Enhanced triggers SG5 RCPT probing in Go worker when policy allows.
- Catch-all policy is admin-only:
  - `risky_only` or `promote_if_score_gte` threshold.

## 6) Output schema + scoring
- Final output CSV header: `email,status,sub_status,score,reason`.
- Score computed in Laravel finalization (0-100). Scoring uses deterministic mapping from reason and cache outcomes.
- Backward-compatible with old `email,reason` chunk outputs.

## 7) Single Email Test (portal)
- Single-check uses the same pipeline as list uploads (job origin = `single_check`).
- Results are shown in the portal; job visibility can be hidden by admin setting.
- Enhanced selection currently enabled for testing (guardrails can be re-added later).

## 8) Engine server provisioning (worker)
- Admin manages Engine Servers and Verifier Domains.
- Provisioning bundle generates a one-line install command:
  - Downloads signed `install.sh`
  - Installs Docker, pulls GHCR image, runs container with generated env
- Provisioning block is inside Engine Server edit UI.

## 9) S3 storage (uploads/results)
- S3 support is working in local dev.
- Required env keys are documented in `docs/S3_SETUP.md`.
- Livewire uploads require Flysystem AWS v3 dependency (already added).
- Use `php artisan config:clear` after .env updates.

## 10) DynamoDB cache (read)
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

## 14) Today’s update (2026-02-01)
- Added this `docs/CONTEXT_HANDOFF.md` for workspace handoff.
- No code changes today.

## 15) Next planned upgrades (not yet implemented)
- Cache read metrics + dashboard widgets
- Adaptive throttling for cache reads
- Chunked cache lookup jobs for large uploads
- Retry strategy improvements (partial unprocessed keys)
- Cache write-back to DynamoDB (write settings in UI)


---

## Update log

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
