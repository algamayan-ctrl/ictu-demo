# S1-ROUTE-02B — corrected external verification and rejection

Status: VERIFIED COMPLETE for the selected increment. Gate: PASS. Full S1-ROUTE-02 remains IN PROGRESS; deployment NOT READY.

## Requirements and design before implementation

Objective: verify the actual corrected external off-campus request with the SAO's deliberate registered E-signature, forward only that version to Admin, and reject with required remarks without inventory reservation, custody or operational documents.

Sources: user-authorized S1-ROUTE-02B and confirmed off-campus SAO → Admin routing/E-sign rules; supplied workflow paragraphs P26/P28/P33/P36/P48 (required evidence/consent), P52/P56 (current reviewer routing), P162/P191/P193 (decisions/history/corrections). Paragraph numbers are zero-based extracted body paragraphs, not manuscript page numbers. Approved manuscript XP/BPR supports small increments with tests. Existing implementation is the technical baseline, not a new institutional policy.

Actors/permissions: approved active external borrower corrects only their own returned request; existing SAO verifies off-campus evidence using their own signature; existing Admin/Head or formally authorized delegate retains final decision authority. No new delegation, role, employee unit or eligibility is introduced.

User stories: SAO reviews and signs corrected v2; Admin receives only current verified v2 and rejects with explanation; borrower receives the rejection and can inspect original and corrected signed evidence. A stale tab or concurrent retry cannot reject a superseding version or send duplicate notices.

Preconditions: S1-ROUTE-02A passed; actual v1 submission/SAO return/corrected v2 resubmission exists, approved reviewed external company, null CSPC unit assignments, readable fresh required documents, current borrower signature, pending current SAO step. Verification requires current effective SAO signature and explicit consent/checklists. Rejection requires current verified off-campus version and pending Head step, authorized actor and nonblank bounded remarks.

Postconditions/state: UNDER_SPMU / SAO RECEIVED → SAO VERIFIED plus its immutable signature snapshot and verified current documents → Head RECEIVED → REJECTED with Head actor/remarks/history/audit/borrower notice. Verification does not grant approval. Original returned version, borrower versions/signature bytes and reviewed affiliation stay intact. Existing off-campus document verification remains recorded after Head rejection. Rejection does not add a Head signature (existing approval-only signature rule).

Inputs/outputs: corrected request identity, current reviewed version identity, deliberate SAO consent/checklists, optional verification remarks, required rejection remarks; saved review steps, signature snapshot, document verification metadata, status history, audit and correctly scoped system/email delivery records. Email is tested with a synthetic array transport; no real institutional delivery is claimed.

Database impact: existing request/version, approval_steps, signature_snapshots/stored_files, supporting documents, histories, audits and notifications only. No migration/schema changes proposed. No allocation/custody/generated-document writes are allowed in this increment.

Existing code: positive SAO verification validates documents, captures its signature, verifies documents, creates Head step and notifies before its audit. Head decide locks/reloads current request but currently accepts no browser review identity and does not compare a preloaded version number; rejection transitions/notifies before its audit. Existing locks, role/registration gates, rollback file tracking, recipient filters and approval machinery remain dependencies.

Proposed technical work: first prove actual cycles and failure cases. Fix only demonstrated stale-version consistency or known audit-before-notice gaps, retaining existing optional/legacy service callers and approval authority. A supplied version identity is a consistency check, never authorization. Preserve ZIP UI; hidden identity fields need no design rewrite.

Failures/acceptance: normal regular and conditional-PTC cycles; missing consent/checklist/current signature or missing/corrupt document; premature/wrong/revoked actor actions; missing/oversized remarks; immutable old/current bytes and affiliation; stale service/form review; duplicate and actual parallel decision; audit/storage rollback with no misleading mail attempt; exactly one final decision/notice; correct queues and zero inventory/custody/output at every step. Related regression including all nine reconciled inherited cases must pass on SQLite and MariaDB 11.4, with frontend/browser and source comparison evidence.

