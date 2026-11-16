# Approved revision reconciliation — 16 November 2026

Workspace: `C:/Users/Admin/ictu-demo`. Reference instance: `http://127.0.0.1:8001`. Starting commit: `7b60c0b`. The user approved selective repairs while retaining useful incoming improvements.

## Progress tracker

- [x] Preserve the clean starting source ZIP, Git state and preceding audit evidence.
- [x] Restore complete-register Laundry filtering before pagination, including completed records and historical completed URLs.
- [x] Keep incoming numbered pagination, visible 7/15/30/50 choices, useful wording and row actions. Accept legacy Laundry page sizes for existing links.
- [x] Restore final signature confirmations and required server consent for billing and current/legacy Accountability decisions; preserve physical compliance processing.
- [x] Restore compact spacing values selectively while retaining new pagination styles and original corner rules.
- [x] Combine incoming fullscreen icon controls/native API with shared Escape, focus, keyboard and background-isolation behavior; check WebKit and native refusal.
- [x] Restore Overdue, Return Processing and Open Accountability filters without changing status calculations or permission checks.
- [x] Preserve the old valid billing PDF when replacement generation fails; retain cashier-copy layout, historical copies and authorized latest-copy redirects.
- [x] Run 35 focused frontend checks, all passed with none skipped.
- [x] Run 278 unique selected backend tests across scoped runs: 276 passed, two retained baseline failures, 2,077 assertions.
- [x] Reproduce both retained failures in an isolated copy of the starting source; no new unexplained failures remain in these checks.
- [x] Check changed PHP syntax, whitespace and source scope.
- [ ] Actual desktop/mobile visual acceptance. Browser capture remains unavailable; DOM checks and rendered HTML do not establish visual acceptance.

## Implementation

**Laundry:** `LaundryRecordTable` filters the complete authorized dataset before pagination. Both queues use GET forms with Search, Status/Return Result, Sort, Apply and Clear. Searches find records beyond page one. Each queue preserves the other's filters; its own filter submission resets its page. An explicit tab parameter keeps the right tab open when both queues have parameters. Page-size changes preserve filters and reset the appropriate page. The completed archive maps its older page/per_page parameters to canonical completed parameters. Empty results keep the filter form accessible. The missing completed-interactions include was removed; these are server filters rather than page-local controls.

**Signature consent:** AccountabilityController's approved `0019fdc` version was restored after checking that its incoming differences were the consent changes and comment/whitespace removals. Both Head views retain their incoming layout, labels and other controls; only signing hooks, hidden consent values and the final-confirmation dialog were restored. Missing/rejected consent is checked before signing or mutation. Dialog cancellation/Escape preserves entered values; duplicate submission remains blocked. Physical compliance actions do not receive signing hooks. Manual report signatures remain unchanged.

**Spacing:** A CSS syntax-tree comparison restored 154 spacing declarations only where an existing selector's incoming numeric padding/margin/gap increased from `0019fdc`. New selectors and non-spacing properties were retained. Examples: table cell padding 16px to 12px, card-header gap 14px to 12px, filter padding 14px to 12px. Existing compact UI overrides still apply. No border-radius or color declaration was changed by this operation. The selector/property/value inventory is in `compact-spacing-changes.json` in the evidence directory.

**Fullscreen:** Incoming icon buttons and native API support, including WebKit, remain. A single shared handler also preserves focus restoration, Tab containment, Escape, scroll restoration and prior inert states of background controls. Native refusal retains a full-window fallback; label updates preserve icons. Authenticated users receive the control only within already authorized document views. File URLs, orientation and proportions are unchanged. Tests now inspect the current control's semantic marker instead of requiring one obsolete exact HTML string.

**Borrower filters:** Overdue, Return Processing and Open Accountability return alongside the incoming concise choices. Existing matching methods perform the filtering. Underlying statuses, calculations and permissions are unchanged.

**Billing reissue:** Generation/metadata replacement runs in a database transaction with the billing row locked. The replacement is generated before earlier FINAL copies become SUPERSEDED. A rendering failure preserves the old FINAL document and billing row. Existing authorization snapshots may remain part of the historical document; the operation captures no new signature and changes no billing amount/date. A disposable-fixture failure test verifies this behavior. No live bill was reissued and the refresh command was not run.

## Retained and protected

The automatic Academic Period resolver/configuration, Configuration dropdown, entries control, report builders/manual signatories, Audit Trail filters, native request-date controls, approval logic, stock movement calculations, custody/release/return services and policy calculations remain unchanged from the starting merge. Incoming cancellation/rejection/revision explanations, notification wording, dashboard instructions, pagination components, cashier document layout and protected-file access fixes remain.

No live migration, renumbering, seed/reset, deployment, Git commit or whole-merge revert was performed. Tests use disposable in-memory SQLite. No autostart task was installed.

## Verification and baseline findings

The starting audit had seven failures in 70 selected backend tests. Laundry search was separately confirmed broken: a one-record search returned 23 records. The approved repairs address those regressions. Pagination expectations were updated only for the retained intentional choices: Laundry/My Borrowings default to seven; Stock Card displays seven screen rows while printing all matching entries. Assertions still cover complete-register search, unique records across pages, query preservation and complete print output.

All 35 focused frontend checks pass: consent, cancellation, duplicate prevention, native/full-window fullscreen, keyboard/focus/background isolation, icon preservation, WebKit, tab selection and read-only filter/pagination integration. The four outdated Laundry integration assertions now verify the retained new pagination components and GET forms instead of retired footer markup.

Final scoped backend evidence covers 278 unique tests: 276 pass, two fail, 2,077 assertions. The protected Stock Card suite passes all 12 tests, including all 105 matching print entries and all 52 filtered issue entries. Changed PHP syntax and whitespace checks pass. This is selected regression coverage, not an exhaustive full-system run.

The broader frontend comparison against the isolated starting source has 289 cases in the revised copy: 256 pass, 19 fail and 14 skip. The starting copy had 287 cases: 250 pass, 23 fail and the same 14 skips. All 19 remaining failure names also occur in the baseline; no new failure was introduced in that comparison. The remaining failures concern existing Accountability presentation, revision-panel styling, date filters, discrepancy preparation, upload visibility, Officer record/release/return pagination and empty-state sizing. They remain outside this selective repair; see `frontend-comparison.json` for the complete names. The 35 focused checks above run without skips and include the two newly added fullscreen cases.

Two retained display failures were reproduced in the isolated pre-change `7b60c0b` source, with autoload paths checked against that copy:

1. `AccountabilityDocumentLabelingTest::test_late_return_billing_statement_uses_a_compact_cashier_copy` expects literal `Effective Date:` in the incoming billing output.
2. `LateReturnAccountabilityTest::test_a_paid_case_appears_in_resolved_history_with_its_payment_record` expects literal `Verified At` in the retained resolved-history display.

These are recorded separately from the repaired regressions. The official cashier template and unrelated history presentation were not altered to satisfy those assertions. Final visual acceptance of retained incoming layouts remains pending.

Evidence: `C:/Users/Admin/.codex/tmp/ictu-reconcile-20261116` contains the source archive, isolated baseline, baseline/current XML, merged results, frontend output, rendered configuration HTML and spacing comparison.

Final source review confirms that the shared stylesheet retains the same 4,371 declarations and structure: exactly 154 spacing values differ, with all other CSS declarations preserved. The approved Academic Period/entries, report output/builder, audit filter, stock/policy/custody/request-workflow and approval source areas match the starting merge. The running port 8001 login endpoint returned HTTP 200 after verification. The temporary baseline environment-file copy was removed after its isolated checks.
