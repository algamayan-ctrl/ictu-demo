# ICTU Deployment and Operations

## Production architecture

The supplied stack contains:

- `app`: PHP 8.4, Apache, Laravel 13.
- `database`: MariaDB 11.4 with a persistent volume.
- `scheduler`: Laravel scheduler for due-soon, approval expiry, overdue, tariff, sanction, and notice processing.
- shared protected application-storage volume.

The production image includes LibreOffice Writer/Calc for the approved DOCX/XLSX document runtime. Its entrypoint runs `package:discover`, `config:cache`, `route:cache`, and `view:cache` whenever a container starts. Do not repeat those cache commands during normal deployment; use `optimize:clear` only for directed troubleshooting.

Place an ICTU-managed HTTPS reverse proxy/load balancer in front of the app. Do not expose MariaDB publicly.

## Production environment register

Copy `.env.docker.example` to the protected, untracked `.env.docker` file. Do not place real values in source control, screenshots, tickets, or chat.

| Variable group | Status | Production requirement |
|---|---|---|
| `APP_KEY` | Required | Generate with `php artisan key:generate --show`; preserve it securely because replacing it invalidates encrypted application state. |
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, timezone | Required/environment-specific | Use `production`, `false`, the approved HTTPS URL, and `Asia/Manila`. |
| `EMAIL_VERIFICATION_BASE_URL` | Required for cross-device verification | Use the same approved HTTPS SPMU-ACPMP origin that recipient phones/laptops/tablets can reach. A private LAN URL is suitable only for same-network testing. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MARIADB_*` | Required | Use the internal MariaDB service and ICTU-managed credentials. Database access must not be public. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Required only when enabling Google sign-in | Register the exact HTTPS callback `${APP_URL}/auth/google/callback` in Google Cloud Console. Missing values keep Google sign-in unavailable; local account authorization remains unchanged. |
| `GOOGLE_ALLOWED_DOMAINS` | Required deployment policy when Google sign-in is enabled | Set to `cspc.edu.ph` for CSPC production. The variable supports a comma-separated list only when ICTU has an approved institutional exception. |
| `MAIL_*` | Required for production email delivery | Use the approved institutional SMTP service and sender identity. |
| `SEED_DEMO_USERS` | Required | Set `false` in production. |
| `RUN_MIGRATIONS` | Environment-specific | `true` makes the entrypoint run migrations on every container start and seed the reference data once, on a new database only (`php artisan spmu:bootstrap`). Use `true` for the first start, then set `false` once the system is in use and apply migrations explicitly. See "Initialization and repeat seeding" below. |

The Google OAuth domain allow-list is an additional CSPC production control. It does not create accounts or assign roles: Google proves identity, while the pre-existing active local user record still controls access.

## Initial deployment

1. Install Docker Engine/Desktop and Docker Compose on the approved server.
2. Copy `.env.docker.example` to `.env.docker`.
3. Generate an application key locally with `.\tools\artisan.cmd key:generate --show` and place the entire result in `APP_KEY`.
4. Replace every placeholder password, URL, SMTP value, and host. Set `APP_DEBUG=false`, `APP_ENV=production`, `SEED_DEMO_USERS=false`, and the real HTTPS `APP_URL`. If Google sign-in is enabled, set all four `GOOGLE_*` values and register the exact callback URI with Google; use `GOOGLE_ALLOWED_DOMAINS=cspc.edu.ph`.
5. Protect `.env.docker` with operating-system permissions; never commit or email it.
6. Start the stack:

```powershell
docker compose up -d --build
docker compose ps
```

With `RUN_MIGRATIONS=true`, the app container applies migrations and then runs `php artisan spmu:bootstrap`, which seeds the reference/master data, including the organizational master data, once. The same entrypoint has already refreshed configuration, route, and view caches.

### Initialization and repeat seeding

`spmu:bootstrap` is the only seeding the entrypoint performs, and it seeds a database at most once:

| State of the database | What `spmu:bootstrap` does |
|---|---|
| Already initialized (its completion marker is present) | Nothing. |
| No marker, but already in use (it has accounts, borrowing requests, or audit history) | Seeds nothing and records the marker, so the existing data is kept exactly as it is. |
| New and unused | Seeds the reference data and records the marker in one transaction. If anything fails, nothing is kept and the next start tries again. |

The marker is the internal `reference_data_bootstrap` row in `system_settings`. It is not shown on the settings screens. Do not edit or delete it: without it, a database with no accounts yet would be seeded again.

Running the seeders directly is a different matter. `php artisan db:seed` ignores the marker, and the seeders are **not** all safe to run again once staff have edited the data:

| Data | Effect of seeding again |
|---|---|
| Opening inventory items | An item that was renamed or deleted is created again with its opening quantity, so stock is overstated. An item whose name and category are unchanged is left alone, including its quantity. |
| Organizational units | Names, parent units, and the active flag are reset to the seeded hierarchy. |
| Roles | Role names and active flags are reset. |
| System settings | Not changed. Existing values are kept. |
| Demo accounts | Never created when `APP_ENV=production`, even if `SEED_DEMO_USERS` is `true`; a warning is logged instead. |

Do not run `php artisan db:seed` against a database that is in use.

### `RUN_MIGRATIONS` by situation

| Situation | Setting | What to run |
|---|---|---|
| First-time local setup (`.env.docker` created by `tools/prepare-docker-local.ps1`) | `true` | Nothing by hand. The first `docker compose up -d --build` migrates and initializes the database, including the demo accounts when `SEED_DEMO_USERS=true`. |
| First start of a production Docker deployment | `true` | Nothing by hand for migrations and reference data; then create the first ICTU account as described below. |
| Any later start or update, local or production | `false` | `docker compose exec app php artisan migrate --force` when an update brings migrations. Nothing else. |
| Laravel Cloud staging | Not used | Follow `LARAVEL-CLOUD-FIRST-DEPLOY.md`; the Docker entrypoint does not run there. |

Leaving `RUN_MIGRATIONS=true` after the first start no longer reseeds anything, but it does apply pending migrations automatically on every restart. Setting it to `false` keeps schema changes a deliberate step taken during a planned update.

7. Create the first real ICTU account:

```powershell
docker compose exec app php artisan spmu:user ictu.admin@cspc.edu.ph --name="ICTU Administrator" --employee-no="REAL-EMPLOYEE-NO" --unit=ICTU --role=ICTU
```

The command securely prompts for a 12+ character password with uppercase, lowercase, number, and symbol.

8. Sign in and create only the approved Borrower, SPMU Admin / Head, SPMU Action Officer, and ICTU accounts. GSU/VPAF do not receive application accounts; their request-letter signatures are obtained physically outside the system.
9. Reconcile opening inventory and all values in the Configuration Register before production acceptance.

## Google OAuth and email

Google OAuth is optional. When enabled, use only the environment variables above; never hardcode a client secret in PHP or configuration files. The Google Cloud Console redirect must exactly match `GOOGLE_REDIRECT_URI`, and production CSPC accounts must be limited with `GOOGLE_ALLOWED_DOMAINS=cspc.edu.ph`.

Laravel uses the configured SMTP transport for email. Delivery attempts and responses appear in the notification report.

Verification links are host-independent and are prefixed with `EMAIL_VERIFICATION_BASE_URL`. This means ICTU may register an account from one computer and the recipient may verify from another device. For access from any network, the base URL must be the institution-approved public HTTPS SPMU-ACPMP address. For a same-LAN demo on Windows, `tools\configure-verification-url.cmd` can detect the server laptop's LAN address; it does not require changing PowerShell's permanent execution policy.

## Scheduled operations

Confirm the scheduler container stays healthy. Manual verification:

```powershell
docker compose exec app php artisan spmu:process-deadlines
```

Run `docker compose logs scheduler` when notices, expiry, or overdue processing appears delayed.

## Backup and recovery

The web **Download local database backup** button applies only to SQLite development. Production uses MariaDB-native backup managed by ICTU. Follow the [Backup and Restore Runbook](BACKUP-RESTORE-RUNBOOK.md) for the manual database plus protected/generated-storage procedure, integrity checks, and isolated restore rehearsal.

No automated backup service is included in this deployment architecture. `backup_schedule` remains `NOT_FINALIZED` until ICTU/client confirmation defines frequency, retention, destination, encryption/key ownership, off-site storage, RPO, RTO, responsible operator, and rehearsal schedule.

## Update procedure

1. Take and verify a recoverable backup.
2. Review code/migration changes and test in staging.
3. Run the supported test image and code-quality checks in staging before packaging.
4. Deploy with:

   ```powershell
   docker compose up -d --build --force-recreate app scheduler
   docker compose ps
   docker compose exec app php artisan migrate:status
   ```

   `--force-recreate` is mandatory, not optional. The `app` and `scheduler` containers run a baked image with no live source mount: after any code change, `docker compose up -d --build` alone can leave a stale container running against a newer image without visibly failing. A verified incident (a stale image left `spmu:process-deadlines` failing on every scheduled run for over a week with no symptom besides accumulating log errors) confirms this is a real, not theoretical, risk. Confirm `docker compose ps` shows both containers freshly recreated and healthy, and that `migrate:status` shows no pending migration, before continuing.
5. The entrypoint refreshes the caches on every container start. With `RUN_MIGRATIONS=false` (the setting for a system in use), run `docker compose exec app php artisan migrate --force` and repeat the `migrate:status` check. With `RUN_MIGRATIONS=true`, the entrypoint migrates automatically on the restart; it does not seed an initialized database again. See "Initialization and repeat seeding" above.
6. Check `/up`, sign-in, scheduler and queue logs, database health, protected-storage write access, LibreOffice document runtime, email, and one non-destructive role test.
7. Record the release and validation as an ICTU technical operation/change record.

## Security checklist

- HTTPS only; HSTS is emitted in production HTTPS requests.
- Keep APP_KEY, database, and SMTP secrets outside source control.
- Use named employee accounts; no shared production passwords.
- Disable departed/ineligible accounts immediately and review roles regularly.
- Preserve the login throttle, CSRF checks, secure session settings, security headers, protected storage, and audit trail.
- Grant database/application filesystem access only to the runtime and designated ICTU operators.
- Monitor failed logins at the edge, failed deliveries, application exceptions, database capacity, scheduler health, storage capacity, and backup outcomes.
- Do not manually update finalized business tables. Authorized corrections must use a documented, audited procedure.

## Health and troubleshooting

- Application health: `https://your-approved-host/up`.
- Container state: `docker compose ps`.
- App logs: `docker compose logs app`.
- Scheduler logs: `docker compose logs scheduler`.
- Queue logs: `docker compose logs queue`.
- Database logs: `docker compose logs database`.

Test local SQLite and production MariaDB separately before institutional acceptance; different database engines can expose constraint or date-query differences.
