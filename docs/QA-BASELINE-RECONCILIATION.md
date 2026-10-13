# S1-QA-BASE-01 — requirements, reconciliation and verification

Status: VERIFIED COMPLETE. Selected gate: PASS. Whole-system/deployment gate: NOT READY.

Later factual correction from S1-ROUTE-02C-PLAN: the timing uncertainty recorded below incorrectly described the current implementation as generating forms at preparation. ZIP/current RequestWorkflowService already generates at Head approval, and CustodyService::prepare validates existing approved forms; supplied workflow P164/P191 agrees. See EXTERNAL-APPROVAL-OUTPUT-READINESS.md/TIME-01. No runtime timing change or historical execution-log edit was made; the QA gate/results remain as recorded.

Objective: reconcile all nine inherited failures without exclusions, preserve the approved ZIP design and the already verified registration/routing/account boundaries, and distinguish stale assertions from presentation defects.

Confirmed sources: the partner's instruction to reconcile these failures; prior instruction to retain ZIP colors/layout; ZIP dashboard has paired desktop panels and a responsive collapse, not the stacked layout asserted by one test. ZIP notification view matches the current file exactly and uses per-record resolution, not the bulk-selection controls asserted by two tests. Manuscript paragraphs about records/audit traceability and notifications remain authoritative. Workflow paragraphs 9/12 require request/borrowing monitoring, 165–174 identify applicable operational documents, and 223/230 retain transaction records and accessible documents. Previously confirmed no-walk-in/no-predictive/no-automatic-cancellation decisions continue to override conflicting workflow text.

Actors/user stories: Borrower reads its own custody state and existing documents before/after return; no genuine obligation is hidden. Admin monitors its permitted request records and paired dashboard; SAO retains physical processing. Admin/ICTU may inspect existing notification delivery records; only ICTU resolves current operational failures, with a technician's own remarks. Legacy SMS is audit-only and is not re-enabled.

Preconditions: existing authenticated/active/verified/approved/workspace gates, record ownership and resolution authorization. Postconditions: read-only views do not change custody/notification history; visible existing documents remain subject/version/status scoped. Resolution remains the existing explicit action and preserves original provider/status data. No new workflow state or transition.

Business rules: pending Gate Pass recording alone is internal SPMU work, not a borrower debt. Preserve genuine incidents/billing/restrictions. One canonical Borrower status label should be used across list/detail/request/dashboard for GATE_PASS_PROCESSING. Display the stored notification status distinctly from its derived category, so FAILED/NOT_CONFIGURED/SKIPPED history remains inspectable. Do not automatically assert SMTP/DNS repair or resurrect removed bulk controls merely to satisfy obsolete UI assertions. Keep the existing bulk API and its authorization/atomicity regression intact.

Inputs/outputs: existing custody/request/document associations and delivery status/provider/resolution data. Outputs: consistent informational Gate Pass badge and expanded Recorded Status field; corrected DOM-based assertions and realistic readable document fixtures. Database impact: no migration or schema change; no production migration/reset. Scope is presentation/test reconciliation, not official document generation or changed pickup timing.

Failure/boundary cases: missing/inapplicable/invalidated/other-subject documents, post-return history, a genuine concurrent obligation, wrong owner/reviewer, SMS/historical/sent rows, unescaped provider/status text, hidden detail rows, and actor without ICTU permission. Existing server-side resolution and borrowing controls must remain.

Implemented reconciliation (all nine original cases verified):

| Original failure | Finding | Small correction |
|---|---|---|
| My Borrowings Gate Pass label | Test opens detail instead of list; detail uses a different label for the same borrower-only status | Use canonical status label in detail, test real list plus no-obligation semantics |
| Gate Pass badge tone | Locator depends on the inconsistent detail label | Preserve info tone and test actual badge, genuine obligations remain covered |
| Pre-release physical-form instructions | Fixture has no generated document or pickup schedule; assertion targets old request markup | Test absent-document honesty plus existing applicable document preview/actual custody pickup state |
| Post-return documents/completion message | Fixture is still OBLIGATION_OPEN with no documents, yet asserts a completed transaction | Create a genuinely CLOSED, verified-Gate-Pass fixture with readable documents and check retained links; keep incomplete-return boundaries |
| Normal/legacy Raw Status text | Expanded view shows only derived category, hiding distinct stored legacy codes | Add escaped Recorded Status in existing details, assert per-row original values and no database rewrite |
| Three legacy statuses Raw Status | Same traceability gap | Same field and precise DOM assertions for all three records |
| DNS bulk-prefill metadata | Removed UI controls and unsupported automatic repair claim | Verify distinct provider errors, eligible individual forms and empty technician remarks |
| Bulk checkbox count | ZIP uses individual resolution forms | Verify exactly the eligible form, excluded history/SMS/sent rows, and read-only Admin; retain API bulk tests |
| Full-width dashboard panels | Test contradicts ZIP and existing frontend pair-layout regression | Assert canonical paired desktop/responsive collapse and content ordering for Admin and SAO |

