# S1-QA-OVERALL-02 — overall failure reconciliation

Status: VERIFIED COMPLETE. Increment gate: PASS. Overall deployment gate: NOT READY.

## Objective and source requirements

Reconcile all 24 named failures in the 14 October overall audit against confirmed user policies and actual current implementation, then run the affected cases and complete suites on SQLite and MariaDB 11.4. Preserve the historical red evidence. The source is the approved workflow/manuscript plus the confirmed Admin/SAO authority, E-sign, Admin Transaction Schedule, descriptive-only scope and face-to-face accountability flow. This increment applies XP defect mapping, small corrections and regression verification; it does not certify institutional approval or change policies.

## Actors, story and states

Borrower submits valid dated/signed requests and sees accurate obligations; SAO verifies off-campus submissions and performs physical release/return; Admin/Head decides approvals and final accountability; ICTU receives only existing technical role grants. Story: as the development partner, I need a trustworthy full test gate that distinguishes invalid fixtures from actual application defects. Preconditions: disposable databases, current source snapshot, valid roles/calendar/evidence. Postconditions: all 24 identified cases verified, mandatory regression checks passing, unchanged live records and private configuration. State guards remain off-campus submission → SAO verification → Head decision, and approval → reservation/documents → SAO release. Legacy compliance verification → Head final review remains separate from current V3 F2F confirmation → final resolution.

## Inputs, outputs and database impact

Inputs: the two complete 1437-case audit runs, exact failure methods, approved decisions, current test fixtures and source. Outputs: traceable mapping, reviewed patches, red/green logs and a gate decision. No migrations or schema changes are planned. Fixtures and test records are confined to isolated SQLite memory and an owned localhost MariaDB container. The established preview/database, private environment and read-only references are preserved.

## Failure and acceptance coverage

Cover normal and invalid calendar dates, valid and unreadable signatures, actual role grants versus forged classification, Admin redirects and SAO custody access, full canonical badge labels, empty/action-required obligation states, missing/declined direct F2F confirmation, legacy final review, required attachment, unauthorized final resolution and stock/audit regressions. Acceptance requires every mapped case green and full suites on both engines with zero failures/errors. Browser/provider delivery, Cloud storage/shared services and formal ICTU/UAT sign-offs remain separate unmet gates.

## Mapping of every inherited failure

