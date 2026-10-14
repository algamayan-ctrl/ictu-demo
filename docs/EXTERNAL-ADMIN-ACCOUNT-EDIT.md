# S1-REG-03 — external Admin account-edit compatibility

Status: VERIFIED COMPLETE. Selected technical gate: PASS (13 October 2026). Full workflow and institutional deployment acceptance remain separate.

## Requirements and field/permission map before code changes

Objective: allow authorized Admin account maintenance for an existing External Borrower without fabricated employee/personnel/CSPC-unit data or rewriting reviewed registration/borrowing evidence. This is a small XP compatibility increment, not an account-type conversion or new registration/reapproval process.

Sources: supplied workflow paragraphs0–4 establish verified sign-in, separate Employee/External registration and SAO review; paragraph3 gives external full name/email/mobile/company/Valid ID/password. The user confirmed SAO self-registration approval, no walk-ins, and Admin operational account management. Manuscript XP/BPR/reliable account management and the existing UserPolicy, email-change OTP and private evidence contracts remain the baseline. Walk-in paragraphs157–160 remain overridden by the user's confirmed exclusion. Synced sources are read-only; missing hosting/ICTU/TD RSC/inventory/KPI attachments are not inferred from form fields.

Actors: existing active, verified, approved Admin (SPMU Head) with its non-revoked mapped SPMU role manages operational accounts; ICTU retains its existing maintenance scope with its mapped ICTU role. SAO/Borrower/guest cannot call Admin editing; Admin cannot manage ICTU targets. Under-lock actor/target scope is rechecked. Employee role changes and primary/additional unit permissions stay on their existing path. This external edit retains Borrower classification and existing role-assignment history; it cannot turn an External Borrower registration into a CSPC staff account. Conversion is a separate unresolved workflow, not silently performed here. A failing direct-controller case proved that the old directory policy accepted a revoked portal assignment; the policy correction enforces the existing RBAC role mapping without granting new authority.

| Field/group | External edit contract | Source/permission implication |
|---|---|---|
| Full name, mobile | Editable using existing length/validation rules | Existing account administration; no registration-review transition |
| Email | Editable, valid unique mailbox; normalize case/whitespace | Existing external signup allows non-CSPC mailboxes; existing email-change OTP clears prior verification and blocks sign-in until proof |
| Password/confirmation | Optional; existing server minimum12, letters/numbers/symbols, no invented maximum or mixed-case rule | Retain when blank; never log password/hash; shared form limits/help match the unchanged server policy |
| Account status | Existing Admin status control retained | Cannot approve Pending/Rejected registration or fabricate a role |
| Access classification/roles | Borrower retained; no new external role grants | SAO review remains the only self-registration Borrower grant; Employee staff-role assignment behavior retained |
| Company/type/Valid ID/reviewer/status | Saved values displayed read-only; posted override rejected | Preserve original SAO evidence, ownership, review actor/date/remarks and file hashes |
| Employee/personnel/division/office/unit fields | Absent from external form; posted values rejected | No employee number, personnel designation, internal unit or additional-unit grant invented |
| Employee creation/edit | Existing form/validation/domain/assignment path retained | No external Admin provisioning or employee-to-external conversion added |

Stories: Admin can change an external contact/password/account state and see the correct fields; the Borrower keeps truthful saved affiliation and request evidence. Admin cannot promote or approve an external applicant through basic editing. Saving an unchanged form produces no account-update audit or pivot metadata rewrite. A failed audit/role/validation write preserves existing account/review/request/private history.

Preconditions: authorized actor and in-scope target, current persisted registration type, valid input. Postconditions: successful account-field edit is audited once when meaningful, immutable review/company/ID and previously saved request-version/company/signatures/output bytes remain; role/unit history unchanged. Changed email becomes unverified and uses the existing post-commit OTP flow without changing SAO status. Pending/Rejected applicants remain blocked even if account status is Active.

