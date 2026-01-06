# AGENTS.md - PM + Dev + Codex Operating Rules (Laravel 12)

This document is the single source of truth for how Sharwat (PM), the Developer (Windows), and Codex must build, modify, and maintain this repository. Codex is used heavily (often 100%) for coding, file creation, and Git operations. Everyone must follow these rules strictly.

---

## 1) Project Goal

Build an Email Verification SaaS platform consisting of:
- Marketing Website (plans, pricing, onboarding)
- Customer Portal (upload email list, track job status, download results)
- Admin Portal (Filament)
- Verifier API (Sanctum) for a separate core verification engine

The core verification engine is NOT built in this repo. It is a separate service that polls pending jobs, processes files, and posts results back.

---

## 2) Fixed Tech Stack (Do NOT change)

- Laravel 12
- PHP 8.4.x (via Docker + Laravel Sail)
- MySQL (via Sail)
- Auth: Laravel Breeze (Blade + Livewire)
- UI: Blade + Livewire (customer portal)
- Admin: Filament Admin
- Billing: Stripe + Laravel Cashier
- API Auth: Laravel Sanctum
- Storage: Local filesystem in dev; S3 in production (must be disk-configurable)
- Local app port: 8082

Hard constraints:
- Do NOT introduce Next.js / React / Vue SPA / Inertia.
- Do NOT introduce paid UI frameworks (Flux, etc.).
- Do NOT change ports away from 8082 unless PM explicitly approves.

---

## 3) Team Environment Rules (Mac + Windows)

### 3.1 Docker/Sail is mandatory for both PM and Dev
All runtime, PHP, Composer, Node, and database commands must run via Sail.

Allowed commands:
- ./vendor/bin/sail artisan ...
- ./vendor/bin/sail composer ...
- ./vendor/bin/sail npm ...

Not allowed:
- host php / composer / npm usage for project tasks

### 3.2 Windows dev (critical rule)
Developer must clone the repository inside WSL filesystem (Linux home), NOT on the Windows C: drive.
Example:
- /home/<user>/projects/<repo>

Then open the folder in VS Code using Remote WSL.

---

## 4) Git Rules (Codex + Dev must follow exactly)

### 4.1 Branching
- main must always be runnable.
- New work must be done in feature branches:
  - feature/<short-name>
Examples:
- feature/jobs-upload
- feature/verifier-api
- feature/stripe-billing

### 4.2 Commit discipline
- Small commits aligned to one task.
- Commit messages:
  - feat: ...
  - fix: ...
  - chore: ...
  - docs: ...
Examples:
- feat: add verification job model and migration
- feat: customer upload page (livewire)
- fix: restrict filament access to admins

