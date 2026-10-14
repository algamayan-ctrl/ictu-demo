# S1-MERGE-INCOMING-01 — retained incoming conflict resolution

Status VERIFIED COMPLETE for the local merge increment. Bounded integration gate PASS. Institutional deployment gate NOT READY.

## Final verification — 14 October 2026

All 1,696 current backend cases have passing evidence on SQLite and MariaDB 11.4.12, across the full runs plus explicit focused retests. This is **not a claim that either raw full run was entirely green**. The full runs used a frozen source snapshot taken before the user answered the late-return policy question. Application runtime hashes remain identical across 687 checked first-party files; only the subsequently confirmed acceptance case changed. No backend case was removed or skipped.

| Receipt | Cases | Passed | Failed | Errors | Assertions |
|---|---:|---:|---:|---:|---:|
| Full SQLite, frozen source | 1,696 | 1,695 | 1 | 0 | 15,054 |
| Full MariaDB group A | 848 | 846 | 1 | 1 | 8,403 |
| Full MariaDB group B | 848 | 848 | 0 | 0 | 6,568 |
| Confirmed-policy / approval retest, SQLite | 6 | 6 | 0 | 0 | 439 |
| Confirmed-policy / approval retest, MariaDB | 6 | 6 | 0 | 0 | 439 |

The full-run failure is the inherited automatic late-return confirmation expectation, superseded after the user's explicit Head-review decision. The MariaDB setup error is a Windows package-manifest write collision between parallel test processes; the same approval case passes in the isolated retest. The MariaDB groups contain disjoint classes and together cover exactly 1,696 cases. A case-by-case union verifies 1,696 unique current passing cases per engine, with zero unresolved cases. Assertion counts are recorded per receipt and not added as if repeated tests were unique.

Additional checks: active PHP/Blade syntax 615/615; frontend 193 total, 192 passed, zero failed, one retained optional skip; pnpm/Vite build passed; Composer JSON/lock validation and platform checks passed. Composer retains its existing warnings about exact FPDF/FPDI version pins. VS Code's current Problems panel reports No Problems after restoring vendor symbol indexing and configuring PHP validation.

The kept preview database has five unapplied main migrations. A consistent read-only copy was created, its 160 evidence files copied, and all five migrations applied **only to that new copy**, without seeding. The upgraded preview has 76/76 applied migrations, no pending migrations, preserved data in all 71 original business tables, integrity OK and zero foreign-key errors. The original 72-table database remains unchanged. Updated preview: **http://127.0.0.1:8766/login**. Four public pages return HTTP 200. Six authenticated kernel checks pass with kept synthetic Head/SAO fixtures: Head dashboard, requests, reports and audit return 200; Head Laundry access returns the required 403; SAO Laundry returns 200. GET checks roll back their database transaction and use array transport only inside the smoke process; the browser preview reads existing private provider configuration.

Supporting compact receipts and log SHA-256 values: [INCOMING-MERGE-QA-RESULTS.json](INCOMING-MERGE-QA-RESULTS.json). Original red receipts, conflict stages, source snapshots and native logs remain in the local `sprint1/merge-incoming` evidence directory. The verified local commit stays on `review/spmu-main-merge-20261014-072648`; main and the saved checkpoint remain intact.

## Current reconciliation evidence

- Incoming is the saved local checkpoint `backup/local-spmu-20261014-072648` (`eaaade7`); Current is updated main (`b1fdfaa`). The working branch remains the review branch. Incoming was selected in 93 conflicted blocks across 41 files; auto-merged compatible main code remains.
- Corrected integration defects: balanced notification callback/imports; reconstructed SQL audit method with compatible view counts; restored Laundry tab containers without duplicate filters; private storage aliases, safe diagnostics and fresh-object rollback cleanup; registration storage exception handling; production demo refusal logging; delayed approval notification with no fabricated pickup time; closed the report Blade section; restored request ID metadata for authorization-aware report links.
- Retained main's production trace redaction, audit pagination/recovery safeguards, private profile-picture routing, read-only All Transactions landing screen and clock-aware transaction advisory. Incoming external affiliation, evidence validation, approval/reservation rollback and role gates remain in place.
- Fixture mapping uses readable synthetic PDFs/PNG signatures, canonical actor role assignments, explicitly selected report scopes, open-return-day bands, current schedule wording and SQL pagination. Assertions for evidence integrity, unauthorized access and rollback remain enforced.
- Added private-storage alias-conflict coverage, schedule boundary coverage, Laundry tab navigation and Blade-section regression coverage. Latest frontend: 193 total / 192 passed / 0 failed / 1 existing optional skip. Latest active PHP syntax: 615/615 passed. Installed 136 PHP packages match the merged lock in both preview and isolated test runtime; frontend build passed.
- First full merged SQLite run: 1,694 total / 1,596 passed / 77 failed / 21 errors, 14,018 assertions. First correction batch: 322 total / 311 passed / 1 failed / 10 errors. These are retained red receipts; final evidence is above. Obsolete initial MariaDB runs were interrupted and are not complete results.
- VS Code's inherited vendor exclusion caused unresolved Laravel types/functions. The workspace now includes installed vendor symbols, PHP 8.4 is selected for analysis, and the local PHP validator uses the verified runtime. The current Problems panel reports No Problems.

