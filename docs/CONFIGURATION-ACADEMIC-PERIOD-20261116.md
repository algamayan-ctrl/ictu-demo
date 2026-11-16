# Configuration navigation and automatic Academic Period — 16 November 2026

Workspace: `C:/Users/Admin/ictu-demo`. Native PHP reference: `http://127.0.0.1:8001`.

## Authorized scope

The user approved automatic Academic Period dates, then approved the five source-based before/after Configuration feature previews. The chosen navigation uses one Configuration dropdown with five flat direct links, following the existing Analytics and Accountability behavior. The earlier proposal for internal Schedule & Activity / Policies groups was superseded by this smaller change.

## Implemented behavior

- Configuration expands/collapses through the existing sidebar JavaScript and styling. Five links open Transaction Schedule, Special Dates & Closures, Academic Period, Late Return Policy and Sanction Rules. The active feature opens its group and keeps the existing selected-link highlight, Escape/focus behavior, mobile menu behavior and desktop collapsed-sidebar handling. No sidebar CSS or JavaScript was changed.
- Existing feature URLs remain valid, including Late Return Policy under administration settings. The Configuration root and unrecognized section values open Transaction Schedule. The tile hub is removed. User Accounts, Analytics, Accountability and other destinations retain their controls and grouping.
- The Academic Period form retains Academic Year, Semester / Term, Start Date and End Date in their original order and control types. Both date controls remain native browser inputs with their original icons. Search, status, sorting and pagination remain. Save Academic Period remains the action; Activate buttons and their table Action column are removed.
- Status is derived from the configured dates in Asia/Manila: Upcoming before Start Date; Current from Start Date through End Date inclusive; Completed afterward. The shared resolver is evaluated when the period is used, so a midnight scheduled task or continuously running server is unnecessary. Stored status flags are retained as historical/save snapshots, not the source of current-period truth. GET requests do not rewrite saved rows.
- Future semesters and Summer / Midyear can be configured in advance. Semester breaks show No current semester. A configured, unambiguous academic year continues to cover its interior semester break for Current Academic Year reporting.
- Saves reject malformed/reversed dates, nonconsecutive academic-year labels, duplicate terms on update, inclusive semester overlaps and intersecting academic-year spans. Saving an already configured year/term retains its existing ID. Old activation-only requests are authorized normally and return an explanation without overriding dates or changing other rows.
- Configuration, PolicyService event-date lookups, Reports, Analytics and Analytics Records use the same date-based current-period resolver. Explicit historical period-ID links remain supported. Existing missing-period calendar-month fallback and the Analytics notice remain. Report definitions, current-stock meanings and export formats are unchanged.

## Protected areas

The original working copy was preserved before edits. Only the configuration navigation, academic-period presentation/save/resolution and directly dependent current-period lookups were revised. Transaction schedule and closure forms, fees, sanction rules, approval/release/return processing, signature consent, manual report signatories, report builders/columns, inventory calculations, other sidebar sections and role restrictions were not revised. Existing recorded academic-period links, sanction decisions/end dates and Control Nos. were not reassigned.

No live database migration, seeding, renumbering or academic-year numbering activation was performed. The pending numbering rollout remains separate. No real academic dates were invented or saved; the SPMU Admin still supplies the official academic year and semester dates.

## Verification tracker

- [x] Preserve the affected originals, working-copy hashes and test baseline.
- [x] Generate and review the five per-feature before/after previews; record user approval.
- [x] Reuse the existing menu behavior and retain original routes/authorization.
- [x] Implement shared date resolution, inclusive boundaries, overlap validation and safe legacy activation handling.
- [x] Verify timezone rollover, stale flags/offline catch-up, future terms, Summer / Midyear, semester breaks, year context, historical links and unchanged numbering activation.
- [x] Verify original Academic Period inputs and record controls.
- [x] Run 30 DOM interaction scenarios against fresh rendered feature pages and the shipped menu handler: five destinations × desktop/phone behavior × light/dark/Default preference. These check interactions, not visual rendering in a real browser.
- [x] Run 140 focused backend regression tests with 842 assertions; all passed. Coverage includes the new automatic-period tests, operational schedule, report exports/manual signatories, signatures and Admin dashboard reconciliation.
- [x] Complete the related Analytics recheck and source-scope review. All 15 Analytics comparison tests passed, 139 assertions. The final merged evidence covers 267 unique selected backend tests across scoped runs: 266 passed, one unchanged baseline failure, 1,831 assertions. No new unexplained failures remain in these checks.
- [ ] Capture actual desktop/mobile screenshots and obtain final visual acceptance. The browser connection still reports a missing network policy; the generated previews are illustrative, not authenticated application screenshots.