### 4.3 Never commit restricted files
Do NOT commit:
- .env
- vendor/
- node_modules/
- storage/app/uploads/*
- storage/app/results/*
- storage/logs/*
- any secrets or credentials

May commit:
- .env.example (placeholders only)
- composer.lock
- package-lock.json (or pnpm-lock.yaml/yarn.lock)

### 4.4 PR workflow (if used)
- PR from feature/* -> main (or dev branch if PM decides later)
- Include test steps in PR description (commands run)

---

## 5) Zero Hardcoding Policy (Mandatory - 100% dynamic)

Do not hardcode any of these in PHP, Blade, Livewire, JS, configs, or tests:
- URLs/domains (including localhost)
- ports
- webhook URLs
- file system paths (absolute or public storage paths)
- storage locations
- Stripe keys, webhook secrets, price IDs, plan IDs
- magic IDs for users/jobs/plans
- any environment-specific values

All environment-dependent values must come from:
- config(...) + .env/.env.example placeholders
- named routes via route(...)
- URL helpers (url(), asset()) only when necessary
- Storage API via Storage::disk(...)

Rules:
- Store only storage keys/relative paths in DB (e.g., uploads/1/abc/input.csv), never absolute paths.
- Downloads must be permission-checked and storage-agnostic (local dev now, S3 later).

---

## 6) Storage Policy (Dev Local -> Prod S3)

We use local storage for development but must be ready to switch to S3 in production without refactoring.

### 6.1 Requirements
- Never hardcode disk names or paths inside features.
- Always use Laravel Storage (filesystem) APIs.
- Disk must be switchable via config/.env.

### 6.2 Required folder structure
Inputs:
- uploads/{user_id}/{job_id}/input.csv

Outputs:
- results/{user_id}/{job_id}/cleaned.csv
- results/{user_id}/{job_id}/report.json (optional)

### 6.3 Download rule
All downloads must go through authorized Laravel routes/controllers.
Never expose raw file URLs/paths publicly.
For S3 later, use signed URLs or controller streaming while enforcing permissions.

---

## 7) Verification Jobs (Core Domain Rules)

### 7.1 Status lifecycle (strict)
- pending -> processing -> completed
- pending -> failed
- processing -> failed

Do not add new statuses without PM approval.

### 7.2 Minimal job fields
A verification job must store:
- user_id
- status
- original_filename
- input_path (storage key)
- output_path (storage key, nullable until completed)
- error_message (nullable)
- started_at (nullable)
- finished_at (nullable)

Optional stats (nullable):
- total_emails
- valid_count, invalid_count, risky_count, unknown_count

---

## 8) Roles & Access Control

Required roles:
- customer
- admin
- verifier-service (token-only)

Rules:
- Filament /admin is admin-only.
- Customers can only access their own jobs and downloads.
- Verifier endpoints are Sanctum token-only (no session auth).

---

## 9) Verifier Integration (Sanctum API Contract)

The core verification engine is separate and must work without UI/session.

Recommended endpoints (do not change without PM approval):
- GET /api/verifier/jobs?status=pending&limit=10
- POST /api/verifier/jobs/{id}/status (processing/failed)
- POST /api/verifier/jobs/{id}/complete (completed + output_path + stats)

Optional:
- GET /api/verifier/jobs/{id}/download (streams input securely if engine is remote)

Authentication:
- Sanctum token required on all verifier routes.
- Token belongs to verifier-service identity.

---

## 10) Billing Rules (Stripe + Cashier)

- Use Cashier for subscriptions and billing.
- Job creation/upload must be blocked if subscription is inactive.
- Plan limits will be enforced at upload time (file size, concurrency, monthly usage later).

Do not build custom billing flows outside Cashier without PM approval.

---

## 11) UI Rules (Customer Portal)

Customer portal must be built using:
- Blade + Livewire only

Rules:
- Keep Livewire components small and focused.
- Use polling for job status updates (every 5-10 seconds) unless PM approves WebSockets.
- Avoid heavy JS; use Alpine only when necessary.

---

## 12) Admin Rules (Filament)

- Filament is the admin portal framework.
- Only admin role can access /admin.
- Maintain Filament resources:
  - Users
  - Verification Jobs
  - Subscriptions (if needed)
  - Job Logs (optional)

Admin actions must never bypass authorization checks.

---

## 13) Code Quality & Structure

- Use Laravel Pint for formatting (run via Sail).
- Use Form Requests for validation.
- Use Policies for authorization.
- Prefer service classes for domain logic (avoid fat controllers).
- Add tests for critical flows:
  - job creation
  - authorization for downloads
  - verifier API auth + status updates

---

## 14) How Codex Should Operate (PM workflow)

Codex must:
- Follow this stack and all constraints.
- Make minimal changes per task.
- Explain what files were changed and why.
- Provide exact Sail commands to verify changes.
- Keep app runnable on port 8082.
- Update README/AGENTS only when rules/requirements change.

Codex must NOT:
- Change architecture, stack, or ports without PM approval.
- Commit secrets or modify .env (only update .env.example placeholders).
- Hardcode URLs, paths, or environment values.
- Expose uploaded files publicly.
- Perform large refactors unless PM requests.

---

## 15) Current Defaults (Reference)

- App: http://localhost:8082
- Admin: http://localhost:8082/admin
- Database: MySQL via Sail
- Storage: local filesystem in dev, S3 later in production
