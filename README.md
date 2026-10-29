# SPMU-ACPMP

SPMU Asset Custody and Performance Monitoring Program is a Laravel 13 system for CSPC property borrowing, custody, return, laundry handling, accountability, reporting, and ICTU administration.

## Final active access model

The application has one requester portal and three active staff classifications:

| Access | Purpose |
|---|---|
| Borrower | Creates borrowing requests and tracks personal requests/custody. Only Borrower accounts may borrow. |
| SPMU Admin / Head | Final borrowing decision authority for on-campus submissions and Action Officer-verified off-campus requests, oversight, reports, and authorized controls. |
| SPMU Action Officer | Off-campus request/document verification, pickup scheduling, exact-quantity preparation, physical release/return, laundry operations, evidence and operational processing. May make the final SPMU decision only under a valid formal delegation. |
| SPMU Admin / Head (administration) | Also administers user accounts and classifications, operational configuration and the operational audit trail. ICTU is no longer an application role; as Technical System Custodian it maintains hosting, Cloudflare, deployment, the database, backups and source code. See "Technical turnover and responsibilities" in [docs/DEPLOYMENT-ICTU.md](docs/DEPLOYMENT-ICTU.md). |

**GSU and VPAF are not application roles, portals, approval queues, or system approvers.** When required by the institutional Borrowing Request Letter, they are **physical signatories only**. The borrower prints the letter, obtains the required handwritten/wet signatures, scans the accomplished document, and uploads it to SPMU for verification.

## Final borrowing workflow

1. Borrower checks available/serviceable inventory and creates a Draft request.
2. The system generates a printable Draft Borrowing Request Letter.
3. Borrower prints the letter and obtains required handwritten/wet signatures, including the required GSU/VPAF institutional signatories on the physical letter.
4. Borrower scans and uploads the fully signed Borrowing Request Letter. A Permission to Conduct document is also required when the request represents a student activity/organization.
5. On-campus submissions go directly to the SPMU Head. Off-campus submissions first go to the SPMU Action Officer, who verifies the request and documents or returns an incomplete request for correction. `VERIFIED` is not approval and creates no reservation.
6. The SPMU Head reviews on-campus submissions directly and off-campus requests after Action Officer verification. The Head may approve, reject, or return the request under the existing decision/delegation rules.
7. Head approval performs the final availability check, reserves the exact approved quantity, and automatically generates the Borrower Slip and applicable Gate Pass. No operational document is generated before approval.
8. The borrower views/downloads the generated documents and brings them to SPMU on the scheduled pickup date.
9. The Action Officer validates the approved/generated documents, prepares the exact approved quantity, records physical handover, and moves custody to Released / On Custody.
10. SPMU records return and applicable Gate Pass/Laundry evidence using the existing separate return, laundry, and accountability workflows.

## Other implemented controls

- Single-portal role isolation and cross-portal 403 protection.
- Formal, time-bound SPMU delegation using the delegate's own account.
- Date-aware inventory availability and borrowing calendar.
- Exact reservation/allocation with database locking and no silent quantity reduction.
- Cancellation/Early Return controls and item-level physical return inspection.
- Damage, destruction, missing, lost, stolen, evidence, billing, sanctions and restrictions.
- In-system notifications, SMTP email and delivery records.
- Reports, CSV exports, audit history, security headers, login throttling and protected file storage.
- Docker/MariaDB application, database and scheduler services.

## Docker local start

Use Docker rather than XAMPP for the project runtime. From PowerShell in the project root:

```powershell
docker compose up -d --build
docker compose ps
```

With `RUN_MIGRATIONS=true`, the container entrypoint applies migrations and, on a new database only, seeds the reference data once (`php artisan spmu:bootstrap`), then refreshes configuration, route, and view caches. Do not repeat those commands after a normal start.

The seeders themselves are not safe to repeat: run again on a database that is in use, `php artisan db:seed` recreates opening inventory items that were renamed or deleted and resets organizational units and roles. Never run it by hand on a database with real data. After first-time setup, set `RUN_MIGRATIONS=false` in `.env.docker` and apply migrations explicitly when updating; see `docs/DEPLOYMENT-ICTU.md`.

Open:

```text
http://127.0.0.1:8080
```

Local demo password: `SPMU-Demo-2026!`

| Demo access | Email |
|---|---|
| Borrower | `borrower@spmu.test` |
| SPMU Action Officer | `spmu@spmu.test` |
| SPMU Admin / Head | `spmu-head@spmu.test` |

There are intentionally **no GSU or VPAF login accounts** in the current demo seeder.

## Test suite

```powershell
docker compose -p spmu-acpmp-test -f .\docker-compose.test.yml build --no-cache test

docker compose -p spmu-acpmp-test -f .\docker-compose.test.yml run --rm test php vendor/bin/phpunit --testdox
```

## Production turnover

Production configuration, first SPMU Admin account creation, scheduler/storage operations, Google OAuth, update steps, and the environment-variable register are in [docs/DEPLOYMENT-ICTU.md](docs/DEPLOYMENT-ICTU.md). The manual database/protected-storage backup, verification, and isolated restore-rehearsal procedure is in [docs/BACKUP-RESTORE-RUNBOOK.md](docs/BACKUP-RESTORE-RUNBOOK.md). Do not use local demo settings or the SQLite download backup action for production.

## Source map

- `app/Http/Controllers` — role portals and form actions.
- `app/Services` — request workflow, inventory, custody, documents, notifications, files and audit logic.
- `app/Models` — business records and relationships.
- `database/migrations` — relational schema and workflow revisions.
- `database/seeders` — reference data and optional demo accounts.
- `resources/views` / `public/css/app.css` — responsive interface.
- `routes/web.php` — protected application routes.
- `tests` — executable regression/acceptance coverage.
- `docker-compose.yml` / `docker-compose.test.yml` — application and test runtimes.

Legacy GSU/VPAF enum/database values may remain only so historical records do not break. They are disabled from assignment and hidden from active user administration.

For the explicit finalized client baseline, see [docs/CLIENT-WORKFLOW-FINAL.md](docs/CLIENT-WORKFLOW-FINAL.md).
