# Compact Reports builder and Summary date range

Approved: compact desktop row with From / To; wrap only when available width requires it. This replaces the rejected permanent two-row concept.

## Progress

- [x] Preserve the current working copy (871 files and baseline hashes).
- [x] Record the baseline: 18 PHP tests / 171 assertions; 10 frontend interaction checks passed.
- [x] Make builder fields flexible and distribute available space; retain compact heights and existing corner styles.
- [x] Allow wrapping at any actual card width and remove the rigid custom-date pair minimum width.
- [x] Add Monthly / Custom Range to Summary. Only the active date controls are enabled and submitted.
- [x] Preserve selected historical Month / Year in generated links and filter clearing.
- [x] Support required, inclusive, ordered custom dates using existing report validation.
- [x] Generate accurate period headings through the shared dataset; retain the approved monthly heading for full-month ranges.
- [x] Keep latest submission filtering, first review / physical release facts, report columns and authorization.
- [x] Verify screen, print, CSV, PDF, Word and spreadsheet generation: 59 PHP tests / 443 assertions passed.
- [x] Verify all six report builders and date-mode interactions: 11 checks passed.
- [x] Complete real-browser checks: 256 layouts across 320–1920px, light/dark themes and expanded/collapsed sidebar states; no overflow or overlaps.
- [x] Complete 24 final custom-range layout checks after enforcing equal 38px input/button heights; all passed.
- [x] Visually review and capture the implemented Summary in an application-rendered test view.
- [x] Complete the live-session visual check on port 8001 after the user signs in again.

## Scope

Application changes are limited to:

- `public/css/reports-compact.css`: compact flexible sizing and safe wrapping within the builder card.
- `public/js/reports-compact.js`: active Summary date controls and custom-date synchronization.
- `resources/views/reports/partials/compact-builder.blade.php`: Summary period selector and date controls.
- `app/Http/Controllers/ReportController.php`: resolve Summary monthly and custom scopes.
- `app/Reports/ReportFilters.php`: preserve Summary Month / Year in query links.
- `app/Reports/Builders/SummaryReport.php`: range-aware title from the existing dataset.
- `app/Reports/ReportCatalogue.php`: Summary description and empty message support both date modes.

Verification changes: `tests/Feature/SummaryCustomRangeTest.php`, `tests/Frontend/report-builder-controls.test.cjs`, and this tracker.

Print / Generate placement and labels, report-specific search and filters, permissions, numbering, signatures, records and operational workflows are preserved. No database migration or deployment is included.

## Verification notes

All PHP checks use isolated SQLite `:memory:` with the existing disposable-database guard. CSV retains its existing rows-only format; other document formats retain their existing structure and use the selected report title and dates.

Evidence and working-copy backup: `C:/Users/Admin/.codex/tmp/ictu-compact-range-20261115`.

The browser session on port 8001 initially expired. After the user signed in again, the live check passed using SPMU Head Demo. A separate local verification server had also served views rendered by the actual application tests for responsive layout checks, without accessing the running application's database. The generated concepts are not used as verification screenshots.

Browser evidence: `layout-results-wide.json` (256 responsive checks), `layout-results.json` (24 final height/layout checks), and `summary-rendered-desktop.jpg`. The screenshot contains synthetic test records and the rendered test user's name, not production records.

## Live verification on port 8001

- All Transactions applies the custom dates and carries the same dates into its Print URL.
- Controls stay within the card with the sidebar expanded. Apply / Clear wrap when available width is insufficient; all controls fit one row when the sidebar is collapsed and more width is available.
- Switching to Summary retains the selected custom dates.
- Summary successfully applies 01 November–05 December 2026 and displays the correct period heading and Print URL.
- Switching to Monthly restores Month / Year, omits inactive custom-date parameters, and displays the approved monthly heading.
- Print / Generate labels and placement remain intact.
- No records matched these live date ranges; populated rows and calculations were verified in the isolated test database.
- The browser is left on Summary Monthly, November 2026, with the sidebar expanded. No application code was changed during this live check.

Actual live screenshots and recorded checks: `C:/Users/Admin/.codex/tmp/ictu-compact-range-20261115/live-8001/`.
