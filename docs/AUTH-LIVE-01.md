# S1-AUTH-LIVE-01 — provider integration and MariaDB verification

Status: BLOCKED on owner/provider actions. Date: 14 October 2026. Development phase: XP iterative development/testing, before system-quality evaluation and deployment. Code and MariaDB acceptance passed; the complete live gate is NOT READY.

## Requirement and permission mapping

Objective: repair duplicated verification feedback, connect the existing private provider configuration to the updated application, then verify actual Google identity, OTP/reset delivery, and MariaDB 11.4 compatibility. The approved authentication scope and confirmed user decisions govern this increment; it does not certify missing ICTU documents or deployment approval.

- Actors: visitors may register or recover their own account; Google proves email ownership; SAO alone reviews self-registration under existing authority. Admin provisioning and roles remain unchanged.
- User stories: a visitor sees one actionable error; a verified Google user completes the correct locked-email form; a manual applicant verifies the emailed OTP before entering the SAO queue; an eligible account owner resets a local password once.
- Preconditions: correct OAuth web client/callback, real authorized sender, persistent same-origin session, current migrations, and an authorized test inbox. Never collect a Google password in this application.
- Postconditions: Google/manual email proof does not grant approval, roles, employee units, reservations, or borrowing access. Existing local matches require authenticated password-confirmed linking.
- Business rules: exact @cspc.edu.ph selects Employee; other verified Google-account emails select External. Manual signup requires OTP before SAO submission. Existing OTP expiry/attempt/cooldown and reset expiry remain unchanged.
- Inputs/outputs: provider credentials stay private; provider subject/email enter server-side session; application shows locked classification/email, concise feedback, and SAO-pending status. Evidence records only non-secret outcomes.
- Database impact: no new migration planned. Verify existing migrations and critical auth/review behavior in an owned disposable MariaDB database; preserve the existing SQLite preview and original Docker database.
- States: manual unverified → OTP verified → pending SAO; Google verified → completed registration → pending SAO. Failure/expiry retains existing access restrictions. Reset changes only the local password and consumes its token.
- Failure scenarios: missing or wrong credentials, callback mismatch, denied Google consent, unverified Google email, duplicate identity, email transport rejection, expired/incorrect OTP, duplicate submission, unauthorized review, and expired/reused reset tokens.
- Dependencies/risks: private .env.docker contains provider values but the updated preview does not load that file. Its callback uses localhost:8080 while the preview uses 127.0.0.1:8765. The Google Console must authorize the intended exact callback. Presence of credentials is not validity or inbox proof.

## Increment and acceptance criteria

1. Snapshot affected sources and protected reference hashes; write failing acceptance tests before changes.
2. Render each verification error once, retain accessible field validation, and use email/password fallback wording for both borrower types.
3. Reuse only existing provider keys in private runtime configuration, preserving data and application keys. Do not print secrets or alter the original Docker environment.
4. Inspect non-secret configuration checks; prove the actual Google authorization/callback with an owner-controlled test account. Mocked callback tests do not count as live proof.
5. Send only to a confirmed test inbox; record SMTP acceptance separately from inbox receipt, manual OTP verification, SAO queue submission, and password-reset completion. Never log OTP/reset secrets.
6. Run focused acceptance/regression tests on SQLite and MariaDB 11.4, verify server version, migration compatibility, uniqueness and rollback, and inspect the updated pages.
7. Update the register with exact evidence and any remaining owner/provider actions. No full live gate passes while callback/inbox/reset evidence is missing.

## Progress register

| Task | Status | Evidence/dependency | Next action |
| --- | --- | --- | --- |
| Duplicate OTP feedback and neutral Google fallback | VERIFIED COMPLETE | Six new acceptance cases pass on both engines; actual browser invalid-code feedback renders once | Retain regression coverage |
| Private runtime provider configuration | VERIFIED COMPLETE | Existing Google/Gmail keys reused in ignored .env; application configuration checks and actual SMTP authentication pass | Owner authorizes exact callback in Google Console |
| Actual Google registration/linking | BLOCKED | Real Google authorization endpoint returned Error 400 redirect_uri_mismatch; Console requires owner sign-in | Add exact callback, then owner-controlled employee/external and linking tests |
| Actual OTP and password-reset delivery | BLOCKED | Real SMTP connection/authentication accepted; no message sent; test inbox still unconfirmed | Confirm recipient, verify inbox and complete actual flows |
| MariaDB 11.4 verification | VERIFIED COMPLETE | 11.4.12 server; 71 migrations, review-submission column and identity uniqueness constraints verified; 175 regression + six new cases pass | Preserve evidence; no original database reset |
| Deployment/ICTU gate | BLOCKED | Separate institutional/hosting/quality evidence remains outstanding | Continue existing deployment checklist |