Baseline: 127 related backend tests, 126 passed, one existing failure in `RsldppWorkflowTest::test_custody_return_filter_dropdown_only_includes_physical_return_statuses`. The first related run also exposed an obsolete Analytics fixture that manually activated a future semester. That fixture now uses its explicit period-ID link, preserving the original unfinished-comparison assertion. It no longer contradicts the approved automatic-current rule. A focused menu-test assertion was corrected to inspect rendered tile elements rather than matching unused CSS class text.

Evidence: `C:/Users/Admin/.codex/tmp/ictu-auto-academic-period-20261116`. Original source copies, baseline/results XML, fresh HTML fixtures, working-copy manifest and DOM results are preserved there.

Native health check: `http://127.0.0.1:8001/login` returned HTTP 200 with the login form. The non-academic PolicyController methods, from `updateSanctionRule` onward, were compared with the preserved original and are identical. No server restart was needed. These checks cover the approved change and relevant regressions; they do not assert that every function in the entire system has been exhaustively tested.

Application source scope: `resources/views/layouts/app.blade.php`, `resources/views/administration/policies.blade.php`, its existing academic-period filter partial, new `app/Services/AcademicPeriodResolver.php`, the Academic Period methods in `PolicyController`, `PolicyService::activePeriodFor` and its configuration-error copy, the current-period lookups in ReportController/AnalyticsController/AnalyticsRecordsController, and ReportingPeriodService. Verification adds AutomaticAcademicPeriodTest and updates only the obsolete future-period fixture in AnalyticsPeriodComparisonTest. No model, public CSS/JavaScript, route, migration or document-output file was changed.

## Approved feature previews

Follow-up layout correction: the Academic Period page-size control now displays `10 entries`, `25 entries`, `50 entries` or `100 entries` inside the existing select border. An opt-in property on the shared record-page-size component removes the separate suffix only on this page; its default rendering for other tables is unchanged. Two existing rendering checks passed (38 assertions), and the fresh HTML confirms unchanged numeric submission values and identical default page-size markup on Special Dates. Evidence: `C:/Users/Admin/.codex/tmp/ictu-academic-page-size-20261116`.

These images compare navigation access; existing page content is authoritative where a mockup simplifies it. The Academic Period preview shows the separately approved automatic page on both sides so the navigation comparison remains clear. Example values are not saved settings.

- Transaction Schedule: `C:/Users/Admin/.codex/generated_images/01a1cebd-f712-7770-8bbf-c17815ee0c2c/exec-24a21591-148c-4c3b-bbd4-8a463d395b43.png`
- Special Dates & Closures: `C:/Users/Admin/.codex/generated_images/01a1cebd-f712-7770-8bbf-c17815ee0c2c/exec-ebf94462-def5-468e-be91-3c8ea1f48eb8.png`
- Academic Period: `C:/Users/Admin/.codex/generated_images/01a1cebd-f712-7770-8bbf-c17815ee0c2c/exec-f783329a-7abd-47b0-9b99-ecfb4c0ea3a9.png`
- Late Return Policy: `C:/Users/Admin/.codex/generated_images/01a1cebd-f712-7770-8bbf-c17815ee0c2c/exec-edb6ae72-d567-4d6e-bdfb-ae9ba60890b1.png`
- Sanction Rules, corrected text-input preview: `C:/Users/Admin/.codex/generated_images/01a1cebd-f712-7770-8bbf-c17815ee0c2c/exec-d3b38037-e630-4d82-93f6-754c66f23dbb.png`
