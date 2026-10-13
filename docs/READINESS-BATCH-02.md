# Authorized readiness batch — 13 October 2026

Status: FOR REVIEW. Bounded technical code gate: PASS. Whole deployment gate: NOT READY. The requested 19:00 target is a scheduling target, not permission to invent institutional approvals, live provider results, questionnaire responses, or operational measurements.

## Requirement and dependency map before changes

The authoritative available references are the read-only manuscript and workflow, plus the user's confirmed scope decisions. Manuscript objectives50–55 require development, quality evaluation and separate operational KPIs; paragraph67 excludes predictive functionality; paragraphs75/77 specify BPR/XP. Existing framework/database/approval authority remains in place.

| Increment | Objective, actors and user story | Inputs, outputs, rules and state | Database impact, failures and acceptance |
|---|---|---|---|
| S1-ANALYTICS-QA-02 | Admin needs truthful descriptive reports; Borrowers/SAO cannot gain Admin analytics access | Filed/completed/physical-custody records and applied date filters produce counts, rates and source-record links; preserve factual denominators | No schema change; inherited labels/fixtures must be reconciled without deleting numeric assertions. Confirmed: count open return days using the Admin Transaction Schedule; retain the calculation and label it clearly |
| Audit display correction | Admin sees operational history; legacy ICTU maintenance sees only technical history | Existing audience-scoped latest500 records; show a truncation notice when more audience-visible records exist | No schema/authority change; never count hidden other-audience events in the notice. Verify510 visible records remain persisted and500 displayed |
| Frontend baseline reconciliation | Staff/Borrowers need consistent usable screens | Current focused-case navigation, role-owned action controls and laundry filters must match actual presentation; filtering remains read-only and page-scoped | No workflow-state change; preserve JS interaction tests and authorization. Reconcile superseded presentation expectations; repair missing scope guidance |
| Legacy prediction cleanup | Admin receives descriptive analytics and actual scheduled demand only | Remove unreachable weighted predictions/templates and projected depletion; retain legacy-link refusal/redirect guards and observed release-rate reporting | No migrations/data deletion; archive historical source/tests in workspace baseline. Negative endpoint and descriptive numerical tests must still pass |
| S1-AUTH-01 | Approved accounts need safe optional Google identity and password fallback | OAuth state and verified provider identity cannot create roles or bypass SAO approval. Confirmed: password fallback, external applications subject to SAO approval, employee@cspc.edu.ph, and password-confirmed linking | Inspect existing controller/tests first. Real OAuth depends on configured client and user consent; simulated callbacks are not a live Google result |
| S1-OPS-ACCEPT-01 | SAO records physical release/return/laundry; Admin retains approval/accountability authority | Inventory allocation and rollback, received dates, worker and condition evidence; no external Laundry login or automatic missed-pickup cancellation | Run existing critical acceptance with isolated data; do not reset or seed the running preview. Missing institutional sanctions/rates cannot be invented |
| S1-CLOUD-STORAGE-01 | Authorized users retain private evidence across replicas/deployments | Configurable protected disk with saved disk metadata; existing local evidence remains readable; private writes, failed-write/DB rollback and integrity checks | No migration of original files without an approved copy/verification plan. Remote adapter, actual bucket credentials and all output/download consumers require checks |
| Deployment/evaluation readiness | Institution needs reproducible runtime and truthful evidence | PHP8.4/Laravel13/MariaDB11.4, shared sessions/cache, scheduler, queues/mail, security, recoverable DB and private files | Cloud managed MySQL is not authorization to substitute MariaDB. Missing nine-deliverable ICTU letter/hosting form and real quality/KPI measurements remain external gates |

## Pending material decisions

The user confirmed open return days; Admin Transaction Schedule governs pickup/return hours; password fallback and safe Google linking; and the manuscript sequence of pre-deployment quality evaluation followed by post-deployment operational KPIs. No fixed institutional hours or penalties were invented.

## Execution and evidence plan

1. Freeze current source hashes and original private evidence; preserve concurrent legitimate preview use.
2. Reconcile inherited failures against current queries/views and authorization, preserving numerical/permission checks.
3. Obtain focused red/green evidence, then related SQLite/MariaDB and frontend regression.
4. Map and remove unused predictions, checking remaining consumers before deletion.
5. Implement bounded security/storage fixes with failing acceptance first; verify physical-workflow tests and isolated recovery.
6. Capture actual desktop/mobile checks separately from server-side tests.
7. Update progress with receipt paths and exact remaining blockers; certify only supported gates.

