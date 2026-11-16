# UI Admin revision tracker

Approved scope: the ICTU UI Revision Plan confirmed on 13 November 2026. Existing application structure, restored corner styles, authorization and operational rules are retained.

Panel follow-up: [Pagination, Search and Filters tracker](TABLE-CONTROLS-REVISION-TRACKER.md). Its functional implementation is complete; actual browser visual sign-off remains pending.

| Status | Milestone |
|---|---|
| Complete | Review markdown, source and approved visual concepts |
| Complete | Confirm report semantics, sidebar groups, closures and resubmissions |
| Complete | Preserve current working copy (833 files) outside the repository |
| Complete | Record isolated regression baseline |
| Complete | Shared dark styling, compact layouts, sidebar and document viewing |
| Complete | Borrower settings, Premises, inventory drilldowns and Stock Card |
| Complete | Fixed-highlight time picker and closure-aware date picker |
| Complete | Summary Report, report filters and monthly future request numbers |
| Complete | Analytics drilldown reconciliation |
| Complete | Isolated regression, export, numbering and permission checks |
| Pending | Actual desktop/mobile screenshots and visual verification |

## Protection and validation

- Working-copy backup: `C:/Users/Admin/.codex/tmp/ictu-ui-admin-20261113/baseline` with SHA-256 manifest and original Git status.
- No live deployment or live migration is part of this revision.
- Existing database records, official signatures, numbering and source changes are preserved.
- Frontend baseline: 246 tests, 243 passed, 2 existing failures, 1 skipped. Failures: preparation discrepancy AO recheck assertion; Borrower Request Records static Draft option assertion.
- Original source and original tests: 144/144 targeted PHP cases passed. Revised suite: 201/201 cases, 1507 assertions. An additional ledger grouping check passed in its 4-case suite.
- Final frontend suite: 249 cases, 246 passed, the same 2 baseline failures, 1 skipped. New picker unit checks all pass.
- Four concurrent workers created 40 unique request numbers in the isolated fixture database.
- All 24 selected role pages rendered; 235 rendered JavaScript blocks, 329 rendered CSS blocks and 173 source style blocks parsed without errors.
- DOM interaction checks passed for closed dates, reason controls, lookup failure, Escape, time typing/Apply/Cancel and inventory dialogs. These are not browser visual checks.
- Summary Report Print, CSV, PDF, Word and Excel output checks passed; its one-page PDF was rendered and visually inspected.
- Final 25-case export/sorting suite passed (110 assertions), including multiple equipment lines in Word, chronological date sorting, Excel date types and preserved name text.
- All 210 Blade templates compile to valid PHP.
- Changes are documented in `docs/UI-ADMIN-REVISION-RESULTS.md`; the final file inventory is listed there.
- Calendar retains its original grid, toolbar, filters and Month/List views. Request form retains its steps and field order.
- Summary Report uses the latest submitted version; historical request IDs remain unchanged.

## Remaining visual acceptance

- Actual desktop/mobile browser screenshots and light/dark visual sign-off remain pending. The in-app browser reports a missing network policy; the supported Chrome connection is unavailable. Standalone browser sessions could not create a page. Automatic approval review rejected the separate Chrome screenshot helper with “blocked by policy” and no further reason.
- Preserve the original calendar, request-form layout and corner styles during visual sign-off.
- Implementation is in source only. Rebuild the deployed Docker image and apply the additive migration during a separate deployment; neither was performed on the live system.
