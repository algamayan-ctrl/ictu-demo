# Control Number and Reports revision results

Implemented the approved proposal and More filters layout in the current working copy. No live migration or numbering activation was performed.

## Implemented behavior

- Shared compact Reports toolbar: primary filters and search on top, anchored More filters for Division, Office/College/Unit and Borrower where applicable. Long lists use matching search results. Popup edits are staged; Cancel, Escape and outside click discard them, while Apply refreshes once and returns to the first record page. Page size is preserved.
- All Transactions now uses one row per submitted request, the latest submitted version, seven data columns and an authorized screen-only eye action. Unsubmitted drafts are excluded; cancelled, expired and unsuccessful submitted records remain available. Historical event-ledger links remain supported through their type/from/to parameters or `view=events`.
- Received Date & Time comes from the earliest recorded SPMU verification/decision for the selected submitted version. Automatic receipt steps and generic update timestamps are excluded. Summary duration runs from that review to physical release; its reporting month remains the latest resubmission. Missing review facts are identified rather than invented. MET/UNMET stays plain table text.
- Inventory category and availability labels, Inventory/Utilization period choices and grouped accountability status choices follow the approved definitions. Existing calculations, exact historical filters and legacy report handlers remain supported.
- Print and Download use the same matching dataset and include all matching rows across every page. The download dialog keeps the existing supported formats and presentation controls.
- Academic-year numbering is staged behind an explicit activation switch: `BR-YYYY-MM-N`, no leading zeros, sequence across the configured academic year, assigned on first explicit submission, no draft consumption and no number change on corrections. Counter allocation is transactional; SQLite obtains a write lock before counter reads. Historical mapping is previewed, fingerprint checked, journaled and reversible before later activity. Old references remain searchable aliases and appear in request details.

## Verification

Evidence directory: `C:\Users\Admin\.codex\tmp\ictu-control-reports-20261114`.

| Check | Result |
|---|---|
| Pre-change focused baseline | 24 passed, 93 assertions |
| Broad report/numbering regression | 174 passed, 2 existing failures; 1,935 assertions |
| Preserved broad baseline | 148 passed, the same 2 existing failures |
| Final targeted submission/numbering/report/export run | 58 passed, 755 assertions |
| Rollback/collision guards | 2 passed, 6 assertions |
| Final selector and report checks after desktop review | 31 passed, 248 assertions |
| More filters DOM interaction checks | 8 passed |
| Concurrent allocation | 4 workers, 40 submitted requests, 40 unique numbers, sequence 1–40; 4 drafts excluded |
| Print/CSV/PDF/Word/Excel parity | All 12 matching requests included despite page 2 showing only 2; excluded request absent |
| PDF inspection | All 12 matching identifiers found; one-page landscape table visually checked |
| Blade compilation | 215 templates passed |
| Rendered syntax | 4 report pages; 32 executable scripts, 10 JSON blocks and 109 style blocks passed |
| Frontend suite | 239 passed, the same 2 previously documented failures, 1 optional browser test skipped |

Existing PHP failures are `ReportSystemPreviewTest::test_reports_opens_on_all_transactions_across_all_dates_by_default` and `ReportSystemPreviewTest::test_all_transactions_filters_search_and_dates_without_changing_formal_reports`. Both already expect an all-dates default that differs from the earlier approved monthly Summary landing view. Their presence was reproduced against the preserved source snapshot. They were left separate from this change.

Existing frontend failures are “reported discrepancy blocks preparation and preserves the AO recheck path” and “Borrower Request Records use the same short status set and filter by the displayed status.” The current run reproduced the same baseline result.

## Remaining rollout and visual checks

The user will configure Academic Periods in Configuration. Numbering activation still requires a separate database backup, additive schema installation and reviewed mapping; see [activation instructions](CONTROL-NUMBER-REPORTS-ACTIVATION.md). The initial PHP database inspection found zero requests and no configured academic calendar. No historical outputs existed for those requests at that inspection; recheck if records are created before activation.

Actual desktop Reports and More filters were reviewed in Chrome using the current source with a separate PHP test fixture. This caught and corrected a missing All Transactions selector choice and the compact toolbar's inherited layout direction. The popup opened in place, preserved the table and closed with Escape. A screenshot is saved at `C:\Users\Admin\.codex\visualizations\2026\11\12\01a1cebd-f712-7770-8bbf-c17815ee0c2c\control-number-reports-20261114\actual-more-filters-desktop.jpg`. Its demo records belong to the test fixture, not the port 8001 database.

Phone/dark-mode screenshots and final visual sign-off remain pending. The in-app Browser initially failed because its required network policy was missing. Computer Use later stopped the phone-check attempt because it could not confidently determine the browser URL. Browser control was not continued after that stop. Generated concepts, DOM checks and the rendered PDF are not substitutes for these remaining visual checks.

Original signed/uploaded documents, signature consent, permission enforcement, custody/return identifiers, stock operations, billing rules and document masters were not revised. The source snapshot preserved 853 files from the existing working copy before implementation.
