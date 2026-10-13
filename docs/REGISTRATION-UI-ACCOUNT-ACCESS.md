# S1-UI-01 — registration, account actions and role navigation

Status: VERIFIED COMPLETE for the requested code/UI increment. Selected gate: PASS. Whole-system/deployment gate: NOT READY.

## Requirements analysis (before implementation)

Objective: apply the partner's 12 October UI revisions to the existing Laravel/Bootstrap system without changing borrowing eligibility, verification or approval authority.

Confirmed sources: the current user's numbered revision request, supplied registration layout image, prior approved registration/routing decisions, and the existing ZIP's shared icon and account-directory styles. The manuscript/workflow registration and SAO/Admin separation continue to apply. The screenshot is a layout reference; its eligibility, Google authentication and ICTU wording do not independently authorize policy changes. Missing hosting/ICTU attachments remain an unresolved deployment evidence gap.

Actors and stories: an anonymous applicant chooses Employee or External and submits the existing validated fields; SAO searches a compact registration list and opens protected applicant details to decide; Admin deactivates another account within its existing operational management scope and views transaction summaries in Request Records; ICTU retains technical account management; Borrowers retain their own dashboard and My Borrowings.

Preconditions: active, verified, approved actor and existing workspace/role checks for protected actions. A target must fall within the actor's account-management scope. Postconditions: deactivation changes ACTIVE to INACTIVE once, preserves identity/history/role assignments, records the actor and denies subsequent portal access. Registration submission/review continues through existing services and validation.

Business rules: no self-deactivation; Admin cannot deactivate ICTU; SAO/Borrowers cannot manage accounts. Recheck a locked target before a write. Repeat deactivation produces no duplicate status audit. Approval and OTP are independent. Nonselected registration fields are disabled, and External never acquires a fabricated CSPC assignment. Rejection remarks remain mandatory. No walk-in, predictive, automatic cancellation, sanction or eligibility changes.

Inputs/outputs: existing registration fields with password confirmation; list search/type/status filters; protected detail/ID links; confirmed account-deactivation PATCH. Outputs: responsive labelled forms, compact paginated records, unchanged review decisions and notices, account status/audit and Request Records links.

Database impact: no migration or new table. Existing user status/audit/notification records only. Existing registration PENDING to APPROVED/REJECTED transitions remain unchanged. Draft/request/custody processing states remain unchanged.

Failure scenarios: forged target/role, self-deactivation, stale target becoming ICTU, duplicate submission, audit failure, invalid/mismatched registration fields, missing private ID, filter tampering and small screens. Preserve server authorization, transactions, escaped output, required documents and safe field selection.

Proposed implementation details: reuse ZIP account-directory styles and shared SVG icons; scoped registration styles with desktop label/input rows and mobile stacking; account-type cards backed by the existing selector; compact SAO table and separately authorized review page; Admin Release/Return navigation and dashboard shortcuts point to Request Records, retaining the existing Admin discrepancy-resolution authority. Dedicated SAO release/return routes remain forbidden to Admin.

Acceptance: Admin sees a protected power action beside Manage for permitted targets; cancellation writes nothing; confirmed action creates one audit and preserves linked data; forbidden actors/targets are denied, including refreshed scope; SAO list remains compact, searchable and paginated with details and private ID access preserved; responsive fields maintain all validation; Admin has no Release/Return navigation or operational dashboard links, SAO retains Release/Return, Borrower retains My Borrowings. Related selected regression passes on SQLite and MariaDB 11.4. Review browser evidence and open the working project in VS Code with an updated local preview.

Dependencies/risks: the already running preview uses an owned SQLite demo database and array mail. Its data must be preserved. Tests must use a separate source/cache snapshot. Existing general CSS differs from the ZIP only in previously compacted heading sizes; do not blindly overwrite those established adjustments. No production deployment or real mail/OAuth certification is part of this increment.

## Verification evidence

## Implemented changes and components