### Confirmed late-return review authority — 14 October 2026

The user explicitly confirmed retaining Admin/Head decision before late-return violation confirmation and sanction. `CustodyService` from updated main already leaves the violation `PENDING_REVIEW`; this runtime behavior is retained. The obsolete auto-confirmation case in `CompleteWorkflowTest` now verifies the actual physical return, no premature sanction, denied Borrower/SAO review, and explicit Head confirmation with one recorded sanction. The updated case and five external approval/reservation variants pass together: 6/6 and 439 assertions on SQLite, and 6/6 and 439 assertions on MariaDB 11.4.12. This decision is distinct from the already confirmed rule that only missed-pickup cancellation requires SAO confirmation.

The fresh final runners have separate databases, storage and compiled views. One MariaDB case encountered a Windows access-denied error while parallel runners initially wrote the same package manifest. Subsequent runners isolate package and service manifest paths as well. Its independent six-case MariaDB retest passed. The full and focused receipts remain separate; a full run containing the old policy expectation is not described as an all-green run.

The full backend runs use fresh SQLite/MariaDB fixtures with array mail/session/cache; no real Google/email delivery or institution-approved deployment is certified by these tests. Test source, classes and compiled-view paths have been checked to point to the isolated snapshots, not the kept preview/runtime data.

### Requirement-to-regression mapping

| Confirmed requirement or retained main behavior | Implementation and verification |
|---|---|
| Google identity, email ownership, SAO approval and safe account linking | Existing authentication/registration controllers; `GoogleAuthTest`, `GoogleRegistrationOnboardingTest`, `GoogleAccountLinkingTest`, registration feature cases. Live provider delivery remains separate. |
| Reviewed external affiliation, immutable corrected evidence and approval-only allocation | Existing request services and external report metadata; `ExternalBorrowingDraftTest`, `ExternalBorrowingSubmissionTest`, `ExternalBorrowingRevisionTest`, `ExternalBorrowingRejectionTest`, `ExternalBorrowingApprovalTest`. |
| Private evidence, legacy recorded-disk reads and rollback preserving prior objects | `ProtectedFileService`, registration exception handling and configuration aliases; `ProtectedStorageCompatibilityTest`, `ProtectedStorageDiskTest`, actual external rollback cases. |
| SAO physical custody; Admin/Head decision before late-return confirmation and sanction | Retained custody and policy services; `CompleteWorkflowTest` physical-return/review case and accountability authorization/consistency cases. |
| Manual cancellation authority with SAO confirmation only for missed pickup | Existing workflow guards; `SaoConfirmedCancellationTest`, `RequestCancellationWorkflowTest`, `PreparationDiscrepancyCancellationTest` and deadline cases. |
| Descriptive/scheduled demand, open-return-day aging and Admin Transaction Schedule | Existing analytics/calendar modules; analytics outcome/aging cases, `OperationalAdvisoryServiceTest` including opening/closing boundaries. |
| Scope-safe reports, paginated audit records and authorized recovery | Reconciled report controller/builders; `ReportPageTest`, `ReportSystemPreviewTest`, `ReportDateRangeTest`, `AuditRecoveryTest` and audit investigation cases. |
| Consistent operational UI and private operational status interpretation | Repaired Laundry tab/filter containers and report Blade section; 192 passing frontend cases and one retained optional skip. |

### Data and runtime preservation

The kept preview was compared read-only with the private pre-sync backup: 72 noninternal table schemas/data unchanged, SQLite integrity OK, zero foreign-key errors. All 160 backed-up stored evidence files, both private environment files and eight available read-only references are unchanged. Main and saved-checkpoint branch tips are unchanged. Public login, registration, forgot-password and email-verification pages return HTTP 200 at the existing localhost preview. This verifies page availability, not real Google sign-in or inbox delivery.

## Objective and authorization

