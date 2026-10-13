# S1-REG-01: Borrower self-registration and SAO review

## Objective and source mapping

Allow a CSPC employee or external borrower to apply for a Borrower account. SAO reviews the application before protected system access. This implements the opening registration paragraphs of the supplied `Wofklow FINAL (AutoRecovered).docx` and the user's confirmed decision that SAO approves self-registration, Admin can provision operational accounts, and walk-ins are excluded.

The source's later walk-in and predictive-analytics passages conflict with the user's explicit exclusions. They are not implemented by this increment. Hosting-form fields are not infrastructure requirements. Missing hosting/ICTU attachments and the reported TD RSC title mismatch remain institutional-evidence blockers.

The following are implementation safeguards, rather than newly invented institutional eligibility rules: the existing 12-character password standard, duplicate constraints, upload type/size checks, private storage, transactions, locked review decisions, required rejection remarks, and separate mailbox/approval/account-status gates. External email syntax is validated without treating its domain as proof of institutional affiliation. Employee registration uses the existing configured institutional-email allowlist; Google/government-domain policy is unchanged.

## Actors, permissions, and stories

| Actor | Permitted action | Boundaries |
|---|---|---|
| Guest employee | Submit employee application; prove mailbox ownership | Cannot select staff roles, approval status, or reviewer |
| Guest external borrower | Submit company/agency and Valid ID; prove mailbox ownership | No fabricated employee number or CSPC unit |
| Active, mailbox-verified SAO with SPMU role | View review queue/IDs; approve or reject Pending applications | Decision is final in this increment; rejection requires remarks |
| Approved, verified, active borrower | Sign in and access existing Borrower workspace; view own protected ID | Other applicants' IDs are forbidden |
| Admin / ICTU | Existing account-provisioning and maintenance permissions | Cannot approve self-registration or view registration IDs through a blanket maintenance permission |

Stories: an applicant submits once and receives Pending status; SAO receives a review notification and checks the actual fields/ID; SAO records one actor-attributed decision; the borrower receives a decision notification and may sign in only after both approval and mailbox proof. Existing provisioned accounts without a self-registration row retain established behavior.

## Inputs, outputs, and states

Employee inputs: employee number, full name, designation, institutional email, division, active office/college/unit within that division, mobile number, password and confirmation. External inputs: full name, valid email, mobile number, company/agency/institution, readable PDF/JPG/PNG Valid ID, password and confirmation. Hidden fields for the other applicant type are disabled in the browser and prohibited server-side.

The configured `max_upload_mb` limit is used, bounded to the existing protected-file range of 1–64 MB with the existing 5 MB fallback. Actual server/PHP/reverse-proxy upload limits still need deployment alignment. Files use generated storage names, MIME and extension validation, PDF/image readability checks, stored integrity hashes, and protected file authorization; this is not antivirus scanning or a document-authenticity guarantee.

Preconditions: guest session, unique email and employee number where applicable, valid type-specific fields, available storage and database. Review additionally requires an authorized SAO and a still-Pending application. Approval requires an active Borrower role and, for an employee, an active organizational assignment; an external ID must still exist and match its stored hash.

Postconditions: user, application, file metadata, and submission audit are committed together; no role/unit permission is granted before review. A successful review commits its status, reviewer, timestamp, remarks, grants, and audit together. Validation/authorization or audit failures leave the decision unchanged. Failed email transport preserves the saved application/decision and records failure evidence; it does not prove recipient delivery.

| Independent gate | States / transitions | Meaning |
|---|---|---|
| Self-registration | New → PENDING → APPROVED or REJECTED | SAO's approval decision; reviewed records cannot be decided twice |
| Mailbox ownership | Unverified → Verified using existing OTP, or existing supported identity proof | Never grants approval or a role |
| Operational account | Existing ACTIVE / INACTIVE / SUSPENDED behavior | Approval never reactivates a disabled account |

Pending registration is represented in `borrower_registrations.status`. `users.account_status` remains the existing operational-status field. Mailbox proof and review can occur in either order. Login, protected-route middleware, and borrowing eligibility all require approval for self-registered users. There is no automatic reapplication/reopening or destructive cleanup of reviewed applications in this increment; those need a separate approved workflow.

## Architecture and database impact