Evidence directory: project workspace `sprint1/auth-live01`. No production reset, reseed, provider purchase, DNS change, commit, push, or deployment is authorized by this increment.

## Changes and verification evidence

- Existing OTP controller/view now distinguish delivery failure from input failure. Delivery shows one card alert; email/code errors show one accessible field alert with aria-invalid and aria-describedby. The shared layout suppresses its duplicate notices when the page supplies its own. Existing colors, controls and layout remain.
- All five Google fallback messages now say email/password, accommodating both employee and external accounts.
- Added `spmu:auth-readiness --check-smtp`: explicit SMTP connection/authentication probe, no email send, class-only failure diagnostics, connection cleanup and a failing gate when the probe fails. Configuration success explicitly excludes Google consent and inbox proof.
- Example configuration now requires an authorized sender instead of suggesting an unverified institutional address; it documents the visible Google option and confirmed employee domain. The actual private .env reused existing provider keys; no secret was printed or committed.
- The private preview preserves its existing SQLite database and demonstration key. It is not a production .env or evidence of a deployed MariaDB runtime. Its database was backed up consistently before integration.

| Check | Result | Evidence |
| --- | --- | --- |
| Scoped SQLite auth/review regression | 175/175 passed, 1333 assertions | `sqlite-auth-regression.txt` |
| Scoped MariaDB auth/review regression | 175/175 passed, 1333 assertions | `mariadb-auth-regression.txt` |
| New feedback/probe acceptance, SQLite | 6/6 passed, 38 assertions | `feedback-final-sqlite.txt` |
| New feedback/probe acceptance, MariaDB | 6/6 passed, 38 assertions | `feedback-final-mariadb.txt` |
| Frontend regression | 190 passed, zero failures, one inherited skip | `frontend.txt` |
| Actual provider configuration/SMTP | Checks pass; SMTP accepted; NO EMAIL SENT | `readiness-with-smtp.json`, `provider-connection.json` |
| Actual Google authorization | Failed: redirect_uri_mismatch; no consent/callback proof | `google-error.png` |
| Actual OTP/reset inbox receipt | NOT TESTED | Confirmed recipient and owner inbox interaction required |
| Desktop/mobile OTP feedback | One alert; six code inputs; no overflow at 390px | Actual browser inspection; `otp-mobile.png` |
| Existing preview/source preservation | All 73 tables/schema unchanged, integrity OK, zero FK errors; three synced reference hashes and original Docker environment unchanged | `preservation.json` |
| Syntax/diff checks | Four affected PHP files lint clean; git diff --check clean | Recorded command output |
| Main preview | HTTP 200 at http://127.0.0.1:8765/login | Fresh no-proxy HTTP check |

The two six-case runs are disjoint from the 175-case regression scope. These are focused authentication checks, not a claim that the entire backend suite, production hosting, all ICTU deliverables or operational KPIs passed. Initial rendering trials were superseded after correcting test-session assumptions and using the supported TestView string rendering API; only the final receipts above count as acceptance evidence.

## Gate and exact next action

Code/MariaDB gate: PASS. Full S1-AUTH-LIVE-01 gate: NOT READY. Google Console must authorize `http://127.0.0.1:8765/auth/google/callback` on the existing Web client, retaining its existing URIs. The prepared Console tab requires the owner to sign in privately. Confirm a real test inbox before sending any application test email. Then verify actual Google onboarding/linking, OTP receipt → verification → SAO submission, and reset receipt → single-use local password change. Complete these in this same authorized increment before another workflow milestone. The owner performs the final browser password-change submission.