Acceptance: all nine original cases accounted for with retained before/after evidence; no skip/exclusion in the accepted filter; whole affected test class and related account/routing/release/return/document/notification regression pass on SQLite and MariaDB 11.4; frontend checks pass; browser verifies the status/audit field/preserved dashboard and real document links. Docs/register reflect actual results and limits.

Dependencies/risks: current Laravel 13.25/PHP 8.4.26, DOM extension, readable synthetic PDF helper, private storage and isolated caches/databases. The user preview remains an owned SQLite fixture with array mail. Workflow generation-at-approval wording versus existing preparation-time generation is a separate timing question: this task tests documents that exist and does not silently change their creation time. Missing ICTU/hosting evidence, real SMTP/OAuth and whole-system certification remain outside this gate.

## Verification evidence

Evidence is retained in the ChatGPT project workspace under `sprint1/`. Results below are actual runs, not estimates. The accepted filter selects 20 related feature classes; no inherited method is excluded or skipped. The same 305 distinct cases ran on both engines, not 610 distinct cases. This is affected-scope regression, not the complete repository suite.

| Check | Verified result | Evidence |
|---|---|---|
| Pre-change affected class | 48 cases: 39 passed, nine failed, 185 assertions | qa-base-before.txt |
| First reconciliation plus new negative tests | 50/51 passed, 273 assertions; new wrong-subject access defect exposed | qa-base-after-sqlite.txt |
| Accepted focused SQLite | 51/51 passed, 276 assertions, exit 0 | qa-base-accepted-focused-sqlite.txt |
| Accepted SQLite related regression | 305/305 passed, 2064 assertions, exit 0 | qa-base-regression-sqlite.txt |
| Accepted MariaDB 11.4.12 related regression | 305/305 passed, 2064 assertions, exit 0 | qa-base-regression-mariadb.txt |
| Frontend | 27/27 passed, zero failures/skips, exit 0 | qa-base-frontend.txt |
| Browser | Canonical list/detail info badge, zero false obligation, retained closed-record preview links, paired Admin/SAO panels, exact current/legacy Recorded Status, empty technician remarks, read-only Admin | qa-base-browser-verification.md |
| Read-only preview data inspection | States/status/provider text retained; both synthetic document byte hashes match; original external requests retained; prior inactive applicant remains inactive; no eligible inventory added | qa-base-ui-evidence.txt |
| Review/quality/source identity | Changed PHP syntax passed; scoped test-file Pint passed; Git whitespace passed; all 27 current/prior source files match checkout, tested copy and preview, including four files in this increment | qa-base-quality.txt, qa-base-file-manifest.json |
| Resource cleanup | Exact labeled disposable MariaDB container/anonymous volume removed; existing localhost preview retained, HTTP 200/PID 20304 | qa-base-cleanup.txt, qa-base-acceptance.json |

Frontend scope: dashboard-section-priority, document-action-ui, dynamic-document-preview and notification-email-consistency. Feature scope/filter is recorded verbatim in qa-base-accepted-filter.txt. Historical UI exclusion evidence remains unchanged; the new accepted filter has no negative exclusion and includes the entire DashboardKpiAndGatePassStatusTest class.

Existing code retained: paired ZIP dashboard and its 980px collapse, per-record resolution controls plus the existing bulk API, stored operational states, true obligation precedence, Admin final approval, SAO physical processing, employee units and reviewed external company snapshots. Changed application files are only DocumentController.php, custody/show.blade.php and reports/notifications.blade.php. Added two negative tests (wrong custody/version links and escaped audit text), readable synthetic document helpers, precise per-row DOM assertions and a two-role dashboard provider in the existing feature test file. No new production endpoint, migration or schema change.

Small review steps:

1. Open the existing owned preview at `http://127.0.0.1:8765/login`; mail is array and the demo database is SQLite.
2. Use Borrower Demo: My Borrowings → CUS-QA-BASE-GATEPASS. List/detail should agree on Gate Pass Processing, with an informational blue badge and no false debt in My Obligations. Open the CLOSED request to inspect both retained preview links.
3. Use Admin then SAO: desktop operational cards remain paired; only SAO retains Release/Return navigation.
4. Use ICTU: Delivery Records → search QA Base Fixture → View Details. Recorded Status FAILED and the synthetic provider error are distinct; technician remarks start empty. The explicit legacy audit view retains FAILED/NOT_CONFIGURED/SKIPPED under the shared category and offers no resolution action. Do not interpret synthetic rows as real mail failures or certify a repair by merely reading them.
5. Review the nine-method mapping in INHERITED-REGRESSION-ISSUES.md and both accepted logs before the next increment.