- `BorrowerRegistrationRequest` validates and normalizes public input; `BorrowerRegistrationService` handles atomic creation, private IDs, review locking, grants, audits, and notifications.
- `BorrowerRegistrationPolicy` limits review to authorized SAO. `EnsureRegistrationIsApproved` protects authenticated routes. Shared local/Google session establishment and `User::mayBorrow()` apply the approval gate.
- Public routes: GET/POST `/register` with guest middleware and throttling. Review routes: GET `/registrations`, POST `/registrations/{registration}/decision` with existing SPMU access and the narrower policy.
- New migration `2026_10_12_000001_add_borrower_self_registration.php` makes employee number/employment type nullable for genuine external accounts and adds `borrower_registrations`.
- Registration fields: unique user FK, applicant type, company, optional protected-file FK, indexed status, reviewer FK, review timestamp/remarks, timestamps. Foreign keys restrict deletion to preserve registration history. Existing email/employee-number unique constraints remain in place.
- Existing user-role/unit tables receive grants on approval. External users receive no internal unit grant. Existing audit, OTP, notification-event/delivery, and stored-file tables are reused.
- Registration ID authorization overrides general ICTU file access for this classification. SAO reviewers and the approved owner can access it; Admin, ICTU, and other borrowers cannot.
- Duplicate-submission redirects exclude password fields from flashed form input.

## Acceptance and verification traceability

Automated coverage is in `tests/Feature/BorrowerSelfRegistrationTest.php`.

| Requirement / risk | Test coverage and expected result |
|---|---|
| Two registration paths | Public form/link; employee/external saved with correct fields, Pending status, no preapproval grants |
| Honest external affiliation | Multiple external accounts with null employee/unit fields; no invented office or employee number |
| Input and uniqueness | Invalid password confirmation/phone/domain/division; structured email rejected as validation rather than a server error; duplicate email/employee number; legacy mixed-case email; privileged-field forgery rejected |
| Upload safety | Readable PDF/image accepted; missing/corrupt/executable/oversize documents rejected; missing/corrupt stored ID blocks approval |
| Atomic creation | Storage or audit failure leaves no new user/application/file metadata; owned uploaded bytes are cleaned on rollback |
| Independent gates | Actual OTP endpoint cannot approve; mocked Google callback cannot bypass Pending; approved/unverified remains a guest; verified/approved external user reaches dashboard |
| Authority and state integrity | SAO succeeds; Borrower/Admin/ICTU denied; rejection reason required; duplicate/reversal blocked; disabled accounts remain disabled |
| Private records | Reviewer and approved owner succeed; unrelated borrower/Admin/ICTU denied |
| Review failure recovery | Inactive employee unit or Borrower role blocks approval; approval audit failure rolls back decision/grants; email failure keeps decision plus failed-delivery evidence |
| History safety | Rollback with registration history refuses before destructive schema work |

Verification uses an isolated copy with no live `.env` or cached configuration. PHP 8.4.26, Laravel source 13.25.0, PHPUnit 12.5.33, SQLite and a disposable localhost-only MariaDB 11.4.12 container are used. Test data reset applies only to the owned verification database. Exact final counts, logs, source hashes, browser observations, and the gate decision are recorded in `DEVELOPMENT-PROGRESS.md`.

MariaDB upgrade rehearsal preserves an existing provisioned user row and its established approval behavior. Two independent PHP workers attempt the same registration, then two workers attempt the same approval: acceptance requires one user, one registration, one active role grant, one approval audit, and one approval-notification event. This is a two-worker check, not a load/stress certification.

Browser checks use synthetic local data and array mail. Employee submission, applicant-type switching, SAO queue/approval/history, approval-only OTP redirect, and approved/verified dashboard access are checked. The final mailbox state is supplied by an isolated fixture helper; live SMTP and a real browser OTP delivery are not claimed. Google identity tests use a mocked provider; live OAuth remains separate.

## Staging application and operational limits

1. Review the migration and take a recoverable database-plus-private-storage backup before applying to a real environment.
2. Run normal migrations in staging using the existing Laravel/Docker deployment procedure. Never use verification-only `migrate:fresh` on an existing application database.
3. Confirm migration status, the retained unique indexes/FKs, private storage, allowed employee domains, and actual upload limits.
4. Rehearse employee/external signup, OTP, SAO decision, denied access before both gates, and mailbox-delivery failure recovery with staging identities.
5. Include `storage/app/private/registration-ids` in the protected-storage backup and restore rehearsal; never publish a storage link for these IDs.
6. Rollback intentionally refuses when history or nullable external-account fields exist. Use a reviewed data-preserving recovery/forward migration; do not force deletion to make rollback succeed.

Only the disposable verification database was migrated. No working/live database migration, production deployment, source commit, or push is part of this increment.

**Later dependency resolution:** S1-REG-02 added external company snapshots and nullable request-unit references for draft creation/editing/history; see EXTERNAL-DRAFT-AFFILIATION.md. Employee-only Admin editing remains a separate S1-REG-03 task. These increments do not certify the complete external borrowing process. External E-signature/document submission and broader document/report acceptance, inventory reservation, cancellations, laundry, analytics scope, fee/sanction policy, live Google/SMTP, and institutional hosting acceptance remain separate tasks.

S1-ROUTE-01 now verifies external E-sign/document submission and current reviewer routing, including retained registration/access regression and the Valid ID private-write failure response; see EXTERNAL-SUBMISSION-ROUTING.md. The complete external approval/operational/deployment gates remain separate.
