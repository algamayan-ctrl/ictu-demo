# Pagination, Search and Filters revision tracker

Reconciliation — 16 November 2026: complete-register Laundry search/filter/sort is restored after the incoming merge. Numbered pagination and visible 7/15/30/50 choices remain, with legacy Laundry page sizes accepted for historical links. Active/completed filters are independent and empty results retain controls. My Borrowings restores Overdue, Return Processing and Open Accountability. See MERGE-RECONCILIATION-20261116.md for current verification; the original milestone evidence below remains historical.

Approved layout: search and applicable filters above the existing table, pagination below. Preserve columns, panel corners, authorization and row actions. Reference application: native PHP instance at http://127.0.0.1:8001.

| Status | Milestone |
|---|---|
| Complete | Review current lists and obtain approval of the proposed layout |
| Complete | Snapshot the existing working copy: 843 source files outside the repository |
| Complete | Inventory movement search, transaction/date filters and pagination; remove the 100-row cutoff |
| Complete | Admin Borrowing History pagination with summaries covering all matching records |
| Complete | Academic Period search, displayed-status filter, sorting and pagination |
| Complete | Special Dates search, status/date filters, sorting and pagination, including historical dates |
| Complete | Transaction and document histories: search, applicable filters and pagination |
| Complete | Full Analytics Records: search, column filter and pagination |
| Complete | Laundry active/completed/legacy archive filters apply before pagination |
| Complete | Gate Pass and approval queues: date filters and page-size controls |
| Complete | Regression, rendered-page, empty-state and interaction checks |
| Pending | Actual desktop/mobile screenshots and visual sign-off |

The pre-change snapshot is `C:\Users\Admin\.codex\tmp\ictu-table-controls-20261113\baseline`. Tests use disposable SQLite databases; the live database and server processes are not migrated or restarted.

## Table coverage

| Table/list | Treatment |
|---|---|
| Inventory catalogue; Borrower Request Records/My Borrowings; staff Request Records | Retain existing search, applicable filters and pagination |
| Borrower registrations; User Accounts | Retain existing record controls and permission checks |
| Verification/approval queues | Retain search/status/sorting and numbered pagination; add submission-date range, Clear and page size |
| Release/Return queues; Accountability current/resolved records | Retain existing controls and row actions |
| Inventory six-column Stock Card | Retain existing search, receipt/issue filter, pagination and reconciliation calculations |
| Inventory movement details | Add full-dataset search, type/date filters, page size and numbered pagination |
| Inventory Borrowing History | Retain existing search/status/overlap-date filters; paginate Admin view as well as AO view |
| Academic Periods; Special Dates & Closures | Replace page-local filtering with server filtering before pagination; retain activation/removal controls |
| Laundry active/completed and historical completed URL | Replace current-page-only search with matching-record filtering before pagination; retain statuses and result definitions |
| Accomplished Gate Pass archive | Retain complete-list search/sort/pagination; add recorded-date filters, Clear and page size |
| Transaction history; supporting-document versions | Add controls over the complete authorized history already supplied by the server |
| Full Analytics Records | Add filter and pagination to the complete existing metric dataset; preserve metric totals |
| Reports; All Transactions; Operational Audit Trail; Notification Delivery | Retain existing scoped search/filter/pagination pipelines |
| Dashboard and inline metric previews | Keep bounded summaries and their existing links to the full register |
| Weekly schedule, sanction settings, selected-request items, release/return inspection forms, one-record item breakdowns | Keep all fields/lines visible together; these are fixed configuration or transaction forms rather than record registers |
| Official documents, print and export tables | Preserve complete document output; interactive table controls are screen-only |
| Temporary Delegation legacy view | No active route exists; leave its source and authorization structure unchanged |

## Verification notes

- Initial regression: 59/60 passed. The existing AO Item Information tab expectation also fails against the pre-change snapshot; this revision does not change that tab.
- First targeted run: 16/16 passed, 178 assertions. Final focused run: 6/6 passed, 80 assertions, including the extended Laundry checks.
- Broader regression: 109/110 passed initially. The remaining test fixture lacked the original return deadline needed to exercise a late-return outcome; correcting that fixture passed the final focused rerun. No application workflow change was needed for that failure.
- Final frontend suite: 239 passed, 2 pre-existing failures, 1 skipped. Both failures were reproduced against the snapshot before this revision.
- 33 authorized role pages rendered successfully without duplicate IDs. All 213 Blade templates, 8 revised PHP files, 323 rendered inline scripts, 433 rendered style blocks and 176 source style blocks passed syntax checks.
- Browser-free interaction checks passed for complete-list pagination, search/filter composition, date boundaries, Clear/focus, page size, independent tables and unchanged row actions. These do not substitute for browser visual review.
- Browser connection reports a missing network policy. Windows screenshot capture also returned `computer-use request timed out: get_window_state` after recovery. Generated concepts are not proof of browser verification.
- Obsolete Laundry tests for filtering only the current page are replaced with disposable-database coverage of search/filter composition, records beyond page one, totals, sorting, pagination and mixed outcomes.
- Absolute zero risk is not claimed. Record queries and presentation have changed; approval, stock movement, release, return, billing, signature and document-access mutation rules remain outside this revision.

See [revision results and edited parts](TABLE-CONTROLS-REVISION-RESULTS.md) for the implementation details and test evidence.