- Admin deactivation: existing transaction/lock/history-preserving action is now available through the shared SPMU/ICTU route and UserPolicy, with refreshed target authorization inside the lock. Admin is limited to Borrower/SAO/Admin targets; self/ICTU targets and unauthorized actors are denied. A modal confirms before the common duplicate-submit lock. Already inactive/suspended targets are idempotent. Existing audit action is retained with actor and ADMIN versus ICTU change-source attribution. No role/identity/transaction is deleted.
- Public registration: account-type cards and the existing selector, shared SVG input icons, aligned desktop rows/mobile stacking, private ID help, password confirmation/toggles and Pending SAO note. Added registration-field component, scoped style partial and a phone icon to the existing SVG family. Conditional fieldsets stay disabled and hidden together; unit/division selection and all server validation remain.
- SAO records: reused the ZIP's unchanged directory style partial for a compact table, validated search/type/status filters and ten-row pagination. Added separately authorized registration detail/review route, policy method and view. Private ID access, reviewer history, remarks requirement and existing decision service are preserved.
- Admin navigation: removed Release & Return entry. KPI/overview links now lead to Request Records; Active Custodies has a guarded database filter and clear banner. Legacy Head custody-list links redirect to Request Records. Dedicated SAO Release/Return endpoints remain forbidden to Admin. Existing targeted discrepancy-resolution authority remains under Request Transaction Oversight; it is not a release/return processing permission.
- Relevant controllers: UserAdministrationController, BorrowerRegistrationReviewController, BorrowingRequestController and CustodyController. Relevant policies: UserPolicy and BorrowerRegistrationPolicy. Routes, role layouts/dashboard/request-list views, registration views and the account-directory view were updated. No new migration/table/framework/database architecture change.

## Verified evidence

Evidence lives in the project workspace's `sprint1/` folder, separate from the working checkout and read-only sources.

| Check | Result | Evidence |
|---|---|---|
| Selected SQLite regression | 293/293; 1946 assertions; exit 0 | ui-accepted-sqlite.txt |
| Same selected MariaDB 11.4.12 regression | 293/293; 1946 assertions; exit 0 | ui-accepted-mariadb.txt |
| Frontend regression | 29/29; exit 0 | ui-frontend-accepted.txt |
| Final UI/public-form checks after the radius correction | Each engine 10/10; 73 assertions; exit 0 | ui-final-view-sqlite.txt, ui-final-view-mariadb.txt |
| Actual two-worker Admin deactivation | Both stale-bound workers redirected; one status audit, one notification, history and role retained | ui-deactivation-one.txt, ui-deactivation-two.txt, ui-deactivation-inspect.txt |
| Browser | Desktop/type switching, real external submission, SAO list/filter/details/rejection validation, Admin navigation/KPI/Cancel/Confirm, External dashboard, phone-width rendered layouts and password toggle | ui-browser-verification.md |
| Source/code review | Scoped Pint passed; modified PHP/routes syntax passed; git diff --check passed; source hashes retained | ui-file-manifest.json |
| Preview | Employee/External registration HTTP 200; existing demo data preserved; project window opened in VS Code | preview-running.json, ui-browser-verification.md |

The historical S1-UI-01 accepted suite explicitly excluded nine assertions independently failing in the original development snapshot; its actual exclusion filter remains evidence. **S1-QA-BASE-01 subsequently reconciled all nine without exclusions**, preserving the ZIP layout and authorization, and passed 305/305 affected cases with 2064 assertions per engine. See INHERITED-REGRESSION-ISSUES.md and QA-BASELINE-RECONCILIATION.md. Both increments are selected-scope verification, not a full-suite pass. The initial UI focused exception-check error was a test harness catch mismatch (Laravel AuthorizationException versus an HTTP exception); it was corrected without weakening the policy. Additional readable-PDF fixture and extracted-partial assertions were repaired without loosening upload/document rules.

The initial native concurrency setup used an unmigrated disposable database and then invoked a seeder without its Laravel container; neither attempt is treated as passed. Retained diagnostic logs explain both failures. The corrected setup uses guarded migrations and the framework db:seed command, and the wrapper requires JSON evidence rather than trusting exit 0 alone.

The final radius-token correction was made after the larger regression; final UI/public-form checks and browser/HTTP rendering cover that small CSS fix. Functional test storage, Blade caches and temporary files are isolated from the user preview and between database engines. No working/live database migration/reset, deployment, commit/push or synced-reference edit occurred.

## Completion and next checkpoint

- [x] Requirements/actor/data/state/error/acceptance mapping before implementation.
- [x] Requested code/UI revisions, authorization review and automated/native concurrency verification.
- [x] ZIP shared directory styles preserved; existing color/radius/font tokens reused with scoped overrides where the base button selector otherwise hid primary/danger colors.
- [x] Responsive rendered layout, protected review flow and original External dashboard inspected.
- [x] Updated localhost preview running and working project opened in VS Code.
- [ ] VS Code integrated-terminal launch: Windows Computer Use guidance prohibits automated terminal commands. The verified preview runs through the normal local runner instead; no integrated-terminal execution is claimed.
- [x] Nine inherited assertion mismatches reconciled in S1-QA-BASE-01 with retained before/after evidence.
- [ ] Full-system/deployment evidence gates.

Next small checkpoint after verified S1-QA-BASE-01: **S1-ROUTE-02A**, the actual external SAO correction/re-sign increment. The document-timing requirement and institutional deployment evidence remain separate.