The user explicitly authorized retaining Incoming changes at conflicts and correcting resulting errors in the open VS Code project. Current branch:review/spmu-main-merge-20261014-072648; current parent:b1fdfaafdfe08103d8b8217e8209b6219e1cdbe2; Incoming:eaaade751e310d2836f1e4b1bcca8f3c20e0093c. There are41 unmerged text files. Resolve each marked block using its Incoming section, preserving already-merged nonconflicting main edits. The current worktree, conflict stages and index are saved first. No remote push or main-branch movement is part of this increment.

## Requirements and boundaries

Preserve the confirmed authentication/registration policies, Admin/SAO authority, employee-unit permission checks, saved external affiliation, readable private E-sign evidence, descriptive/scheduled-demand-only analytics, Admin Transaction Schedule, open-return-day reporting, and SAO confirmation for missed-pickup cancellation. Existing manual cancellation authority remains unchanged. No institution rule, fee, role or infrastructure choice is invented by this merge. Manuscript XP/BPR requires small correction and regression evidence before the gate passes.

## Actors, story and states

Story: as the development partner, I need the saved local fixes retained in disputed sections while compatible updated-main improvements survive. App actors retain existing Borrower/SAO/Admin/ICTU scopes. Preconditions: verified merge parents, copied conflict versions and intact private/runtime data. Postconditions: zero conflict markers/unmerged entries, valid selected dependency files, passing isolated regression checks and a local merge commit on the review branch. Git state: in-progress conflicted merge → resolved/staged review → verified local merge commit. App approval, allocation, signature and physical-custody states retain their confirmed guards.

## Inputs, output and database impact

Inputs are41 conflict files plus the auto-merged source, saved QA checkpoint and newer main. Outputs: conflict-block resolution register, scoped compatibility fixes, test evidence and local merge result. No migration/reset/seed of the kept preview or original Docker database is planned. Fresh SQLite/MariaDB fixtures are used for tests. Sources and private .env files stay intact.

## Risks and acceptance

Incoming selections can leave references to functions/services removed by main or break composed JSON/dependency content hashes. Auto-merged files can be semantically inconsistent even without markers. Check syntax, Composer validity/platform, frontend and full backend suites on both engines. Fix integration defects within confirmed behavior, preserving new-main improvements where compatible. Do not silently adjust institutional behavior or delete/skip critical tests. Preserve before/after and failed-run evidence. Actual Google/email, hosting/quality sign-off and operational KPI gates remain distinct from this merge.

## Conflict files

- app/Http/Controllers/AnalyticsController.php
- app/Http/Controllers/BorrowingRequestController.php
- app/Http/Controllers/ReportController.php
- app/Http/Controllers/UserAdministrationController.php
- app/Providers/AppServiceProvider.php
- app/Reports/Builders/ApprovalDecisionReport.php
- app/Reports/Builders/BorrowingActivityReport.php
- app/Reports/Builders/OffCampusGatePassReport.php
- app/Services/AnalyticsCardDetailService.php
- app/Services/AnalyticsDetailService.php
- app/Services/AnalyticsService.php
- app/Services/NotificationService.php
- app/Services/ProtectedFileService.php
- app/Services/RequestWorkflowService.php
- composer.json
- composer.lock
- docs/DEPLOYMENT-ICTU.md
- resources/views/administration/users/form.blade.php
- resources/views/analytics/index.blade.php
- resources/views/analytics/partials/borrowing-returns.blade.php
- resources/views/analytics/partials/detail-panel.blade.php
- resources/views/analytics/partials/inventory-health.blade.php
- resources/views/analytics/partials/overview.blade.php
- resources/views/laundry/index.blade.php
- resources/views/reports/detailed.blade.php
- resources/views/reports/notifications.blade.php
- resources/views/reports/partials/report-builder.blade.php
- routes/console.php
- tests/Feature/AnalyticsCardDetailReconciliationTest.php
- tests/Feature/AnalyticsLateReturnRateTest.php
- tests/Feature/AnalyticsOverdueAgingTest.php
- tests/Feature/AnalyticsRestructureTest.php
- tests/Feature/BorrowingEligibilityTest.php
- tests/Feature/ItemPreparationStep2ContractTest.php
- tests/Feature/LinenReturnInspectionFormTest.php
- tests/Feature/OffCampusGatePassWorkflowTest.php
- tests/Feature/ReportDateRangeTest.php
- tests/Feature/ReportInventoryUtilizationTest.php
- tests/Feature/ReportPageTest.php
- tests/Feature/RoleWorkspaceSmokeTest.php
- tests/Frontend/laundry-operations.test.cjs
