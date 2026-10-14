# Overall system test — 14 October 2026

Task: S1-QA-OVERALL-01. Status: FOR REVIEW. Test execution is finished; the full pre-deployment/release gate is NOT READY. This is an overall technical regression/readiness audit, before another workflow increment. Application behavior is frozen; findings are recorded rather than silently changing requirements or tests.

## Objective and authorized scope

Verify the current Laravel/PHP, Bootstrap/JavaScript, MariaDB 11.4 and Docker system against the available manuscript/workflow and confirmed user decisions. Determine whether the pre-deployment gate has enough evidence. Automated tests are not ISO questionnaires, institutional approval, or real operational KPI measurements.

Actors/permissions: preserve Borrower, SAO, Admin and technical ICTU authorization. Google/OTP prove identity without approval. Preconditions: isolated test copy without a live environment/cache, owned localhost-only MariaDB, synthetic fixtures, real providers disabled in automated suites, and protected original databases/reference files. Postconditions: original code/business data/evidence remain intact and every reported result has a receipt.

Inputs: frozen application source/locked dependencies, all Unit/Feature and Frontend tests, runtime/container state, available references and deployment configuration. Outputs: exact executed/pass/fail/error/skip counts, named failures, coverage limits, conflicts and a deployment decision. No schema or business-state changes are planned in the original system. Exceptional scenarios include unavailable providers, test/runtime failures, duplicate/concurrent inventory updates, invalid evidence, unsafe uploads, unauthorized workflow access and incomplete infrastructure.

Acceptance/dependencies: complete full suites on both engines, build/platform/dependency checks, reproducible recovery rehearsal, representative role/UI smoke checks, and a truthful deployment register. Missing original ICTU/hosting documents, real OAuth/mail receipt, provider infrastructure, quality evaluation and institutional sign-off remain explicit gates. A local passing suite does not certify a hosted environment.

## Test and evidence register

| Area | Status | Evidence/next action |
| --- | --- | --- |
| Full Unit/Feature suite, SQLite | FOR REVIEW | 1437 executed: 1413 passed, 24 failed, zero errors/skips; failures need reconciliation |
| Full Unit/Feature suite, MariaDB 11.4 | FOR REVIEW | Same 1437 executed/1413 passed/24 failed; same 24 methods; zero errors/skips |
| Frontend suite and asset build | FOR REVIEW | 190 passed, zero failures, one optional browser skip; production assets build passed |
| Dependency/platform/security checks | FOR REVIEW | Composer validation/platform and audits pass; zero reported production dependency advisories; not a penetration-test certificate |
| Runtime/provider/storage/scheduler/queue | FOR REVIEW | Current no-dev Docker image boots; scheduler ran once, empty worker booted, SMTP authenticated; real Cloud/OAuth/inbox/persistent-object checks remain open |
| Recovery rehearsal | VERIFIED COMPLETE | Owned 72-table native MariaDB restore and private SHA verified; production/Cloud recovery remains separate |
| Role/UI smoke tests | FOR REVIEW | 41/41 real HTTP login/page/permission checks on owned Linux fixture; fresh complete browser/device UAT remains unverified |
| Exact ICTU nine deliverables and hosting approval | BLOCKED | Nine deliverables located in E:/New SPMU System/ICTU Requirements.docx; original signed letter/hosting form and approved resolution of premises versus Cloud remain unavailable |
| ISO/IEC 25010:2023 pre-deployment evaluation | BLOCKED | Specified expert/instrument evidence and sign-off unavailable |
| Real operational KPI evaluation | NOT STARTED | After approved deployment, as confirmed by the user |

Evidence directory: project workspace `sprint1/overall-qa01`. Historical scoped receipts are context only; new whole-suite results will be listed separately. No production deployment, reset, seeding, provider purchase, DNS change, code fix, commit or push is part of this audit.

## Exact fresh results

**Pre-deployment decision: NO / NOT READY.** Both complete backend runs returned exit code 1. The same 24 acceptance cases failed on both engines. These are unresolved acceptance failures; this audit does not silently classify every failure as an application defect or as inherited without a baseline replay.

| Check | Executed | Passed | Failed | Errors | Skipped | Evidence |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| Full Unit/Feature, SQLite | 1437 | 1413 | 24 | 0 | 0 | backend-full-sqlite.txt; backend-counts-sqlite.json |
| Full Unit/Feature, MariaDB 11.4.12 | 1437 | 1413 | 24 | 0 | 0 | backend-full-mariadb.txt; backend-counts-mariadb.json |
| Frontend | 191 | 190 | 0 | 0 | 1 | frontend-tests.txt |
| Actual owned Linux role HTTP checks | 41 | 41 | 0 | 0 | 0 | role-http-smoke.json |
| First-party PHP syntax, including Blade files | 572 | 572 | 0 | 0 | 0 | syntax.json |