Business rules: derive external type from persisted registration, never a client checkbox; reject company/type/review/evidence/unit/employee/role/email-verification overrides. Lock registration before User in the same order as SAO review, refresh/revalidate type and scope before writing. No external role synchronization, unit creation/revocation/assignment, or reviewed-affiliation mutation. Missing/corrupted existing ID is not repaired/replaced or freshly certified by an account edit; its stored evidence stays unchanged and registration decision integrity remains the separate existing review gate.

Inputs/outputs: existing PUT account endpoint with name/email/mobile/status/Borrower classification and optional confirmed password; output existing redirect, accurate account-update/no-change or email-reverification notice and existing audit events. Read-only external summary uses escaped persisted fields. Reuse original cards, grid, CSS/icons/spacing and responsive layout; password controls must work when employee organization controls are absent.

Database impact: no schema/dependency migration; only allowed User fields, existing audit and existing changed-mailbox verification records. Registration, units/pivots, role history, prior requests/versions/documents/signatures/inventory remain untouched. States: contact/password edit keeps review/verification; email change clears mailbox verification only; account-status change keeps SAO review; no new approval/role/affiliation transition.

Exceptions/acceptance: reproduce external form/save failures before fix; test Admin/ICTU, approved/pending/rejected/inactive targets, unauthorized/inactive/unverified actors, blank/no-op credentials, password and email boundaries/duplicate/invalid inputs, forged reviewed/employee/unit/role fields, stale actor/target/type and concurrent SAO review, audit rollback, missing/corrupt existing evidence and retained request/file hashes. Both-engine focused/retained regression, existing affected frontend checks and desktop/mobile browser review; independent MariaDB workers if registration locking is introduced. Preserve pre-existing preview database/private/reference bytes; browser writes only to a separate owned disposable demo. Record actual counts/logs before PASS.

Dependencies/risks: current PHP8.4/Laravel13, MariaDB11.4, existing registration schema/policies and portable test harness. Actual SMTP delivery, broader lost-update/outbox design, government Google domains, company/ID corrections, staff conversion/reapproval policies, operating-hour conflict and deployment acceptance remain separate. Existing email-change semantics are retained; no new SAO reapproval rule is inferred.

## Verification and gate

Workspace evidence: sprint1/reg03/. Corrected pre-change baseline8/8 targeted external form/save/forged-employee cases failed; the old code accepted invented employee/unit assignments, including pending/rejected applicants. Initial diagnostics also exposed the framework's failing-response contextualizer masking validation redirects with an `all() on array` error; the direct acceptance assertion reports the underlying failure and vendor files were not changed. A separate revoked-role case failed before the directory policy correction. First focused48/48 and expanded54/54 passed on both engines; final scope adds two minimum/Unicode password cases.

Both interim related runs executed569 cases:559 passed and10 ICTU fixture cases failed because privileged factory actors had no mapped role. Assertions/behavior were retained; three test files now provision legitimate actor roles. A targeted rerun exposed one additional self-protected actor and the dashboard class's deliberately unseeded role dictionary; those setups were corrected. Final targeted19/19 and116 assertions passed on SQLite. Historical failed logs remain, without exclusions/waivers. Final clean related runs now pass571/571 with6145 assertions on each engine, including all56 new external-edit cases and the nine previously reconciled inherited cases. This32-class related scope is not the entire repository suite.

Native MariaDB11.4.12 independent Admin edit/SAO approval workers and exact late-audit rollback passed. Registration→User lock order preserves both contact change and SAO review; only SAO grants the one Borrower role, with no internal assignment, premature borrowing output, or old private-ID hash change. Supporting native receipts are under reg03/.

Actual browser QA used a separate owned SQLite database at localhost8766 and array mail. Approved/pending contact saves preserve registration/roles/evidence; pending remains Pending with no role. Desktop1280 and mobile390 field bounds fit; employee fields/units remain. The browser first timed out and lost its bindings; recovery succeeded using the documented AX controls, and failed/timed-out actions are not counted as successful UI verification. Screenshots/data receipts are retained. No browser password change or actual SMTP delivery was performed.