Preconditions: isolated test copies have no live .env/config cache, test schemas/storage are owned by this task, source references remain read-only. Postcondition for every accepted increment: correct output/state, unchanged authority, preserved original evidence, recorded audit where applicable, and passing related tests. Live email/OAuth/Cloud/ICTU/physical-user evaluation cannot be inferred from mocked tests.

## External accountability report extension

Objective: Admin must see reviewed external company on property/late-return case records without fabricated CSPC division/unit assignments. Inputs: existing saved custody version and historical case rows; output: the existing report schema with truthful affiliation display and unchanged employee filters. No database/state/authority/penalty change. Two failing acceptance cases must verify profile edits cannot rewrite reviewed company and legacy bogus employee fields cannot place external cases in employee-only filters. Billing Settlement has no affiliation columns in its current schema; adding them is not part of this correction.

## Progress register and remaining gates

| Task | Status | Dependencies / verified evidence | Outstanding issue and next action |
|---|---|---|---|
| S1-ANALYTICS-QA-02 original30 failures | VERIFIED COMPLETE, bounded technical scope | Confirmed open-return-day definition; all five affected classes included in281 passing cases on both engines | No change to fees or default working hours; broader whole-system certification remains separate |
| S1-ANALYTICS-CLEAN-02 | VERIFIED COMPLETE, runtime/output scope | Removed ForecastService/predictive view/obsolete positive tests, stock-coverage projections and dead report renderers; negative availability/descriptive tests passed | Legacy unsupported-link refusal remains deliberately; archived source is retained outside repo |
| S1-REPORT-EXT-02 | VERIFIED COMPLETE, seven report builders | Saved-version resolver; external company/classification and employee-filter exclusion; employee report regression passed | Billing Settlement has no affiliation columns; missing standalone KPI targets are not invented |
| S1-AUTH-01 safe Google linking code | VERIFIED COMPLETE, simulated acceptance | Provider subject uniqueness, verified email, current-password/local-session binding, expiry, role/SAO checks and fallback tested | Live client/consent/domain/provider login still BLOCKED by configuration; mocks are not live OAuth |
| S1-OPS-ACCEPT-01 physical workflow | FOR REVIEW | Shared74-case regression plus actual approval/number/rollback evidence in the completed receipt table | Whole inventory/source-policy acceptance and all-role manual review require missing inventory/policy references and user review |
| S1-CAL-TIME-01 | FOR REVIEW | User confirmed Admin Transaction Schedule; calculations retain open-return days; new built-in Slip refers to live schedule | Review uploaded custom templates for literal13–16 hours; archived signed originals must remain intact |
| S1-CLOUD-STORAGE-01 protected evidence | FOR REVIEW | Private configured disk, saved disk metadata, integrity and failed-write/DB rollback tests; adapter installed | Actual private Cloud bucket, existing-object copy verification, profile pictures and local-path/template consumers pending |
| S1-CLOUD-RUNTIME-01 | FOR REVIEW | Current PHP8.4.26/Laravel13.35/MariaDB11.4.12; build/platform/audits; read-only preflight and scheduler single-server lock declaration | Linux/Cloud no-dev build, LibreOffice/templates, approved external MariaDB/TLS, real shared sessions/locks/workers and durable queued email pending |
| S1-DEPLOY-QA-01 recovery/security | FOR REVIEW | Owned72-table native MariaDB restore with synthetic company/identity/private SHA; original business/evidence preservation | Actual Cloud restore, off-site/encrypted backup, approved recovery policy, broader security/operational checks and original ICTU evidence pending |
| S1-ICTU-01 exact nine deliverables | BLOCKED | Original Hosting Requirements and nine-deliverable letter are unavailable in this mirror; no thread-read tool can retrieve them here | Obtain the originals and map each exact deliverable. Blank/requested hosting form fields do not establish approved infrastructure capacity. Reported TD RSC “Bolivia” project/title mismatch remains unverified and cannot certify SPMU approval |
| S1-ISO-01 pre-deployment quality evaluation | BLOCKED, evaluator/input dependency | Evaluation plan prepared for ISO/IEC25010:2023 Functional Suitability and Interaction Capability | Five-expert/manual instrument evaluation and sign-off not performed; automated tests are not questionnaire responses |
| S1-KPI-POST-01 operational evaluation | NOT STARTED, after approved deployment | User confirmed manuscript sequence; empty raw-observation CSV/protocol prepared | At least30 comparable process simulations after approved deployment; confirm standalone definitions/baseline/targets and preserve raw evidence |

## Phased implementation plan