| ID | Exact test method | Classification | Correction and preserved requirement |
|---|---|---|---|
| QA02-01 | Tests\Feature\AccountSettingsRoleTest::test_borrower_account_settings_only_list_allowed_colleges_and_use_default_theme_label | Assertion scope | Assert Default in the appearance select only. A whole-page ban on System also matches unrelated system labels/scripts/comments. Preserve read-only identity/unit assertions. |
| QA02-02 | Tests\Feature\BorrowerObligationGroupingTest::test_my_obligations_shows_the_four_grouped_summary_cards | Missing scenario fixture | Four cards require an obligation needing borrower action; create an unpaid billing. Add independent zero-obligation coverage for the existing compact three-card state and empty message. No synthetic institutional fees are introduced. |
| QA02-03 | Tests\Feature\CompleteWorkflowTest::test_release_is_blocked_after_pickup_window_expiry_until_rescheduled | Calendar fixture | The normal reschedule fixture derives a Saturday needed-from date from Friday pickup. Use a deterministic open needed-from day with a remaining eligible pickup window; retain terminal-deadline rejection coverage. |
| QA02-04 | Tests\Feature\CreateRequestOrganizationalUnitTest::test_access_classification_wording_is_unaffected_by_the_division_rename | Role fixture | Assign the ICTU role through the existing role assignment service; an access-classification label alone is not a role grant. Preserve real role-pivot authorization and employee unit restrictions. |
| QA02-05 | Tests\Feature\DashboardAccountabilityReconciliationTest::test_accountability_filter_and_decision_copy_is_clear_and_current | Retired UI expectation | Assert the current accountability-confirmation and final F2F instructions; preserve the still-valid legacy AO disposition limits and search/filter wording. Do not restore the retired official-disposition action. |
| QA02-06 | Tests\Feature\FoundationTest::test_landing_page_is_available | Retired landing expectation | The approved shared sign-in page replaced the old public workflow narrative. Assert real sign-in, Google, registration and OTP links; preserve absence of retired GSU/VPAF approval labels. |
| QA02-07 | Tests\Feature\OffCampusGatePassWorkflowTest::test_off_campus_non_student_activity_requires_only_request_letter_and_routes_to_officer_first | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-08 | Tests\Feature\OffCampusGatePassWorkflowTest::test_off_campus_student_activity_requires_ptc_but_non_student_activity_does_not | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-09 | Tests\Feature\OffCampusGatePassWorkflowTest::test_request_supporting_document_uses_the_ictu_configured_upload_limit | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-10 | Tests\Feature\OffCampusGatePassWorkflowTest::test_action_officer_can_return_for_correction_and_corrected_version_restarts_verification | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-11 | Tests\Feature\OffCampusGatePassWorkflowTest::test_action_officer_verification_routes_to_head_without_approving_reserving_or_generating | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-12 | Tests\Feature\OffCampusGatePassWorkflowTest::test_head_rejection_creates_no_inventory_or_operational_documents | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-13 | Tests\Feature\OffCampusGatePassWorkflowTest::test_head_return_for_revision_creates_no_inventory_or_operational_documents | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-14 | Tests\Feature\OffCampusGatePassWorkflowTest::test_head_approval_generates_one_gate_pass_and_borrower_slip_then_physical_release_reuses_them | Calendar fixture | Select an open needed-from day AND an open preceding pickup day; resolve the return through the calendar. Preserve closed-day rejection, PTC, SAO verification, Head decision and zero premature allocation. |
| QA02-15 | Tests\Feature\RoleWorkspaceSmokeTest::test_spmu_workspace_pages_render | Retired permission expectation | Head/Admin has User Accounts and Request Records; custody redirects to Request Records. SAO has separate Release and Return and no User Accounts. Verify exact sidebar labels, not whole-page CSS/comments. |
| QA02-16 | Tests\Feature\RoleWorkspaceSmokeTest::test_each_active_workspace_has_a_focused_role_specific_menu | Retired permission expectation | Head/Admin has User Accounts and Request Records; custody redirects to Request Records. SAO has separate Release and Return and no User Accounts. Verify exact sidebar labels, not whole-page CSS/comments. |
| QA02-17 | Tests\Feature\RsldppWorkflowTest::test_no_count_as_offense_decision_remains_for_a_fresh_open_incident | Evidence fixture | Replace PNG header plus arbitrary text with the existing genuine PNG fixture. Retain hash/readability validation and all decision, sanction, state and notification assertions. |
| QA02-18 | Tests\Feature\RsldppWorkflowTest::test_clear_decision_creates_no_offense_and_resolves_immediately | Evidence fixture | Replace PNG header plus arbitrary text with the existing genuine PNG fixture. Retain hash/readability validation and all decision, sanction, state and notification assertions. |
| QA02-19 | Tests\Feature\RsldppWorkflowTest::test_confirm_decision_auto_records_offense_and_sanction_when_eligible | Evidence fixture | Replace PNG header plus arbitrary text with the existing genuine PNG fixture. Retain hash/readability validation and all decision, sanction, state and notification assertions. |
| QA02-20 | Tests\Feature\RsldppWorkflowTest::test_confirmed_accountability_notifies_f2f_processing_without_generating_rslddp | Evidence fixture | Replace PNG header plus arbitrary text with the existing genuine PNG fixture. Retain hash/readability validation and all decision, sanction, state and notification assertions. |
| QA02-21 | Tests\Feature\RsldppWorkflowTest::test_confirmed_accountability_resolves_directly_after_confirmed_f2f_processing | Evidence fixture | Replace PNG header plus arbitrary text with the existing genuine PNG fixture. Retain hash/readability validation and all decision, sanction, state and notification assertions. |
| QA02-22 | Tests\Feature\RsldppWorkflowTest::test_legacy_compliance_case_without_rslddp_still_requires_head_final_resolution | Confirmed code defect | accepted is implicit in the installed Laravel validator and nullable does not make it optional. Use sometimes+accepted only for the established legacy final-review path; current direct F2F retains required+accepted. Keep Head-only authority and required final attachment. |
| QA02-23 | Tests\Feature\RsldppWorkflowTest::test_status_badge_shows_canonical_labels_and_tones_for_rslddp_statuses | Assertion scope | Compare the complete decoded badge text and tone; a valid canonical label contains the auto-formatted substring. |
| QA02-24 | Tests\Feature\UniversalDocumentActionUiTest::unavailable_documents_use_not_available_and_non_applicable_documents_are_hidden | Retired document UI expectation | The active request view lists existing FINAL current-version documents after final approval, with an empty packet message. Assert its current availability guard and the physical release/return applicability guards; do not restore obsolete placeholder markup. |