Each backend run made **11,750 assertions**. SQLite runner duration: **12m 24.106s**; MariaDB: **31m 51.608s**. Both report zero warnings and incomplete cases. Counts across database engines repeat the same cases and are not added into a unique-case total. The frontend skip is the optional real-browser report date-range test whose REPORT_BUILDER_HTML fixture was not supplied; it is not counted as a pass. No new browser/device visual evaluation or formal UAT was completed by these HTTP checks.

Composer validation, locked production platform checks, Composer production audit and pnpm production audit all returned exit 0. Both production audits reported zero advisories; this is not proof of complete security. Current frontend build passed in 6.77s. Diff check passed.

## Failure groups and required correction review

| Group | Cases | Verified observation and next review |
| --- | ---: | --- |
| Off-campus request dates | 8 | Current calendar rejects Items Needed From on Saturday 17 October 2026. The fixture resolves an open pickup day but derives a needed date that is closed for REQUEST. Reconcile test schedules with the Admin Transaction Schedule; do not weaken institutional date validation merely to make tests pass. |
| Expired pickup / rescheduling | 1 | Continue Release has no valid window on/before the approved needed date. Map permitted cancellation/revision/rescheduling against approved dates before changing either fixture or behavior. |
| Accountability E-sign prerequisites | 5 | The fixture stores a PNG header plus text, not a decodable PNG. The current E-sign guard rejects it. Use genuine synthetic image bytes after confirming the scenario; preserve validation and evidence integrity. |
| Legacy F2F confirmation | 1 | Required f2f_processing_completed confirmation is absent. Review legacy state/permission compatibility and the mandatory manual-processing prerequisite. |
| UI, labels and permission expectations | 9 | Old text/menu, whole-page empty-state, role-access and document-display assertions differ from current behavior. Includes ICTU create expecting 200/receiving 403 and an Admin physical-custody page expecting 200/receiving a redirect. Map actor roles and the confirmed UI/approval rules; do not restore unwanted Admin Release/Return or broaden approval authority to satisfy obsolete assertions. |

The five E-sign fixture failures have a directly inspected invalid-image cause. The remaining classification above identifies observed triggers and required reconciliation; it does not certify an unperformed fix or prove all failures predate the current changes.

## All 24 failed cases

The same named cases fail on both databases. Full non-secret case lists with file/line and concise diagnostic tails are stored in failures-sqlite.json and failures-mariadb.json.

| No. | Test class | Method |
| ---: | --- | --- |
| 1 | AccountSettingsRoleTest | test_borrower_account_settings_only_list_allowed_colleges_and_use_default_theme_label |
| 2 | BorrowerObligationGroupingTest | test_my_obligations_shows_the_four_grouped_summary_cards |
| 3 | CompleteWorkflowTest | test_release_is_blocked_after_pickup_window_expiry_until_rescheduled |
| 4 | CreateRequestOrganizationalUnitTest | test_access_classification_wording_is_unaffected_by_the_division_rename |
| 5 | DashboardAccountabilityReconciliationTest | test_accountability_filter_and_decision_copy_is_clear_and_current |
| 6 | FoundationTest | test_landing_page_is_available |
| 7 | OffCampusGatePassWorkflowTest | test_off_campus_non_student_activity_requires_only_request_letter_and_routes_to_officer_first |
| 8 | OffCampusGatePassWorkflowTest | test_off_campus_student_activity_requires_ptc_but_non_student_activity_does_not |
| 9 | OffCampusGatePassWorkflowTest | test_request_supporting_document_uses_the_ictu_configured_upload_limit |
| 10 | OffCampusGatePassWorkflowTest | test_action_officer_can_return_for_correction_and_corrected_version_restarts_verification |
| 11 | OffCampusGatePassWorkflowTest | test_action_officer_verification_routes_to_head_without_approving_reserving_or_generating |
| 12 | OffCampusGatePassWorkflowTest | test_head_rejection_creates_no_inventory_or_operational_documents |
| 13 | OffCampusGatePassWorkflowTest | test_head_return_for_revision_creates_no_inventory_or_operational_documents |
| 14 | OffCampusGatePassWorkflowTest | test_head_approval_generates_one_gate_pass_and_borrower_slip_then_physical_release_reuses_them |
| 15 | RoleWorkspaceSmokeTest | test_spmu_workspace_pages_render |
| 16 | RoleWorkspaceSmokeTest | test_each_active_workspace_has_a_focused_role_specific_menu |
| 17 | RsldppWorkflowTest | test_no_count_as_offense_decision_remains_for_a_fresh_open_incident |
| 18 | RsldppWorkflowTest | test_clear_decision_creates_no_offense_and_resolves_immediately |
| 19 | RsldppWorkflowTest | test_confirm_decision_auto_records_offense_and_sanction_when_eligible |
| 20 | RsldppWorkflowTest | test_confirmed_accountability_notifies_f2f_processing_without_generating_rslddp |
| 21 | RsldppWorkflowTest | test_confirmed_accountability_resolves_directly_after_confirmed_f2f_processing |
| 22 | RsldppWorkflowTest | test_legacy_compliance_case_without_rslddp_still_requires_head_final_resolution |
| 23 | RsldppWorkflowTest | test_status_badge_shows_canonical_labels_and_tones_for_rslddp_statuses |
| 24 | UniversalDocumentActionUiTest | unavailable_documents_use_not_available_and_non_applicable_documents_are_hidden |

