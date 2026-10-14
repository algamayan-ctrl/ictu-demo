# Laravel Cloud Staging: First Deployment

This is the procedure for bringing SPMU-ACPMP up on a new Laravel Cloud
environment with an empty MySQL database. It covers the first deployment only.
For the Docker deployment on an ICTU server, see `DEPLOYMENT-ICTU.md`.

Follow the steps in order. Steps 4 and 5 are run once per database, never again.

## What a fresh database needs

Migrations alone do not produce a usable system. On an empty database:

| After | Roles | System settings | Inventory | Users |
|---|---|---|---|---|
| `migrate` only | 1 (inactive `LAUNDRY`) | 6 of 13 | none | none |
| `migrate`, then `spmu:bootstrap` | Borrower, SPMU, ICTU active | 13, plus one internal marker row | 4 categories, 3 units of measure, 26 opening items | none |
| then `spmu:user` | | | | first SPMU Admin / Head |

Until the reference data is seeded, no account can be created: `spmu:user`
stops with an error because the role it must assign does not exist.

## Before you start

- A Laravel Cloud environment with a MySQL database attached and empty.
- A private object storage bucket attached to the environment.
- A working outgoing mail service. The first sign-in requires an email
  one-time code, so mail must work before step 6.
- The institutional email address, full name and employee number of the
  person who will hold the first SPMU Admin / Head account.

Never put real passwords, keys or secrets in this file, in a ticket, in chat,
or in a screenshot. Enter them only in the Laravel Cloud environment settings
or at a password prompt.

## Step 1: Environment variables

Set these in the Laravel Cloud environment. Values marked "injected" are
normally supplied by Laravel Cloud when the resource is attached; confirm the
names it uses match.

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Generated for this environment; store it securely and do not rotate it casually |
| `APP_URL` | The HTTPS address of this environment |
| `APP_TIMEZONE` | `Asia/Manila` |
| `DB_CONNECTION` | `mysql`. The application default is `sqlite`, so this must be set. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Injected |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `FILESYSTEM_DISK` | `local` |
| `PROTECTED_FILES_DISK` | `s3` |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | Injected for the private bucket |
| `MAIL_*` | The approved mail service and sender |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `GOOGLE_ALLOWED_DOMAINS` | Only if Google sign-in is enabled; register `${APP_URL}/auth/google/callback` with Google |
| `SEED_DEMO_USERS` | `false`, or leave it unset. See the warning below. |

**Demo users must stay off.** `SEED_DEMO_USERS` defaults to `false`. The four
demonstration accounts use a password that is published in the source code.
With `APP_ENV=production` the seeder refuses to create them even if the flag is
`true`, and logs a warning instead; in any other environment the flag creates
them. Do not set it on staging or production, even temporarily, and do not rely
on the refusal: keep `APP_ENV=production` and leave the flag unset.

Do not set `RUN_MIGRATIONS`. It belongs to the Docker entrypoint and has no
effect on Laravel Cloud.

## Step 2: Deploy command

Set the environment's deploy command to run migrations only:

```
php artisan migrate --force
```

Do not add `db:seed` to the deploy command. Seeding on every deployment undoes
later changes; see "Why the seeders are never run directly".

## Step 3: First deployment

Deploy. Then confirm from the Laravel Cloud command runner:

```
php artisan migrate:status
```

Every migration should show as run, with none pending.

## Step 4: One-time reference data (run once)

Confirm `SEED_DEMO_USERS` is not `true`, then run:

```
php artisan spmu:bootstrap
```

This creates the roles, the remaining system settings, the inventory
categories, units of measure and the 26 opening inventory items. It creates no
user accounts. It ends with `Reference data seeded. This database will not be
seeded again.`

The command records a completion marker in the database and seeds at most
once. Running it again reports that the data was already initialized and
changes nothing. If it fails part way, nothing is kept and it can simply be
run again. On a database that already has accounts, requests or audit history
it seeds nothing.

Use this command, not `db:seed`.

## Step 5: First SPMU Admin / Head account (run once)

Run the command with the real person's details and **without** a password
option, so the password is asked for at a hidden prompt:

```
php artisan spmu:user <institutional-email> --name="<Full Name>" --employee-no="<Employee No>" --unit=SPMU --role=SPMU --classification=SPMU_HEAD
```

The password must have at least 12 characters with an uppercase letter, a
lowercase letter, a number and a symbol.

If the Laravel Cloud command runner cannot show an interactive prompt, the
command accepts `--password=`. A password typed that way may be kept in the
command history, so treat it as temporary: sign in once and change it
immediately. Never reuse a demonstration password.

The command prints `Account ready for <name> as SPMU Admin / Head.` when it
succeeds. If it reports that the organizational unit is unavailable or fails
on a missing role, step 4 has not been run.

## Step 6: Verify

1. Open the site and sign in as the new SPMU Admin / Head. Expect an email
   one-time code on first sign-in; the account command does not mark the
   address as verified.
2. Confirm the SPMU workspace opens with User Accounts in the menu.
3. In the command runner, confirm the counts:

   ```
   php artisan tinker --execute="echo App\Models\Role::count().' roles, '.App\Models\User::count().' users, '.App\Models\InventoryItem::count().' items';"
   ```

   Expected on a new database: `4 roles, 1 users, 26 items`.
4. Upload a small test file through the application (for example an
   E-signature on the profile page) and open it again. This proves the private
   bucket is writable and readable.
5. Enable the scheduler for the environment so `spmu:process-deadlines` runs
   every minute, and confirm it appears in the scheduler log.

From here, the SPMU Admin / Head creates the remaining accounts in the
application, and SPMU reconciles the opening inventory.

## Later deployments

- The deploy command stays `php artisan migrate --force`.
- Do not run `db:seed`.
- New reference data arrives through migrations.

## Why the seeders are never run directly

`spmu:bootstrap` is safe to repeat because it checks its marker first.
`db:seed` does not. The seeders were written to bootstrap an empty database;
run after the system is in use, they change data that staff have since edited:

| Data | Effect of running `db:seed` again |
|---|---|
| Opening inventory items | An item that was renamed or deleted is created again with its opening quantity, so stock is overstated. An item whose name and category are unchanged is left alone, including its quantity. |
| Organizational units | Names, parent units and the active flag are reset to the seeded hierarchy. |
| Roles | Role names and active flags are reset. |
| System settings | Not changed. Existing values are kept. |
| Demo accounts | Never created when `APP_ENV=production`; a warning is logged instead. |

The same table appears in `DEPLOYMENT-ICTU.md` under "Initialization and
repeat seeding".

## Do not

- Do not import the local development database. Its file records point to the
  local disk, and those documents do not exist in the bucket.
- Do not run `migrate:fresh`, `migrate:refresh`, `migrate:rollback`,
  `migrate:reset` or `db:wipe` on any database that holds data.
- Do not set `APP_DEBUG=true` on a reachable environment.
- Do not make the bucket public. Files are served only through the
  application, which checks who is asking.

## To confirm in Laravel Cloud

These depend on the Laravel Cloud account and were not verified when this
procedure was written:

- The exact variable names injected for the database and the bucket.
- Whether the command runner supports interactive prompts (step 5).
- The MySQL version. The migrations were verified on MySQL 8.4.
- The maximum request size, which limits uploads.