## Verification evidence

Workspace evidence folder: sprint1/qa-overall02. Initial source manifest: 646 files, private configuration hashes: 2 files. The full historical audit remains in sprint1/overall-qa01. Fresh selected red runs and final verification will be recorded here after execution. Tests are corrected only where the fixture/assertion conflicts with confirmed current behavior. No test is skipped or business guard relaxed merely to obtain a pass.

## Diagnostic runs (not acceptance passes)

Fresh selected red: all 24/24 cases failed with 83 assertions on each engine, matching the inherited failure list. The first correction run passed 16/25 with 9 failures: online REQUEST availability intentionally permits all dates, so needed-from fixture validation also needs physical isOpenDate; the new empty-state case initially expected obsolete copy. Corrected targeted run:25/25,326 assertions on each engine.

The first broad runs were interrupted after SQLite exposed a stock-overlap fixture regression (688 passed,1 failed before interruption; MariaDB389 passed,0 failures before interruption). The second request used independent offsets instead of the first approved calendar-resolved period. Its dates now match the first exactly and the test additionally requires Returned for Revision and a reservation-conflict audit. The first added audit assertion mistakenly named audit_logs; the actual existing table is audit_events. This test-only typo was corrected. These partial/error runs are preserved and are not reported as completed full-suite results. The final full runs began after the focused correction passed; their completed results are recorded below.

## Final verified results

| Check | Total | Passed | Failed/errors | Assertions |
|---|---:|---:|---:|---:|
| Full SQLite Unit/Feature | 1438 | 1438 | 0/0 | 11994 |
| Full MariaDB 11.4.12 Unit/Feature | 1438 | 1438 | 0/0 | 11994 |
| Selected reconciliation, each engine | 25 | 25 | 0/0 | 326 |
| Overlap/pickup correction, each engine | 2 | 2 | 0/0 | 53 |
| Frontend | 191 | 190 | 0 | — |
| Affected PHP syntax | 11 | 11 | 0 | — |

Both completed full runs have zero skips, warnings, incomplete or risky cases and process exit0. Full suite totals include the added empty-obligation regression case (1437→1438); selected runs overlap the full suites and are not added to the totals. SQLite took 627715ms; MariaDB took 1764071ms. Frontend retains one optional browser date-range fixture skip; this is not a verified visual/provider test.

### What changed and what was added

One production rule changed in AccountabilityController: established legacy final resolution uses sometimes+accepted instead of nullable+accepted for the optional F2F field. Current direct F2F remains required+accepted, Head-only final resolution and required final attachment remain unchanged. Ten existing backend test classes were reconciled; one frontend assertion was updated to describe the existing conditional action card. Added one empty-obligation regression case, exact canonical badge/sidebar checks, and legacy unauthorized/declined confirmation plus exact stock-conflict state/audit assertions. Calendar and PNG fixtures now meet actual validation. No new role, policy, migration, allocation rule, UI layout, fee or provider change was introduced.

### Preservation and practical limits

All24 mapped rows are VERIFIED COMPLETE with exact evidence links in requirement-fixture-map.json. Changes are confined to twelve mapped source/test files;634 other snapshotted source files are unchanged. The73 preview tables/schema match the prior preserved snapshot, integrity is ok and foreign-key errors0. Two private environment files and eight references are preserved. The test clone matches the final application/test source. Only an owned temporary database was used for MariaDB tests. Cleanup and preview HTTP checks are recorded separately.

These automated results close this QA increment, not the institutional release gate. Actual Google callback/account linking and OTP/password-reset inbox receipt still need live evidence; actual hosting choice (ICTU premises versus Cloud), production private persistent storage/shared sessions/cache/queue/scheduler, security/recovery and expert/UAT/panel/handover evidence remain open. The original running Docker image is not a freshly rebuilt release of this patch. Earlier Docker/build/audit evidence is historical and was not silently relabelled as a new run. Real operational KPIs remain after approved deployment per the confirmed manuscript sequence.

Next authorized step: resume S1-AUTH-LIVE-01 at exact provider callback setup and the owner-authorized test inbox, then verify real Google onboarding/linking, manual OTP→SAO and single-use password reset. No deployment is authorized/certified by this result.
