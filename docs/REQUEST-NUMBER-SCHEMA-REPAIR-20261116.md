# Request-number schema repair — 16 November 2026

## Cause

The native PHP database at `C:/Users/Admin/ictu-demo/database/database.sqlite` had not received the existing `2026_11_13_000001_create_request_number_counters_table` migration. The current request-header code therefore failed before it could create a request.

The earlier isolated tests built a complete schema and did not detect this local deployment gap. Read-only inspection confirmed zero requests, versions, supporting documents and signature snapshots after the failed attempts; no partial request needed removal.

## Completed repair

- [x] Preserve the affected source files and hash the 883-file working copy.
- [x] Create a consistent SQLite backup with `VACUUM INTO` before changing the native database.
- [x] Apply the single counter-table migration to a disposable database copy first.
- [x] Verify request-header creation on that copy and roll back the probe. Existing application data remains unchanged.
- [x] Apply only the same single migration to the verified native SQLite database.
- [x] Verify the result against the backup: 66 existing application tables retain their data and schema hashes. Only `request_number_counters` and its migration-ledger entry are added; the new table remains empty until an actual request is created.
- [x] Add a readable setup-validation error if the counter schema is missing on another installation, instead of an unhandled SQL exception.
- [x] Verify the login page responds with HTTP 200.

The migration creates an empty counter table. It does not rewrite request numbers or existing records, apply signatures, submit a borrowing request, change stock, or rebuild Docker.

## Regression results

- Before source edits: 42 numbering/workflow tests passed, 981 assertions.
- After the repair guard: 46 tests passed, 1,018 assertions, covering existing numbering, academic-year behavior, collisions/rollback, approval/release workflow and upload oversize responses.
- Final compatibility check: both new schema tests passed, 30 assertions. The positive case removes all staged academic-numbering tables to reproduce the native schema, reinstalls only the counter table, submits a request with synthetic evidence/signature consent and opens its details successfully. The negative case confirms a missing counter produces a setup error with no partial request, documents, signatures or notifications.

These are isolated database checks. The repair did not submit a request using the real borrower's account or signature. Earlier unrelated full-suite failures remain recorded in `REQUEST-UPLOAD-FIX-20261116.md`.

## Academic-year activation remains separate

The `2026_11_14_000001_create_academic_request_numbering_tables` migration remains pending. Academic-year numbering is not activated, and there are still no configured Academic Periods. This repair restores the existing current numbering flow only. The approved first-submission academic-year numbering remains subject to the user's calendar configuration and the reviewed activation procedure in `CONTROL-NUMBER-REPORTS-ACTIVATION.md`.

## Evidence

Private backup and receipts: `C:/Users/Admin/.codex/tmp/ictu-numbering-schema-fix-20261116`.

- `native-before.sqlite`: consistent pre-repair database backup.
- `database-before-manifest.json`: pre-repair row/schema hashes.
- `copy-verification.json`: disposable-copy validation and rolled-back probe.
- `native-repair-verification.json`: exact schema and preserved-data comparison.
- `baseline-tests.log`, `regression-tests.log`, `legacy-schema-tests.log`: test results.
- `scope-results.json`: source-change scope.

Application source changes are limited to the numbering-service guard, its existing feature test and this tracker. The existing migration file is unchanged.