The browser also proved inherited input min8/max12 restrictions contradicted server min12/no maximum. Four failing client-rule cases were added and the shared account-form hints/limits corrected to the unchanged PHP rule, including Unicode character groups. All31 final frontend checks pass. Shared CSS/colors/icons/grid are unchanged. External OTP failure feedback now points to the existing verification page/ICTU assistance rather than promising an Admin-only resend control that does not exist.

## Final acceptance evidence

| Check | Actual result | Workspace receipt |
|---|---|---|
| Related SQLite regression | 571/571;6145 assertions | `sprint1/reg03-final-regression-sqlite.txt` |
| Related MariaDB11.4.12 regression | 571/571;6145 assertions | `sprint1/reg03-final-regression-mariadb.txt` |
| Additional employee/unit/own-account regression | 10/10;152 assertions on each engine; separate run scope | `sprint1/reg03-employee-compatibility-sqlite.txt`, `sprint1/reg03-employee-compatibility-mariadb.txt` |
| Frontend | 31/31;0 failed/skipped, including7 new cases | `sprint1/reg03-final-frontend.txt` |
| Independent concurrent edit/review and late-audit rollback | Passed; one SAO-attributed Borrower grant;0 new internal assignments or premature outputs | `sprint1/reg03/native-acceptance.json`, `native-rollback.json` |
| Desktop/narrow viewport and actual approved/pending saves | Passed; reviewed registration/role/private evidence preserved | `sprint1/reg03/browser-verification.md`, `ui-acceptance-summary.json` and screenshots |
| Source and protected history | 619 runtime/test/public files match working/test/preview;5 Docker source files unchanged;162 prior database/private/reference files byte-identical | `sprint1/reg03/source-manifest.json`, `final-preservation-summary.json`, `final-preserved-files.json` |
| Syntax/style/whitespace | Six affected PHP files parse; policy/new test style and whitespace pass; inherited controller four-fixer style debt unchanged | `sprint1/reg03/syntax.txt`, `quality.json` |
| Cleanup and preview | Two exact owned MariaDB containers/anonymous volumes removed; separate QA browser logged out/closed and server stopped; existing containers healthy, preview HTTP200 | `sprint1/reg03/cleanup.json`, `ui-cleanup.json`, `preview.json` |

The accepted code changes are limited to three existing application files (account controller, policy and shared form), two new test files, three existing test fixture setups and documentation. No application service, database schema, dependency, shared CSS/icon asset or institutional rule was added. Legacy classified Admin/ICTU actors with a missing/revoked mapped role are denied; basic account editing does not fabricate an assignment to repair them. Existing account-creation/staff-role/unit behavior is retained for legitimate employee accounts.

Main preview source is updated at `http://127.0.0.1:8765/login`, and VS Code opened the working repo. Its existing SQLite database and all old private evidence remain unchanged. Only empty owned verification databases were initialized; there was no working/live migration, reset, account edit, commit/push or deployment. Test mail uses the array transport; actual OTP delivery is not certified. The ordinary client-rule checks and responsive browser viewport do not establish physical-device, load, ISO questionnaire or operational KPI acceptance.

Completion checklist: requirements/permissions mapped; red acceptance reproduced; smallest demonstrated code correction;56 new feature and7 frontend cases; clean related and employee compatibility on both engines; real concurrent workers/rollback; actual browser saves/layout; protected history/source match; code review, documentation and exact-owned cleanup — all verified. Gate PASS applies only to S1-REG-03.

Next recommended authorized increment: **S1-CANCEL-01 — SAO-confirmed cancellation**, beginning with existing deadline/queue/reservation/permission mapping and failing tests that a deadline never cancels by itself, unauthorized actors cannot confirm, and authorized confirmation releases applicable inventory exactly once. Do not implement it as part of this completed account-edit scope. Missing institutional attachments, reported unverified Bolivia title mismatch, operating-window conflict, company/ID/type corrections, OAuth policy and deployment remain separate.
