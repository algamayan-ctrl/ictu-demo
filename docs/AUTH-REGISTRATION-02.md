# Authentication and registration increment

Task S1-AUTH-REG-02. Status FOR REVIEW. Source: user instructions dated 13 October 2026, approved existing employee/external registration policy, manuscript XP/BPR methodology, and the supplied red-rectangle guide. This increment extends existing Laravel/Socialite registration; it does not replace authentication or change approval authority.

## Requirements before implementation

Objective: actual Google identity → applicable registration → SAO review; manual registration → emailed OTP → SAO review. Password reset uses the existing Laravel password broker and a real configured email transport. Google handles its own password entry. Existing account linking requires a signed-in local owner and current local password.

Actors: guests create Borrower applications only; SAO reviews verified applications; Admin retains existing user administration; Google verifies provider identity and email without granting local privileges. User stories: a new Google user completes a locked-email form; a manual applicant verifies mailbox ownership before appearing in SAO's queue; a registered user recovers a password through an expiring reset link.

Preconditions: Google credentials and exact callback URI for provider flow; a supported real mail transport for OTP/reset delivery; an active employee organizational unit or a readable private external Valid ID. Postconditions: one saved application, no Borrower role or employee unit grants before SAO approval, immutable provider subject bound to one local user, and one submission notification after email verification.

Confirmed rules: @cspc.edu.ph Google identities use employee fields; other verified Google-account addresses use external fields. Yahoo/government addresses work only if registered as Google accounts. Google registration may skip redundant OTP (user confirmed); manual registration requires OTP. Neither route approves borrowing access. CSPC email ownership does not prove employee eligibility. External records never receive fabricated CSPC assignments. Existing local emails are never automatically linked by email match.

Inputs/outputs: provider-verified email/subject stored only in the server session with a short expiry, then completed identity/contact/company/ID or employee/unit fields and a local fallback password. Client-supplied email/type/provider claims cannot override Google identity. Manual input remains editable before submission. UI displays a single account-type selector and concise OTP instructions.

Database impact: add nullable submitted_for_review_at to borrower_registrations; reuse users, oauth_identities, private stored_files, email_verification_otps, audit and notification tables. Backfill only previously verified historical applications to avoid resending old notifications. No original business records are reset.

States: manual saved/unverified → verified and submitted for review → SAO approved/rejected. Google callback → expiring onboarding context (no user yet) → verified application submitted for review → SAO decision. Existing linked sign-in stays subject-based. Expired/cancelled OAuth does not create a user. Duplicate submissions, identity collisions, failed uploads/audit writes roll back the entire registration and new private bytes.

Failure/acceptance coverage: verified employee/external classification; locked email/type tampering; missing/expired context; unverified provider identity; no automatic existing-email linking; duplicate subject rollback; no premature roles/grants; manual applications hidden and forbidden to review before OTP; notification exactly once after verification; OTP expiry/attempt limits/resend and transport failure; password reset expiry and authorization regression. Verify desktop/mobile layouts and both SQLite/MariaDB where applicable.

Dependencies/risks: Google OAuth client and email provider are not configured in the current protected environment. Live delivery/consent remain BLOCKED until the owner configures them. Automated provider/mail tests do not prove real inbox receipt. Hosting readiness and the original nine ICTU deliverables remain separate deployment gates; missing institutional source documents cannot be certified.

## Marked document register

- Google button and centered single Register as selector: VERIFIED COMPLETE for tested rendering. Desktop employee and mobile external forms inspected; locked Google email/type covered by automated callbacks.
- Compact request/SAO records and supported status filtering: FOR REVIEW. Code implemented; Admin status choices inspected. Unverified pending applications are excluded from SAO; reviewed history remains visible. Submitted date uses the actual review-submission timestamp when available.
- Separate on-campus/off-campus eligibility filter: VERIFIED COMPLETE in the Admin demo browser. Filtering the 26-item catalogue to off-campus eligibility returned one permitted item. This changes display filtering only.
- Predictive analytics removal: VERIFIED COMPLETE in scoped automated assertions; inherited removal preserved. No new prediction code was added.
- Analytics paired-card alignment: FOR REVIEW. Equal-width/stretched pairs implemented; final analytics browser inspection was interrupted when the browser connection changed. Compact report controls inspected at 44px, including date fields and the filter action.
- Reports display actual filtered records on initial load: VERIFIED COMPLETE in scoped tests and the Admin demo browser. Initial load displayed seven existing demo records with saved external-company fields; this is not an operational KPI measurement.

## Progress and verification register

| Task | Status | Evidence | Outstanding dependency and next action |
| --- | --- | --- | --- |
| Google identity onboarding and locked classification | FOR REVIEW | 210/210 scoped SQLite cases, including new onboarding, linking, tampering, pending access and rollback cases | MariaDB retest and real Google client/consent |
| Manual registration OTP before SAO review | FOR REVIEW | Wrong/correct/repeated OTP, hidden unverified queue, approval denial, one notification after verification | Real SMTP delivery/inbox acceptance and MariaDB |
| OTP/reset transport honesty and recovery | VERIFIED COMPLETE for tested behavior | Fake/log transports refused outside tests; Laravel reset token replay/expiry and neutral unknown-account cases | Live provider test is a separate blocked gate |
| Provider setup guide and non-secret readiness command | VERIFIED COMPLETE | docs/GOOGLE-EMAIL-SETUP.md; spmu:auth-readiness; no credentials exposed or provider accounts created | Owner selected setup guide only because no provider is available |
| MariaDB 11.4 verification | BLOCKED | Docker Desktop startup attempted; engine pipe remained unavailable | Restore the local Docker engine and run the same isolated cases |
| Live Google/OTP/password-reset verification | BLOCKED | Configuration check reports absent credentials and non-delivery mail transport | Configure owner-managed providers privately, then verify consent and actual inbox receipt |

Final scoped backend receipt: workspace sprint1/auth-registration02-final-sqlite4.txt, 210 passed, zero failures, 1525 assertions. Subsequent UI-copy/timestamp changes passed 66 focused cases with 576 assertions (auth-registration02-final-rendering.txt). Google account-chooser parameters and related onboarding/linking passed 56 cases with 466 assertions (auth-registration02-final-google-picker.txt). These cases overlap; do not add them to a whole-suite total. Frontend: 190 passed, zero failures, one existing optional skip. Vite build passed. Final scan: 24 changed PHP files linted successfully and zero source files removed. git diff --check passed.

The verification harness now explicitly sets APP_BASE_PATH to the selected isolated checkout. Before this fix, a shared dependency junction caused it to infer another verification folder for views/migrations; mixed-folder trial receipts are superseded by the scoped final receipts. Tests use a separate in-memory SQLite database and fixture storage; no live environment file or production cached configuration is loaded.

The preview remains the existing local SQLite demonstration database, not the target production MariaDB environment. An additive migration was applied only after a consistent backup. All original business fields in 12 checked tables were preserved, excluding the new submission timestamp and normal demo sign-in metadata/audit. The supplied DOCX hash was unchanged. No reset, destructive migration, commit, push, or deployment was performed.

Gate decision: NOT READY for live/deployment. Scoped automated checks pass; missing provider setup, fresh MariaDB evidence, all-role visual completion, and the existing hosting/ICTU gates remain explicit. Next authorized step: S1-AUTH-LIVE-01, owner-managed Google/email configuration followed by real employee/external consent, manual OTP, password-reset receipt, and isolated MariaDB verification.