## Actual runtime and recovery evidence

The current-source Docker production image built with no development packages. It ran Laravel **13.35.0**, PHP **8.4.26**, MariaDB **11.4.12**, and 71 migrations in an owned schema. Required gd/intl/mbstring/pcntl/pdo_mysql/zip extensions and soffice/pdftoppm/tesseract binaries were found. Final /up and /login both returned HTTP 200. This checks binary presence and boot, not every custom document conversion.

Real HTTP checks used synthetic Admin, SAO, Employee, External and ICTU accounts. SAO borrowing-approval access was denied without formal delegation, Admin physical custody redirected to Request Records, and External had no CSPC unit/employee number. Synthetic identity/approval setup is a stated fixture precondition, not live Google, OTP or SAO-review proof. The scheduler executed spmu:process-deadlines once successfully. A database worker exited successfully on an empty queue; no queued message, retry, persistent daemon or Cloud multi-replica execution is certified.

The test packaging initially omitted artisan and public/.htaccess, and the synthetic user setup omitted protected email verification timestamps. These harness omissions were corrected and the package was rebuilt/retested; they are not reported as production source defects. The final role expectations also preserve the inspected Head/formally-delegated-officer borrowing-approval authority. Original Unit/Feature/Frontend tests were never edited to eliminate their failures.

Native recovery restored **72 tables**, one synthetic request, its saved company, one linked identity and a private file with its original SHA. This was a local rehearsal on owned databases, not an actual Cloud/production restore or approved RPO/RTO/retention policy.

The previously running original container still reports Laravel **13.25.0** while the current checkout/package is **13.35.0**. Its healthy status does not certify the updated source. Original scheduler/queue services were not running in the observed stack. A future authorized deployment must package/recreate the correct application and required services.

## Configuration and Cloud limits

The current local checkout configuration preflight passed **4 of 11 checks** and failed 7: HTTPS URL, shared sessions, secure cookies, shared cache/scheduler locks, durable queue, Cloud private persistent evidence disk, and MariaDB connection. This is the local SQLite preview configuration, not a configured production target. Its 32-byte demonstration key passes the existing command's length check but is a known demo key and must never be used in production. Docker persistent volumes and Cloud object storage are different deployment arrangements; a local disk with an approved persistent Docker volume is not automatically invalid for an institutional Docker deployment.

Actual Laravel SMTP connection/authentication passed again with **NO EMAIL SENT**. Live Google consent/callback, OTP/reset inbox receipt and full account linking remain unverified; the last real browser Google attempt in S1-AUTH-LIVE-01 returned redirect_uri_mismatch and has no confirmed successful retest. Credentials and fake test deliveries cannot certify these flows.

