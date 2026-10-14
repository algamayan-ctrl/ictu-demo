# Analytics/report baseline reconciliation backlog

> Current 13 October batch: see [READINESS-BATCH-02.md](READINESS-BATCH-02.md). Earlier counts/statuses below are historical. Confirmed decisions: open return days, Admin Transaction Schedule, password-confirmed Google linking and password fallback, operational KPIs after deployment. Original broader SQLite: 1,081 cases / 1,048 passed / 33 failed; pre-change replay proves 32 inherited failures. The reconciled 33-method retest passed 33/33 with 279 assertions; current updated-runtime affected acceptance passed281/281 on SQLite and MariaDB,1855 assertions per engine. See the completed batch register for supplemental checks and scope limits. The superseded MariaDB broad run was intentionally terminated and is INCOMPLETE. Whole deployment gate remains NOT READY.

Status: FOR REVIEW. Full Analytics/system-quality gate: NOT READY. This register does not undo the previously verified nine inherited QA cases or the cancellation/registration/output gates.

## Verified distinction between current changes and inherited failures

The first broad SQLite run selected569 cases:501 passed,64 failed,4 errored. Replaying the failing method selection against saved pre-change application code selected98 cases:51 passed,43 failed,4 errored.47 of the initial68 failure/error cases also failed before this increment;21 represented changed active behavior. Receipts: workspace sprint1/analytics01-broad-first-sqlite.txt, analytics01-inherited-baseline-sqlite.txt and analytics01/initial-failure-classification.json. Never describe this as a whole-suite pass.

Obsolete positive prediction expectations were reconciled into negative checks for unavailable projection keys. Existing descriptive numerical assertions were retained; inherited separator/label assertions and panel targeting were reconciled to the current ZIP presentation. The inventory detail's structured-cell500 error was fixed by escaped primary/secondary rendering. MariaDB exposed a test-fixture tuple error: approvedOnly() selected the BorrowingRequest instead of RequestVersion; the actual version PK is now used. A new deliberately divergent-ID test failed against the old helper and verifies the correction. Its quantity assertion uses numeric90 across database drivers; initial broad receipts retain the resolved string-versus-number fixture failure.

## Remaining broader cases

These cases remain outside the bounded passing core/focused scope and are not silently skipped or certified complete. The original baseline proves their prior failure; no institution-sensitive behavior was changed to make them green.

| Area | Original broader failures | Investigation/acceptance needed |
|---|---:|---|
| AnalyticsLateReturnRateTest |3| Reconcile inherited separators/labels with exact completed-return counts/rates; retain factual denominators |
| AnalyticsOverdueAgingTest |9| Existing daysOverdue()/lateDaysBetween() use OperationalCalendarService::countOpenTransactionDays(RETURN); tests expect calendar-day ages and older card titles/status fixtures. Confirm institutional metric basis before changing calculations; distinguish open return days from calendar days. Do not change fees/sanctions/operating hours |
| AnalyticsRequestOutcomesTest |1| Reconcile old card wording while preserving filed cohort/outcome counts and source-record reconciliation |
| ReportDateRangeTest |10| Verify current date-control accessibility, displayed range formatting and exact pagination data/fixtures; keep server-side date validation |
| ReportPageTest |7| Map actual Admin operational-audit versus ICTU technical authority, current navigation/displays and report pagination. A test failure is not permission to alter approval/access policy |
| Full frontend suite |8 failures,1 existing skipped case| Current and pre-change runs both:191 total,182 passed,8 failed,1 skipped. Six Accountability presentation expectations and two Laundry presentation expectations need separate source/interaction reconciliation. These untouched areas are not new Analytics regressions |
| Desktop/mobile final visual acceptance | FOR REVIEW | Actual Admin sign-in/scheduled page and3/2/1 synthetic counts/groups verified. Screenshot exposed white KPI text on white cards; scoped theme-heading color applied. Final visual capture/retest was interrupted by repeated browser protocol/startup timeouts. Do not certify final pixel fit or physical-phone acceptance |

## Next small increment

Recommended S1-ANALYTICS-QA-02: requirement/test reconciliation before the next workflow milestone. First map each remaining failure to the saved baseline, current data query and actual workflow/institutional requirement. Keep source/manuscript references read-only, freeze main data/evidence, preserve existing authority, and ask for a material institutional decision only where definitions conflict. Add focused failing tests for the chosen contract, correct the smallest affected code/fixture, and obtain a clean two-engine related run. Record frontend and actual browser acceptance separately.

Separate subsequent work: remove inactive legacy prediction internals/templates after documenting their remaining pure-test references; audit truthful external descriptive/report breakdowns and scheduled item quantities/source datasets; confirm institutional KPI targets/baselines; Google OAuth/linking; physical workflow; protected persistent storage; security/backup restore; Cloud staging; nine ICTU requirements and TD RSC/title reconciliation. See LARAVEL-CLOUD-READINESS.md. No respondent sampling or manuscript edits are authorized here.
