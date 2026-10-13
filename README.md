# DICT Free Wi-Fi Customer Feedback Platform

Central platform for anonymous customer feedback at DICT Free Wi-Fi sites, as specified in
`../Spec.md` and sequenced in `../Plan.md`.

**Implemented: Phase 3 (core application), Phase 4 (public survey + QR/site-link fallback) and
the Phase 5 vendor-neutral captive portal contract.**
Phases 0–2 (governance, supplier/gateway inventory, VPS foundation) are human work and are not in this repo.

The portal contract is verified end to end against the bundled gateway simulator. It is **not yet
verified against real gateway hardware** — see [docs/PORTAL_CONTRACT.md](docs/PORTAL_CONTRACT.md).

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
- Gateway simulator (local only, admin-only): <http://localhost:8000/simulator>
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
| Captive portal contract (init/status/complete) | `app/Http/Controllers/Portal/PortalApiController.php` |
| Signed handoff token, session rules, outcome signing | `app/Services/Portal/` |
| Gateway simulator (local only) | `app/Http/Controllers/Simulator/GatewayController.php` |
| Integration registry, rotation, revocation | `app/Models/Integration.php`, `app/Filament/Resources/Integrations` |
| RBAC + row-level organizational scope | `app/Policies/*`, `Site::scopeVisibleTo()`, `SurveyResponse::scopeVisibleTo()` |
| Audit trail (append-only, before/after diffs) | `app/Models/Concerns/Auditable.php`, `app/Models/AuditLog.php` |
| Rate limits, honeypot, correlation IDs | `AppServiceProvider`, `AssignCorrelationId` |

## Deliberate design decisions

- **Attribution is never faked.** A link/QR submission records `site_link`/`qr_code`
  (unverified). Only the portal handoff, which needs a secret the public browser never has,
  records `captive_verified`. See `Site::publicAttributionMethod()` and the portal controllers.
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

- **Vendor-specific adapter for the pilot gateway.** The contract is done; the TP-Link Omada
  mapping is not, because the controller rejected the supplied credentials (cloud-managed base
  URL still needed) and its supported portal mechanism is unconfirmed. See the pilot gate in
  `docs/PORTAL_CONTRACT.md`.
- CSV export and dashboard filters (Phase 7) — the scoped `visibleTo()` query they need already exists.
- MFA wiring (Filament 5 ships it natively; enable when accounts exist).
- Retention/anonymisation jobs — pending approved retention periods.
- `integration_events` table — the per-integration `last_success_at` / `last_error_code`
  columns cover health reporting for now; a full event log is a later phase.
- Bulk site import, scheduled reports, NMS metrics.

## Secrets

Real gateway credentials belong in `.env` (gitignored) or the deployment secret store — never in this
repository, a migration, or a seed file. `DemoSeeder` creates only fake data.