ProfilePictureController remains hard-coded to local storage, while protected uploads now have saved-disk support. Real private object-store operations, all file/template consumers, replica/session/lock behavior, durable mail/retries, approved production secrets/HTTPS and external MariaDB connectivity/TLS are still unverified for Cloud. [Laravel Cloud managed database documentation](https://laravel.com/cloud/docs/resources/databases/laravel-mysql) documents MySQL; it does not authorize replacing the approved MariaDB 11.4 stack. [Object storage](https://laravel.com/cloud/docs/resources/object-storage), [runtimes](https://laravel.com/cloud/docs/runtimes) and [workers](https://laravel.com/cloud/docs/workers) describe platform capabilities, not completed SPMU deployment.

## Institutional requirements and conflicts

The available manuscript/workflow and confirmed user decisions remain authoritative. Additional read-only references were found under E:/New SPMU System: ICTU Requirements.docx, Borrowable Items.docx, Test-KPI.docx, the KPI computation sheets and the ISO questionnaire. The ICTU Word document contains all nine deliverables. It is not a supplied signed approval letter or an infrastructure-capacity approval. The original Hosting_Requirements (1).docx, signed ICTU screenshot/letter and Brussels TD RSC PDF were not found in the checked project locations; the reported Bolivia project/title mismatch remains a flagged, unverified attribution issue.

The ICTU document specifies a fully containerized system running on ICTU premises. The user targets Laravel Cloud. This is a material hosting conflict that needs a written institutional resolution before affected deployment behavior changes. No CPU, RAM, network, domain, storage capacity, backup schedule or provider approval was inferred from unavailable/blank form fields.

The existing DEPLOYMENT-ICTU.md and .env.docker.example restrict GOOGLE_ALLOWED_DOMAINS to cspc.edu.ph, conflicting with the already confirmed external Google-registration policy. The current private preview permits external accounts; the stale deployment example/runbook must be reconciled before copying them into production. Do not ask the user to reapprove the already confirmed application policy.

The supplied ISO questionnaire is an unfilled operational-user instrument, while the current manuscript also specifies pre-deployment technical validation by five IT experts. Keep those evidence roles distinct; neither an empty instrument nor automated tests prove completed evaluation. The asset list is available, but physical opening-balance reconciliation/authorization has not been verified. No respondent sampling or manuscript text was edited.

## Nine ICTU deliverables and evidence status

| No. | Deliverable in ICTU Requirements.docx | Status | Evidence and remaining work |
| ---: | --- | --- | --- |
| 1 | Deployed System | NOT STARTED | Current local test image boots; approved ICTU location and fully functional deployed system are not certified. This is a later deployment deliverable, not a circular prerequisite to already having deployed. |
| 2 | Pre-Deployment & Test Report | FOR REVIEW | This report and fresh receipts exist; 24 acceptance failures remain unresolved. |
| 3 | Panel Approval Documentation | BLOCKED | Signed applicable panel approval/inspection evidence unavailable. |
| 4 | Training Materials | FOR REVIEW | Technical/setup guides exist; complete role manuals, training sessions and attendance/feedback are unverified. |
| 5 | System Documentation | FOR REVIEW | Code/architecture/Docker/maintenance documentation exists; stale provider/hosting instructions and final operational configuration remain to reconcile. |
| 6 | Security and Backup Documentation | FOR REVIEW | Role tests, dependency audits and local native restore pass; approved network security, monitoring, backup frequency/retention/encryption/off-site ownership and actual target restore remain unverified. |
| 7 | User Acceptance Test Report | BLOCKED | Formal UAT with recorded feedback, corrections and sign-off not supplied. HTTP fixtures are not end-user acceptance. |
| 8 | Final Delivery Package | FOR REVIEW | Current local image and source exist; approved release configuration, complete supporting documents and final packaged delivery are not complete. No secrets are included in this report/image context. |
| 9 | Handover & Sign-off | NOT STARTED | Formal credentials handover and signed acknowledgement remain later delivery actions. No credentials were disclosed. |

Deployment/handover deliverables are tracked in their proper later phase; their absence alone is not used as a circular pre-deployment blocker. The current blockers are unresolved technical acceptance, actual provider/target infrastructure checks, required pre-deployment quality/UAT/panel evidence and approved hosting/security/recovery arrangements.

## Gate and next implementation increments

**Overall gate: NOT READY. Do not pre-deploy/release this as an approved finished system.**

1. Recommended **S1-QA-OVERALL-02**: requirement/fixture mapping for these 24 failures; correct verified test-fixture defects and independently confirmed implementation defects without weakening date, permission or E-sign checks. Use the failed cases as acceptance evidence; rerun the affected group and then both whole suites after actual corrections.
2. Complete **S1-AUTH-LIVE-01**: owner-authorized exact Google callback and actual employee/external/linking flows; confirm test inbox, then real OTP and one-use password-reset completion. The owner performs the final browser password-change step.
3. Complete target compatibility: photo/file persistence, applicable document runtime, approved MariaDB 11.4 hosting/TLS, HTTPS/shared sessions/locks, workers/scheduler/mail retries, monitoring and target backup/restore. Resolve the premises-versus-Cloud institutional hosting conflict before deployment changes.
4. Complete applicable panel, expert quality evaluation, formal UAT, training/documentation and the reviewed delivery/security package before the release gate. **Real operational KPI measurement remains after approved deployment**, as confirmed by the user; it is not moved before deployment. Use approved comparable cases/baselines, not automated test counts as research measurements.

## Preservation

646 snapshotted source files and both private environment files are unchanged. All 8 checked reference files are unchanged. The 73 original SQLite tables/schema match the earlier recorded consistent baseline, with integrity OK and zero FK errors. Own synthetic databases/containers/image are separate from original user containers; cleanup is recorded separately. No application/test behavior, existing account, inventory, signed evidence, manuscript or workflow was edited by this audit.