Dependencies/risks: verified PHP 8.4.26/Laravel 13.25.0/PHPUnit 12.5.33; separate synthetic database/file/Blade caches. Preserve existing preview records and user sessions/uploads. Missing ICTU/hosting/inventory/KPI references prevent institutional deployment certification; reported TD RSC Bolivia title mismatch remains unverified. Official document generation timing is pending and does not affect this rejection-only step. Real SMTP/OAuth, broader informational P50 recipients, automatic-cancellation correction, complete external approval/output and whole-system quality evaluation remain separate.

Sequence: record failing baselines → small fix → focused/related tests on both engines → parallel rejection → preserved preview actual verification/rejection → source/quality/cleanup evidence → progress/checklist/gate. No working/live reset/migration, deployment, commit/push or synced-reference edits.

Focused retest discovery: version consistency and known audit rollback now pass, but two actual-cycle cases reach rejection and fail because the borrower request page omits the saved rejection remarks. Add a current Head rejection reason using the existing content-area/callout design with escaped Blade text, visible only to already authorized viewers. Add escaped-content/read-only rejection coverage. This presents the saved decision and does not invent a new rejection or resubmission rule.

## Implemented changes and verification evidence

Changed three existing application files: RequestWorkflowService adds optional Head reviewed-version identity and compares the bound version again under the existing lock; positive SAO verification and Head rejection audits now precede their notices. ApprovalController validates/passes that identity. requests/show sends the identity for either reviewer and displays the current Head rejection reason using existing callout styling and escaped text. Added ExternalBorrowingRejectionTest with 29 data-provider cases. No migration, stock/approval-authority rewrite or CSS/global design change.

| Check | Actual result | Evidence in project workspace sprint1/ |
|---|---|---|
| Initial discovery | 14/24 passed, 6 failed, 4 fixture errors, 521 assertions; wrong signature/document fixture names are separate from behavior defects | route2b-baseline.txt |
| Corrected stronger baseline | 4/10 passed, 6 failed, 279 assertions; two missing rendered review-identity assertions, stale bound model, stale form, and two audit-before-notice failures | route2b-defect-baseline.txt |
| First focused retest | 22/24 passed, 728 assertions; two successful decision cycles expose missing visible borrower rejection reason | route2b-focused-sqlite.txt |
| Focused before item-note correction | 29/29 passed, 850 assertions, exit 0 | route2b-final-focused-sqlite.txt |
| First related SQLite regression | 359/359 passed, 3560 assertions, before item-note correction | route2b-final-regression-sqlite.txt |
| First related MariaDB 11.4.12 regression | Same 359/359 passed, 3560 assertions, before item-note correction | route2b-final-regression-mariadb.txt |
| Frontend | 22/22 passed, no failures/skips, exit 0 | route2b-final-frontend.txt |
| Actual parallel rejection | Two independent workers bound actual corrected/verified v2; one rejected with302, the other denied with decision validation; one rejection audit/notice, verified SAO evidence retained, no allocation/custody/output, 9 file records and 9 disk files | route2b-native-setup.txt, route2b-native-one.txt, route2b-native-two.txt, route2b-native-inspect.txt |
| Syntax/style/review/source identity | Three PHP syntax checks, scoped new-test Pint and git whitespace checks passed; compared current changes against retained originals; 33 source hashes match working checkout, tested copy and preview | route2b-quality.txt, route2b-code-review.txt, route2b-file-manifest.json |
| Disposable DB cleanup | Exact owned database b1005eaaa8a5113786a4ec150f4680799742af1384a0489591d9e841f4484d58 and its anonymous volume removed after tests; existing preview preserved | route2b-container-id.txt, route2b-cleanup.txt |

