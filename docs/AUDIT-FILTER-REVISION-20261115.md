# Audit Trail Actor search and Category grouping

Approved scope: searchable Actor control and nine visible category choices, including All operational events. Existing classifications, records, permissions, table columns, ordering and row actions remain intact.

## Progress

- [x] Review and visualize Actor search; obtain approval for Actor search and category grouping.
- [x] Preserve the current working copy: 873 files, Git status and SHA-256 hashes.
- [x] Record the isolated test baseline: 23 / 24 audit tests passed, 398 assertions.
- [x] Add grouped selector categories while preserving legacy filters and stored event classifications.
- [x] Add bounded name matching, selection, clear and keyboard controls to Actor.
- [x] Complete targeted server checks: 7 new tests / 80 assertions passed.
- [x] Complete interaction checks: 11 passed, covering keyboard, clear, System, duplicate names, failure state and unchanged row disclosures.
- [x] Complete 24 actual-browser scenarios at 320–1920px, light/dark themes and expanded/collapsed sidebar states; no overflow or selection failures.
- [x] Review final regression: 30 / 31 passed, 478 assertions; the same pre-existing institutional email-validation failure remains.
- [x] Verify final scope: 4 application files, 2 test files and this tracker only; no removed files or new trailing whitespace.
- [ ] Capture final standalone screenshots. Computer Use stopped this operation because it could not verify the current Windows browser URL with sufficient confidence to enforce policy.

## Category compatibility

- Borrowing & Approvals combines BORROWING and APPROVAL.
- Administration & Settings combines ACCOUNT, OPERATIONAL_SETTINGS and SETTINGS.
- Release / Return, Laundry / Gate Pass, Accountability, Inventory and Corrections / Reopenings retain their original scope.
- Other Activities is the selector, record Type and detail Category label for OTHER_OPERATIONAL.
- All operational events retains every category already authorized for the Admin.
- A legacy link keeps its exact old category scope and shows one selected historical-filter option with the shared group label and original subcategory; the default selector contains nine options.
- Table badges and detail categories use the same wording as the selector, including Borrowing & Approvals and Administration & Settings. Raw classifications, badge styling, record actions and saved audit rows remain unchanged.

## Category label consistency follow-up

The selector previously used the new grouped names while record Types and detail Categories still used the old labels. Both record displays now derive their wording from the selector labels and existing group membership. This applies to historical and newly recorded events without changing stored classifications or filter results.

- [x] Preserve the three affected files and hash all 877 working-copy files before edits.
- [x] Verify the immediate baseline: 7 tests passed, 80 assertions.
- [x] Share the category wording across the filter, Type and View Details.
- [x] Preserve precise historical filter scopes and identify their original subcategories.
- [x] Verify all eight specific choices against their eleven underlying categories, including rendered badges and details, with unchanged saved audit events.
- [x] Complete audit regression: 31 / 32 tests passed, 572 assertions. The sole failure is the same pre-existing institutional email-validation issue recorded below.
- [x] Confirm the follow-up changes only the controller label mapping, its existing feature test and this tracker; no application layout, JavaScript, CSS, database or permission changes.

Follow-up evidence: `C:/Users/Admin/.codex/tmp/ictu-audit-labels-20261115`.

## Actor behavior

Names come from the existing authorized event audience. Duplicate names retain distinct actor IDs and show their registered email to distinguish entries. Unrelated account profiles are excluded. Typing matches choices locally; selecting a result or explicitly clearing the Actor control applies the filter through the existing form. No new API or route is introduced.

## Baseline issue

Before application edits, `AuditRecoveryTest::test_other_roles_and_the_other_audit_audience_cannot_revert` failed because its account update was rejected by the existing institutional email-domain validation: “Use an approved CSPC institutional email domain (cspc.edu.ph).” This failure is recorded separately from this revision. Account-edit validation and audit-recovery mutation rules are outside the approved changes.

## Evidence

Backup, baseline logs and verification artifacts: `C:/Users/Admin/.codex/tmp/ictu-audit-filters-20261115`.

Final evidence: `checks-controls.log`, `checks-js.log`, `checks-php-final.log`, `browser-results.json` and `scope-results.json`. Browser scenarios use application-rendered views with isolated synthetic records; they do not claim verification of live production data.

Tests use disposable SQLite databases guarded by Tests/TestCase.php. No live database migration, seed, server restart or Docker rebuild is part of this revision.
