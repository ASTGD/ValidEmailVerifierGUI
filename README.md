# ValidEmailVerifierGUI — Email Verification SaaS (Laravel 12)

This repository contains the Laravel 12 application for the Email Verification SaaS platform.

The application includes:
- Marketing website (plans, pricing, onboarding)
- Customer portal (upload email lists, track verification jobs, download results)
- Admin portal (manage users, subscriptions, jobs, operations)
- Verifier API for the separate core verification engine (job polling + status/result submission)

The email verification engine is a separate service. This repository implements the UI, billing, job orchestration, and API layer.

---

## Tech Stack

- Laravel 12
- PHP 8.4.x (via Docker + Laravel Sail)
- MySQL (via Sail)
- Auth: Laravel Breeze (Blade + Livewire)
- Customer Portal: Blade + Livewire
- Admin Portal: Filament Admin
- Billing: Stripe + Laravel Cashier
- API Auth: Laravel Sanctum
- Storage: Local filesystem in development, S3 in production (disk-configurable via Laravel Filesystem)

---

## Local Development

This project uses Docker + Laravel Sail for consistent environments across macOS and Windows.

### App URLs
- Application: http://localhost:8082
- Admin Panel (Filament): http://localhost:8082/admin

### Common Commands

Start containers:

    ./vendor/bin/sail up -d

Stop containers:

    ./vendor/bin/sail down

Run migrations:

    ./vendor/bin/sail artisan migrate

Reset database + seed dev users:

    ./vendor/bin/sail artisan migrate:fresh --seed

Run tests:

    ./vendor/bin/sail artisan test

Frontend dev server (Vite):

    ./vendor/bin/sail npm run dev

Composer commands (always via Sail):

    ./vendor/bin/sail composer install
    ./vendor/bin/sail composer require vendor/package

### Create Admin User

    ./vendor/bin/sail artisan app:bootstrap-users

---

## Project Modules (What lives where)

### Marketing Website
- Blade pages: resources/views
- Public routes: routes/web.php

### Customer Portal (Blade + Livewire)
- Livewire components: app/Livewire
- Portal views: resources/views
- Portal routes: routes/web.php

Core features:
- Upload email list (CSV)
- Create verification job (pending)
- Track status (pending → processing → completed/failed)
- Download results when completed

### Admin Portal (Filament)
- Filament panel and resources: app/Filament
- Access controlled to admin users only

Admin features:
- Manage users
- Monitor jobs by status
- Review failures and rerun operations (future)
- Support downloads

### Billing (Stripe + Cashier)
- Subscription plans
- Webhook-driven subscription state sync
- Restrict job creation for inactive subscriptions

### Verifier API (Sanctum)
- Token-protected API endpoints used by the core verification engine
- The engine polls for pending jobs, downloads input files, uploads output files, and updates status/results

---

## Storage Strategy (Dev Local → Prod S3)

### Development
- Uses Laravel filesystem disk: local
- Inputs and outputs stored under storage/app/...

### Production
- Will switch to S3 (or S3-compatible storage) via Laravel filesystem configuration
- The application must not hardcode file paths. Always use Laravel’s Storage APIs so switching disks requires no code changes.

### Expected Storage Structure
- uploads/{user_id}/{job_id}/input.csv
- results/{user_id}/{job_id}/cleaned.csv
- results/{user_id}/{job_id}/report.json (optional)

### Download Security Requirement
All file downloads must be served through Laravel routes/controllers with authorization checks.
Do not expose raw storage paths publicly.

---

## Roles
- customer: portal access
- admin: Filament admin access
- verifier-service: Sanctum token access to verifier endpoints

## Billing
- Customer billing portal: `/billing`
- Checkout uses `STRIPE_PRICE_ID` (set in `.env`)

## Verifier API (Sanctum)

Endpoints:
- GET /api/verifier/jobs?status=pending&limit=10
- POST /api/verifier/jobs/{id}/status
- POST /api/verifier/jobs/{id}/complete
- GET /api/verifier/jobs/{id}/download

Issue a verifier token:

    ./vendor/bin/sail artisan app:issue-verifier-token

## Maintenance

Purge completed/failed jobs older than the retention window:

    ./vendor/bin/sail artisan app:purge-verification-jobs

---

## Contribution Workflow
- Work on feature/<feature-name> branches
- Small PRs preferred (single feature/module)
- main must remain runnable
- Do not commit .env, vendor/, node_modules/, or uploaded files

---

## Notes
- Port is fixed to 8082 to avoid conflicts with other projects.
- Always run PHP/Artisan/Composer/NPM through Sail.
- Follow the rules in AGENTS.md for contribution and Codex behavior.
