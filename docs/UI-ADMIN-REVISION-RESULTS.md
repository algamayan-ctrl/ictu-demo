# UI Admin revision results

Implemented the approved UI revision plan in source. Existing working-copy changes were retained. The application has not been deployed and the live database has not been migrated.

## Delivered behavior

- Shared compact spacing, dark-mode component colors, mobile record cards, preserved original corner radii, and separate role-specific sidebar sections.
- Original calendar grid, toolbar, Month/List controls and events retained; closed days show their reasons. Borrowing date popups disable closed endpoints, provide hover information and separate accessible information controls, and permit ranges spanning closures.
- Fixed-highlight Hour/Minute/AM-PM operational picker with keyboard entry, minute precision and Apply/Cancel. Existing schedule fields, validation and explicit Save action retained.
- Borrower Event label, reorganized existing Account Settings, and consistently labelled Premises filters. Existing profile, signature, appearance, contact and account-linking functionality retained.
- Read-only inventory summary dialogs, Action Officer Item Description tab, and six-column Stock Card for staff. Admin movement history remains in expandable Movement details. Existing Stock Card print permissions remain unchanged.
- Fullscreen PDF/image viewing for already-authorized viewers, with original preview URLs, fullscreen fallback, Escape and focus handling. Shared duplicate fullscreen code removed.
- Default monthly Summary Report with nine agreed columns, latest submitted version, recorded signer/affiliation/item snapshots, actual elapsed release time and plain MET/UNMET remarks. Missing submission dates are disclosed rather than assigned a guessed month.
- Selected report choices retired from the selector while legacy handlers and links remain. Ledger Accountability groups Incident, Penalty, Billing and Payment; All retains all event types.
- Future request IDs use an atomic monthly counter. Historical IDs, request versions, signatures and operational histories remain unchanged.
- Analytics custom-period links now retain both dates; existing metric/detail reconciliation remains passing.

## Validation

- Original source and original tests: 144/144 targeted PHP cases passed.
- Revised targeted PHP suite: 201/201 cases passed. Additional ledger suite: 4/4 passed, including the new combined Accountability filter and legacy-filter compatibility check.
- Frontend suite: 249 cases, 246 passed, 2 pre-existing failures, 1 skipped. No additional failures. Existing failures concern the preparation discrepancy static assertion and the Borrower Request Records static Draft-option assertion.
- Four concurrent workers generated 40 distinct numbers in the isolated SQLite test fixture.
- 24 role pages rendered without application errors or duplicate IDs. Rendered and source CSS/JavaScript syntax checks passed.
- DOM interaction checks passed for date closures, reason buttons, selection, Escape, failure/retry, time typing and Apply/Cancel without submitting, and inventory dialogs.
- The final 25-case export/sorting suite passed with 110 assertions. Multiple equipment lines, chronological date sorting, date-only Excel cells and preserved text names are covered. All 210 Blade templates compile to valid PHP.
- Summary Print/CSV/PDF/Word/Excel checks passed. A real fixture PDF was rendered and visually inspected as a clean single-page nine-column report.

## Deployment and remaining acceptance

The additive `request_number_counters` migration was applied only to the isolated test database. A separate deployment must rebuild the Docker application image and apply the migration before using the new numbering service.

Actual desktop/mobile screenshots and browser visual sign-off remain pending. The in-app browser has no network policy, the Chrome connection is unavailable, and standalone sessions could not create a page. Automatic approval review rejected launching a separate Chrome screenshot helper as “blocked by policy,” without further detail. DOM checks and PDF review do not replace browser visual verification.

Working-copy backup and raw verification evidence: `C:/Users/Admin/.codex/tmp/ictu-ui-admin-20261113`.

## Files revised in this phase

42 files differ from the source backup for this phase; 10 are new. Earlier uncommitted revisions remain in place.

- `app/Http/Controllers/InventoryController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Reports/Builders/SummaryReport.php`
- `app/Reports/Exporters/SpreadsheetExporter.php`
- `app/Reports/Exporters/WordExporter.php`
- `app/Reports/ReportCatalogue.php`
- `app/Reports/ReportRecordTable.php`
- `app/Services/AllTransactionsService.php`
- `app/Services/BorrowingRequestNumberService.php`
- `app/Services/ReportService.php`
- `app/Support/AnalyticsDetailLink.php`
- `app/Support/AnalyticsDrilldown.php`
- `database/migrations/2026_11_13_000001_create_request_number_counters_table.php`
- `docs/UI-ADMIN-REVISION-RESULTS.md`
- `docs/UI-ADMIN-REVISION-TRACKER.md`
- `public/css/ui-admin-revisions.css`
- `public/js/ui-admin-revisions.js`
- `resources/views/administration/partials/transaction-schedule.blade.php`
- `resources/views/calendar/index.blade.php`
- `resources/views/components/document-review-viewer.blade.php`
- `resources/views/inventory/index.blade.php`
- `resources/views/inventory/partials/sao-stock-card.blade.php`
- `resources/views/inventory/partials/stock-card-table.blade.php`
- `resources/views/inventory/partials/summary-dialog.blade.php`
- `resources/views/inventory/show.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/profile/show.blade.php`
- `resources/views/reports/all-transactions.blade.php`
- `resources/views/reports/detailed.blade.php`
- `resources/views/reports/document/table.blade.php`
- `resources/views/reports/partials/report-builder.blade.php`
- `resources/views/reports/partials/summary-builder.blade.php`
- `resources/views/reports/pdf.blade.php`
- `resources/views/requests/partials/create-details.blade.php`
- `tests/Feature/BorrowingRequestNumberTest.php`
- `tests/Feature/DocumentPreviewFullscreenTest.php`
- `tests/Feature/InventoryStockCardTest.php`
- `tests/Feature/ReportAccountabilityBillingTest.php`
- `tests/Feature/ReportFoundationTest.php`
- `tests/Feature/ReportPageTest.php`
- `tests/Feature/SummaryReportTest.php`
- `tests/Frontend/ui-admin-pickers.test.cjs`
