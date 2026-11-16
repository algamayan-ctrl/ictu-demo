# Reports UI correction — 14 November 2026

The report-type dropdown changed its displayed value while the builder and results remained on Summary. The compact toolbar dispatched its refresh/type-change events on `.reporting-workspace`, but the existing updater listens on the child `.reports-detail-page`. The events never reached that updater. This also prevented Apply and More filters from refreshing the preview.

The toolbar now dispatches bubbling events from its form so the existing updater receives them. Report selection rebuilds the report-specific controls and results together. An aborted older response cannot replace a newer selected report.

| Report | Existing controls retained |
|---|---|
| Summary | Month, Year; Division, Office/College/Unit and Borrower |
| All Transactions | Reporting Period, Status; Division, Office/College/Unit and Borrower |
| Inventory Status | Reporting Period, Category, Availability Status |
| Equipment Utilization | Reporting Period, Equipment; Division and Office/College/Unit |
| Accountability Cases | Reporting Period, Finding, Case Status; Division, Office/College/Unit and Borrower |
| Billing Settlement | Reporting Period, Billing Status, Payment Status; Borrower |

The Reports heading now reserves a separate right-hand column for **Print** and **Generate**, using the original heading font size. Long titles wrap inside the heading column without moving those actions below the heading on desktop. The Generate button still opens the existing format/presentation dialog and uses the existing output routes. Its format actions are Generate PDF, Generate Word, Generate Excel and Generate CSV. Phone layouts may wrap the actions below the heading when space requires it.

This correction changes UI event routing, action labels, heading alignment and their regression checks. It does not change numbering, report datasets/calculations, permissions, signatures, inventory movement, approvals, custody, returns or billing workflows.

Verification:

- The integrated DOM check uses actual rendered Reports pages, the shipped toolbar script and the existing preview updater. The old script reproduces the failed switching. The correction passes all 10 checks: six report switches with matching filters/title/print route, Apply, More filters Apply/Cancel and an out-of-order response.
- Two server-rendering checks passed, 41 assertions, including report-specific controls and supported generation formats.
- The frontend suite passed 249 tests, with the same two previously documented failures and one skipped optional browser test.
- All 215 Blade templates compiled; the revised PHP and JavaScript passed syntax checks.
- Native Chrome verification used the current code and an isolated PHP fixture: switching from Summary to Inventory displayed Category/Availability Status and the Inventory title, then switching to All Transactions displayed its Status filter, title and print route. Returning to Summary restored Month/Year and its monthly heading. Print/Generate remained on the right. The existing port 8001 session had expired, so no live login or record edits were performed.

Evidence: `C:\Users\Admin\.codex\tmp\ictu-report-ui-correction-20261114`. The seven files initially in scope were preserved there before edits. Additional integration coverage is in `tests/Frontend/report-builder-controls.test.cjs`.

Actual Summary, Inventory and All Transactions screenshots are saved under `C:\Users\Admin\.codex\visualizations\2026\11\12\01a1cebd-f712-7770-8bbf-c17815ee0c2c\reports-ui-correction-20261114`. These contain isolated fixture records. The final Summary screenshot verifies the original heading size with Print/Generate at the far right.

To repeat the integrated check, render `ControlNumberReportsRevisionTest::test_compact_toolbar_keeps_advanced_filters_in_a_single_popup_and_scopes_period_choices` with `CONTROL_REPORT_HTML_DIR` set to an existing output directory, then run `node --test tests/Frontend/report-builder-controls.test.cjs` with that same directory and `REPORTS_TEST_JSDOM` pointing to an installed jsdom package. No application dependency was added.