1. Close the bounded technical increment using the completed receipts above; keep full-system/manual quality acceptance open.
2. Finish persistent-file consumers, profile pictures, queued email/retry audit and actual production build/template compatibility in small independently tested increments.
3. Configure approved staging providers, then observe real Google consent/linking, SMTP, worker/retry, scheduler and shared-session/lock behavior; verify approved MariaDB11.4 networking/TLS and private-object restore.
4. Reconcile original Hosting/ICTU nine deliverables and source inventory/KPI definitions; complete expert/interaction evaluation with evidence before the deployment gate.
5. Deploy only after a concrete reviewable configuration and passed institutional/technical gates. No deployment was performed in this batch.
6. Collect matched operational measurements after that approved deployment, using at least30 comparable process simulations; compute Time Efficiency, Accuracy and Productivity from approved definitions and real denominators.

## Exact next small development task

**S1-CLOUD-STORAGE-02A — profile-picture persistent storage mapping and failing acceptance.** Objective: new avatars must survive replicas/deployments while existing local photos remain readable. Actors/permissions: preserve self/staff avatar access and deny borrower access to another user's photo. Preconditions: inspect ProfilePictureController, User profile path, existing protected-file service and stored-file references. Inputs: validated PNG/JPEG/WebP at the existing2MB limit. Output: authorized private avatar response and audited owner metadata. Proposed database change: nullable saved file/disk reference only after compatibility review; preserve the legacy local path and original bytes. Upload/replace/remove must be atomic; failed DB writes clean only newly staged objects, not the prior image. First tests must fail on existing local-only code for private remote storage, legacy reads after disk change, owner/other-user permissions, invalid/oversized uploads, stale concurrent replacement, rollback preserving previous bytes and correct audit. Use isolated SQLite/MariaDB schemas and a private adapter spy; actual bucket checks remain a separate provider gate. No original-file migration/deletion is implied by this plan.

## Verified technical results — 13 October 2026

The 19:00 target was missed. The bounded code gate is PASS; the full-system, institutional and Cloud deployment gate remains NOT READY. No deployment, production restore, questionnaire completion or real operational measurement is claimed.

| Check | Verified result | Receipt under project workspace sprint1/batch02 |
|---|---|---|
| Current Laravel13.35 affected acceptance | 281/281 SQLite; 281/281 MariaDB11.4.12; 1855 assertions per engine | final-acceptance-sqlite.txt; final-acceptance-mariadb.txt |
| Shared release/return/laundry helpers, updated runtime | 74/74; 437 assertions | shared-helper-updated-sqlite.txt |
| Existing accountability/billing report behavior | 3/3; 26 assertions | accountability-report-regression-sqlite.txt |
| Actual approval/rollback and request numbers | 73/73 SQLite; MariaDB integration/report set 76/76 | approval-number-regression-sqlite.txt; integration-report-regression-mariadb.txt |
| Frontend |191 cases:190 passed,0 failures,1 existing optional browser skip | frontend-post-cleanup2.txt |
| Native local recovery |72 restored tables, synthetic request/Google identity/reviewed company/private SHA verified | recovery-result.json |
| Original material |162 reference/evidence files unchanged; additive OAuth migration; observed login timestamp-only updates on3 users and one normal SESSION_IDLE_TIMEOUT audit | preservation-final.json; database-preservation-review.json |
| Dependencies and build |Laravel13.35.0/CommonMark2.10.3/Flysystem3.36.0; Composer and production pnpm audits0 reported advisories; platform checks and Vite build passed; checkout optimized loader10341 classes and HTTP200 | final-composer-audit.json; frontend-audit.json; frontend-build-final.txt; checkout-optimized-autoload.txt; preview-http-final.json |

Counts overlap and must not be added into a whole-suite total. Original broader SQLite was1081 cases,1048 passed and33 failures; a pre-change33-method replay proved32 inherited failures. Current33-method reconciliation passed33/33 with279 assertions. The superseded broader MariaDB run was intentionally stopped before completion after its source/tests became outdated; it is INCOMPLETE, never a full MariaDB pass. The isolated Windows optimized test-loader scan was also stopped before completion; tests used the valid normal loader. Successful optimized checkout loading is a separate result, not a Linux/Cloud no-dev build.

Desktop1280px and mobile390px Scheduled Demand screenshots show synthetic3/2/1 counts without horizontal overflow. Browser session recovery later timed out; a second all-role/date-picker visual review is not certified. New built-in Borrower Slips refer to the Admin Transaction Schedule; archived signed outputs were preserved, and custom templates still need inspection for literal old hours.