These are 359 distinct cases repeated on two engines, in 22 related feature classes including all nine inherited reconciled cases without exclusions. This is the affected scope, not the full repository suite or institutional ISO/KPI/load evaluation. Native concurrency exercised actual controllers and locked MariaDB transactions, not two browser middleware sessions. Synthetic notifications use array mail or direct Mail::html instrumentation; no real SMTP delivery is asserted. Known audit failure now attempts zero email; broader transaction commit/transport/outbox reliability remains separate.

Local browser/data: preserved request6, BR-20261012160920-5, originally created in S1-ROUTE-02A. Before-state capture retains both borrower versions/snapshots and six evidence-file hashes; verification captured SAO user2 snapshot5, verified only corrected documents and created Head RECEIVED step. Head user3 rejected with explicit synthetic remarks; borrower notice targets user5. Both borrower versions/snapshots/private bytes and original returned review remain unchanged, company Example External Agency and null CSPC units retained. Target allocation/custody/operational output remains zero; the preview's three unrelated historical QA allocations/custodies are preserved. Evidence: route2b-ui-original.json, route2b-ui-before.txt, route2b-ui-verified.txt, route2b-ui-final.txt. Browser presentation review completed; see route2b-browser-verification.md.

Browser-discovered item-note extension: a rejected borrower request correctly shows Rejected in its header/tracker, but its Requested Items note infers Awaiting SPMU review from null approved quantities. The two actual-cycle tests fail on that stale label (route2b-label-baseline.txt: 0/2 passed, 196 assertions). The same existing view now displays Request rejected for the recorded REJECTED status; all quantity values, other labels and decision rules remain. Both cases verify the closed label and absence of the pending label. Final accepted related runs after this change are 359/359 and 3564 assertions on each engine: route2b-accepted-regression-sqlite.txt and route2b-accepted-regression-mariadb.txt, exit0. Final frontend route2b-accepted-frontend.txt is 22/22, zero failures/skips; scoped style/whitespace passed again (route2b-accepted-style.txt). Source manifest was regenerated and all33 still match. The second exact owned regression database was removed separately (route2b-final-container-id.txt, route2b-final-cleanup.txt); no existing database was reset.

## Completion checklist and gate

- [x] Requirements/actors/state/failures/database/dependencies mapped before implementation.
- [x] Actual corrected external SAO own E-sign → Admin rejection cycle verified with regular and conditional PTC evidence.
- [x] Original/current borrower evidence, reviewed affiliation, SAO snapshot, current queues/remarks/notices and zero reservation/custody/output verified.
- [x] Wrong/revoked/premature actors, invalid consent/identity/remarks/signature/documents, stale/duplicate decisions and audit rollback verified.
- [x] Actual parallel rejection produces one decision/audit/notice and no orphan evidence files.
- [x] Final 359/359 related cases on each engine,3564 assertions;22 frontend; inherited cases retained.
- [x] Actual browser final closed-state label/reason/documents and notification reviewed; desktop screenshot inspected. Final source hashes match33 files; syntax/scoped style/whitespace/diff review passed.
- [x] Both exact owned disposable databases cleaned up; existing preview/server/data preserved. No migration/schema addition, working/live reset, synced-reference edit, commit/push or deployment.
- [x] Progress register and step guide updated; evidence and practical limits documented.

Gate PASS for S1-ROUTE-02B only. Verification never approved stock; Admin rejection adds no Head signature under the existing approval-only E-sign rule. Optional Head review identity retains legacy callers, while supplied mismatches and stale bound versions are refused under lock. Broader S1-ROUTE-02 and institutional deployment are incomplete. Missing institutional attachments, reported Bolivia mismatch, production/real-mail/OAuth/physical-phone/load/operational KPI acceptance and full transport commit reliability remain separate.

Next authorized step: S1-ROUTE-02C-PLAN — read-only external approval/output readiness mapping. Trace company/null-unit fields into the official packet, present concrete timing conflicts and proposed resolution for confirmation before affected behavior changes, then define current-authority, exactly-once/competing-stock and rollback acceptance tests. Do not implement approval/output timing assumptions or real inventory balances.