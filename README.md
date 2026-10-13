# DICT Free Wi-Fi Customer Feedback Platform

Central platform for anonymous customer feedback at DICT Free Wi-Fi sites, as specified in
`../Spec.md` and sequenced in `../Plan.md`.

**Implemented: Phase 3 (core application) and Phase 4 (public survey + QR/site-link fallback).**
Phases 0–2 (governance, supplier/gateway inventory, VPS foundation) are human work and are not in this repo.

## Stack

Laravel 12 · PHP 8.2 · Filament 5 · MariaDB/MySQL · Tailwind 4 (Vite)

## Local setup

```bash
composer install
npm install && npm run build

# .env already points at the XAMPP MariaDB instance
php artisan migrate:fresh --seed     # schema + demo data (fake sites only)
php artisan serve
```

- Public survey: <http://localhost:8000/f/DEMO-0001>
- QR poster: <http://localhost:8000/f/DEMO-0001/qr>
- Admin panel: <http://localhost:8000/admin> — `admin@example.test` / `password`
- Supplier-scoped demo user (proves row-level scope): `supplier@example.test` / `password`

Tests: `php artisan test` (55 tests) · Style: `vendor/bin/pint`

## What is here

| Area | Where |
|---|---|
| Supplier + site registry, immutable `site_code`, soft delete | `app/Models/Site.php`, `app/Filament/Resources/Sites` |
| Survey builder with immutable published versions | `app/Models/SurveyVersion.php`, `VersionsRelationManager` |
| Survey resolution per site (scope, schedule, assignment precedence) | `app/Services/Surveys/ResolveSurvey.php` |
| Response ingestion, schema validation, idempotency, attribution | `app/Services/Responses/SubmitResponse.php` |
| Public survey UI (mobile-first, no JS required) | `resources/views/public/survey.blade.php` |
| Public JSON API (`/api/v1/public/*`) | `app/Http/Controllers/Api/PublicApiController.php` |
| RBAC + row-level organizational scope | `app/Policies/*`, `Site::scopeVisibleTo()`, `SurveyResponse::scopeVisibleTo()` |
| Audit trail (append-only, before/after diffs) | `app/Models/Concerns/Auditable.php`, `app/Models/AuditLog.php` |
| Rate limits, honeypot, correlation IDs | `AppServiceProvider`, `AssignCorrelationId` |

## Deliberate design decisions

- **Attribution is never faked.** A site configured for a captive portal still records
  `site_link`/`qr_code` (unverified) until a validated gateway handoff exists (Phase 5).
  See `Site::publicAttributionMethod()`.
- **A mandatory survey policy fails safe.** If a site requests `required_before_access` without a
  verified portal integration, `ResolveSurvey` downgrades it to `optional` and reports it, so the
  platform can never strand a public user behind a survey it cannot prove is reachable.
- **One write path.** The HTML form and the JSON API both call `SubmitResponse`, so validation,
  deduplication and attribution cannot drift apart.
- **Scope is enforced in queries and policies**, not by hiding menu items. Roles decide actions,
  scope decides rows.
- **Responses are read-only** in the admin. FR-RESP-012 (correction/quarantine/deletion) needs DICT
  approval before it is built, so `SurveyResponsePolicy` denies update and delete outright.
- **No personal data.** No names, numbers, emails or IPs are stored with a response; any session
  reference is hashed.

## Not built yet (deliberately)

- **Phase 5 — captive portal contract and pilot adapter.** Blocked on the confirmed gateway model and
  its supported completion-verification method. Pilot gateway is a TP-Link Omada controller.
- CSV export and dashboard filters (Phase 7) — the scoped `visibleTo()` query they need already exists.
- MFA wiring (Filament 5 ships it natively; enable when accounts exist).
- Retention/anonymisation jobs — pending approved retention periods.
- `integrations` / `integration_events` tables — nothing consumes them before Phase 5; `sites`
  already carries `integration_mode` and `integration_status`.
- Bulk site import, scheduled reports, NMS metrics.

## Secrets

Real gateway credentials belong in `.env` (gitignored) or the deployment secret store — never in this
repository, a migration, or a seed file. `DemoSeeder` creates only fake data.