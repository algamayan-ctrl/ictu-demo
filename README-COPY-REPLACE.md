# ICTU Audit Trail — Copy & Replace

**Base:** `capstone-spmu-acpmp.zip` audit page, with the shared `app.blade.php` based on the most recent finalized ICTU User Accounts integration.

## Install

1. Back up the current two files in your Laravel project.
2. Open this ZIP, copy the `resources` folder into your existing project root (where `artisan` is located), and replace its matching two files:
   - `resources/views/reports/audit.blade.php`
   - `resources/views/layouts/app.blade.php`
3. Run (PowerShell from your project root):

```powershell
docker compose build app
docker compose up -d --no-deps --force-recreate app
docker compose exec app php artisan optimize:clear
```

4. Open `/reports/audit` and refresh with **Ctrl + Shift + R**.

No migration, seeding, account resets, or controller/route changes are needed.

## Finalized UI

- Same slim, maximized-width ICTU styling as User Accounts.
- Topbar: Audit Trail title plus existing approved subtitle; duplicate large page-heading removed.
- Four desktop filter groups: Search, Date, Category, Actor + Reset.
- Full-width five-column table: Activity; Type; Actor / Record; Time; Action.
- Real records from the existing AuditEvent query (no sample users/records hardcoded).
- Activity icon, readable action label/reason, category badge, actor, source record ID, time and date.
- Default seven records per page; options 7 / 14 / 28, range indicator, working Prev / Next.
- View Details expands directly beneath the selected row, retaining action code, timestamp, IP, reason, correlation ID, and before/after JSON when available.
- Search and filters reset to page 1 and retain the current data scope.
- Maintains responsive horizontal scrolling on small screens and existing light/dark theme variables.

## Data-scope note

The existing `ReportController::audit()` deliberately retrieves only the latest **500** audit events. This patch does not change that controller. Pagination, search, and filters operate on these loaded events. If more than 500 exist, the interface preserves the existing disclosure using the full DB count; stored audit history is not modified or deleted.

`Actor / Record` is used instead of `Account` because the Audit Trail can also contain borrowing, approvals, custody, accountability and settings actions.

## Validated

- PHP syntax (compiled Blade directives; existing x-component tags unchanged).
- Inline JS syntax and CSS parsing.
- Chromium smoke: initial seven rows; navigation; details toggle; filters; no-results; Reset; page-size selection; no runtime JS errors.
- Compatible with existing `ReportPageTest` wording checks for `System administration` and the latest-500 display-cap note.

Full Laravel/PHPUnit tests against your Docker database must still run in your environment.