The browser fixtures are synthetic historical records, not an actual physical release/inspection cycle; the PDFs use readable synthetic bytes, not official institutional form certification. Native PDF child rendering/physical printing, phone-device testing, real SMTP/OAuth, generation timing, operational KPI improvement, load testing and institutional deployment sign-off remain outside this gate. No working/live database migration/reset, deployment, commit/push or synced reference edit occurred. The original two external requests and prior preview accounts/files were preserved.

Completion:

- [x] All nine inherited failures traced, classified and reconciled without accepted-run exclusions.
- [x] Real presentation/audit and document-access defects corrected without changing approved authority or ZIP layout.
- [x] Missing/invalidated/wrong-linked documents, genuine obligations, escaped provider/status text and resolution permissions covered.
- [x] Whole affected class plus selected related regression passed on SQLite and MariaDB; frontend and local browser checks completed.
- [x] Source identity, quality, fixture preservation, docs/register and disposable-resource cleanup verified.

Gate decision: **PASS for S1-QA-BASE-01**. Next authorized small task: **S1-ROUTE-02A**, one actual external SAO Return for Revision → borrower corrected E-sign resubmission cycle. Verify required remarks/documents, frozen company/null internal affiliation, preserved original signature/files, exactly one new signed version, re-entry to SAO, no premature Admin decision/stock allocation, and failed-correction rollback. Do not begin official-output timing/company changes or broader approval/reservation work inside this completed QA task.

### Verified scope extension before correction

The first post-reconciliation run passed 50/51 cases: all original inherited cases were reconciled, but the new wrong-subject document test received HTTP 200 instead of 403. Source review confirms that authorization trusts request_version_id ownership even when a CustodyTransaction subject belongs to a different request/borrower. Such inconsistent legacy/corrupted metadata can expose a controlled file. This is a record-integrity/access defect, not an approval-rule conflict.

Implemented correction: before generated-document preview/view/download, require a custody subject and its linked request-version request to agree on request and borrower whenever that version linkage exists. Deny inconsistent/missing custody subjects; retain existing subject types, legacy no-version behavior, staff role checks and invalidated-document responses. No data rewrite, database constraint, or creation-time change. The new negative test now receives HTTP 403 for mismatched links across preview/view/download, while valid CLOSED-record links and file metadata remain intact. This intermediate failure was evidence of a real defect and was not waived.

## 13 October 2026 — C-01 fixture follow-up

The nine inherited cases remain included. During C-01 both-engine broad runs, eight OffCampusGatePassWorkflowTest cases exposed a separate date-sensitive fixture: it resolved the pickup day, then assumed schedule + one calendar day was always an open return day. A Friday schedule chose Saturday17October2026 and the application correctly refused it. The normal-flow fixture now resolves RETURN through the existing operating calendar, keeping every original case/assertion and leaving application calendar behavior unchanged. All369 retained related cases pass in each broad run. A separate new invalid-enum fixture error and its corrected focused rerun are transparently recorded in EXTERNAL-BORROWER-SLIP-AFFILIATION.md; those broad runs are not represented as clean387/387 results. No inherited exclusions were introduced.

## 13 October 2026 — C-02 clean retained regression

The C-02 selected scope passes433/433 cases with4047 assertions on each engine, including all387 C-01 cases,31 new conditional-document cases and15 existing laundry/report cases. The nine inherited cases, calendar fixture correction and corrected C-01 invalid-loaded-value case are retained. No exclusions or rewrites of historical logs. This verifies the active company-display increment and related regressions; actual corrected external approval/reservation/whole-packet/concurrency and institutional deployment remain separate gates.

## 13 October 2026 — C-03 actual approval and real contention

After the current-read inventory correction, the selected30-class scope passed497/497 with5176 assertions on each engine, retaining all433 prior cases without exclusions. The initial native stock check had genuinely overallocated synthetic stock under REPEATABLE READ; its source/data/logs remain failed evidence. Final fresh workers produced exactly one duplicate approval and one competing-stock winner with one returned-for-revision loser,2 reserved from capacity3 per item, no partial loser packet and no orphan files. Native second-PDF rollback preserved every compared row/private byte.

Two late signing-binding cases exposed older same-signer snapshot reuse. The final narrow correction requires the signing audit for the exact current version/step. All151 affected external submission/revision/rejection/approval cases passed afterward on each engine with3081 assertions, including all57 new approval cases. Do not represent the earlier497 cases plus these two as a clean single499-case execution. Unchanged employee/rendering/other affected classes retain the broader497 evidence; all external workflows affected by the final guard were rerun. Actual new preview request7, controlled form fields/hash/render review and historical preservation are recorded in EXTERNAL-CORRECTED-APPROVAL.md. Institutional hours, print/deployment and separate creation/cancellation/analytics work remain open.
