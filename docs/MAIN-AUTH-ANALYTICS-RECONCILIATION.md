# S1-MAIN-AUTH-ANALYTICS-01

Status FOR REVIEW. Code integration gate PASS; live-provider and deployment gates NOT READY until the remaining acceptance evidence is collected.

## Objective and confirmed requirements

Resolve Google, password sign-in, OTP and password-reset configuration failures, use the pulled-main analytics layout, and run with a fresh MariaDB database initialized from main's reference/demo seeders. The user clarified that their final registration behavior stays: Google-verified identity → locked employee/external registration → SAO review; manual registration → OTP → SAO review. Password fallback and authenticated-owner linking remain. Descriptive analytics and actual scheduled demand only; open return days are clearly labelled. Other operational approval, custody, cancellation and evidence rules remain unchanged.

Pulled main is `b1fdfaafdfe08103d8b8217e8209b6219e1cdbe2`. The existing merged checkpoint is `30f53aba76f188a1723481a20fb796ba21e3ff41`, preserved on `backup/before-main-module-switch-20261014` and in the verified external Git bundle.

## Findings and proposed implementation

- Main's existing-user-only Google callback and institutional-only OTP disagree with the retained registration flow. Laravel Socialite, SMTP and password-broker core logic are shared already; wholesale replacement would remove approved behavior without fixing provider configuration.
- The native preview listens on 8766 while its configured Google callback points at 8765. The existing Docker configuration uses a LAN application address and a localhost callback. Align the application address, OAuth start origin, callback and browser session. Google's registered callback must match exactly.
- Main offers four visible descriptive sections but still exposes forecast detail handlers. Restore its layout and descriptive stock/release-history presentation while retaining the scheduled-demand section, saved external affiliations and blocked forecasting paths.
- Create a separate persistent MariaDB 11.4 instance/volume. Main's 72 migrations and seeders are the baseline; four additive registration/affiliation/OAuth migrations are required by the retained modules. Start with fresh rows; no local transaction or identity records are imported.
- Public multi-device authentication requires a reachable HTTPS domain and the matching provider callback. Localhost resolves to each device itself. Prepare the deployment configuration and guide; do not claim public/Google/inbox success from local tests.

## Actors, inputs, states and database impact

Borrowers authenticate and prove mailbox ownership; SAO reviews self-registration; Admin/Head retains operational decision authority; ICTU handles technical configuration. Google handles its own credentials; the application does not collect Google passwords. Inputs: verified Google identity or local credentials, OTP/reset token, private server provider configuration and existing master seeders. Outputs: valid application sessions, locked verified registration, OTP/reset transport status, descriptive analytics and fresh reference/demo data. Google identity never grants an operational role or SAO approval.

Existing data and volumes remain recoverable. New database/storage and application key are isolated. Migration/one-time seeding affects the new database only. State transitions retain pending email → pending SAO review → approved access. Failed SMTP invalidates the OTP; failed OAuth must create no identity, session approval or inventory allocation. Repeated initialization must not reseed an in-use database.

## Acceptance and failure scenarios

Verify normal login, verified Google onboarding, denied/expired/mismatched OAuth state, host/callback consistency, locked email/type, safe linking, wrong/expired/replayed OTP, resend limits, SMTP failure, reset-token ownership/expiration, inactive and pending-account denial. Check approved analytics scope, period/actor filters, external affiliation, scheduled-date boundaries, no forecasting through detail URLs, and main layout rendering. Verify fresh MariaDB version/schema/seed provenance and actual application endpoints; preserve the original database/config/evidence. Use isolated QA databases and array transport for automated cases; SMTP connection acceptance and real mailbox receipt are separate evidence.

## Dependencies and outstanding gates

Private Google/SMTP credentials exist, but the credentials pasted into chat require provider rotation before deployment. No secret is recorded in this document or Git. Real Google consent/callback and inbox receipt require external/user verification. Hosting choice, trusted-proxy configuration, HTTPS, persistent storage/queues/scheduler, backup restoration, ISO evaluation and ICTU approval remain deployment requirements.

## Implemented changes and requirement traceability

| Requirement | Implementation | Verification |
|---|---|---|
| Keep approved Google/manual registration, SAO authority and safe linking | Existing onboarding, OTP and reset controllers retained; Google start validates callback configuration and redirects aliases before creating state | GoogleCanonicalOriginTest, GoogleAuthTest, GoogleRegistrationOnboardingTest, AuthMailDeliveryTest, BorrowerSelfRegistrationTest |
| Generate correct reset/OAuth links across aliases and hosting proxies | AppServiceProvider uses configured URL/scheme; explicit TRUSTED_PROXIES support; corrected deployment examples | Canonical-origin acceptance, real HTTP callback parameter, actual Google sign-in page handoff |
| Main analytics layout; descriptive and scheduled only | Three main presentation partials restored; recorded-stock/release-history API and details restored; forecasting runtime/detail paths remain unavailable | All Analytics feature cases and DescriptiveScheduledAnalyticsTest |
| Filters must respect the selected borrower and truthful external affiliation | Stock history follows borrower identity; external snapshots cannot be grouped into a CSPC division/unit even with inconsistent legacy fields | New stock-history fixture: all releases 5, CSPC-unit releases 3, selected external releases 2; stock stays institution-wide |
| Fresh main database without local data import | Separate MariaDB database/storage; 72 main migrations plus 4 retained-module compatibility migrations; main reference/demo seeder baseline | Actual runtime proof; 76 migrated rows; idempotent bootstrap; fresh borrowing requests and OAuth identities both zero |
| Preserve prior code/data | Backup branch and Git bundle; old Docker database/storage retained; SQL dump plus storage archive captured before cutover | Dump completion/footer, archive integrity and SHA-256 receipts; a full restore rehearsal remains outstanding |

The stock-history filter acceptance initially failed on the restored main implementation (5 releases appeared under a CSPC unit instead of 3). It passes after the filter correction. The initial eleven analytics failures were traced to expectations that classified the descriptive Coverage Detail card/API as predictive; the replacement expectations assert recorded quantities and continue rejecting actual forecast URLs and computed future coverage/rates.

## Exact current checks

| Check | Result | Evidence in the local project workspace |
|---|---|---|
| Final SQLite affected regression | 406 passed, 0 failed/errors, 2,896 assertions | `sprint1/merge-incoming/main-auth-analytics-final2-sqlite.txt` and events |
| Final MariaDB 11.4 affected regression | 406 passed, 0 failed/errors, 2,896 assertions | `sprint1/main-baseline01/main-auth-analytics-final2-mariadb.txt` and events |
| Frontend suite | 193 cases: 192 passed, 0 failed, 1 existing optional skip | `sprint1/main-baseline01/frontend-01.txt` |
| Actual fresh Docker HTTP | 29 checks passed; includes login, five analytics sections, canonical OAuth parameters, blocked forecast report and repeated authenticated requests | `sprint1/main-baseline01/http-smoke.json` |
| Changed PHP/Blade files and new origin test | 14 syntax checks passed; Git whitespace check passed | Current source verification |
| Docker build and live runtime | PHP 8.4.26, Laravel 13.35.0, Socialite 5.31.0, MariaDB 11.4.12 | Build receipt and `runtime-proof-final.json` |
| SMTP probe | Connection/authentication accepted; probe sent no message | `sprint1/main-baseline01/smtp-readiness.txt` |
| Actual OTP mailbox/verification | User confirmed receipt and successful verification; saved email verified, OTP consumed, submitted for SAO review, registration PENDING, allocations zero | `live-otp-send.txt`, `live-mail-status.json` and user confirmation on 14 October 2026 |
| Actual forgot-password request | Current HTTP controller accepted request, normal response rendered, reset token saved, pending SAO status retained, allocations zero. User confirmed email receipt; opening its localhost link on a phone returned ERR_CONNECTION_REFUSED. Laptop reset page returned HTTP 200 and correct submit address; token was about 7 minutes old against a 60-minute lifetime, with no completed reset audit. Password change pending. | `live-reset-send.json`, `reset-diagnostics.json` and user confirmation |
| Actual Google start | Browser reached Google's SPMU-ACPMP sign-in page without the reported redirect mismatch; account consent/callback still unverified | `sprint1/main-baseline01/google-signin-start.png` |
| Actual app UI | Admin demo signed in; restored Inventory Health visually inspected | `sprint1/main-baseline01/inventory-main-layout.png` |
| Session configuration | Inherited one-minute QA setting restored to the supplied 15-minute value; 29 HTTP checks passed again | Protected configuration and current runtime |

These are affected-module checks, not a new certification of the entire system. Test execution duration is not an operational KPI measurement. Main seed inventory quantities are reference/demo values; institutional opening stock still requires reconciliation before production.

## Progress register

| ID | Description | Status | Dependency / next action |
|---|---|---|---|
| MAIN-AUTH-CODE | Google origin, registration compatibility, password/OTP fallback | VERIFIED COMPLETE | Current automated and actual HTTP evidence above |
| MAIN-ANALYTICS | Main layout, descriptive stock history, scheduled demand, filter protection | VERIFIED COMPLETE | Current analytics acceptance and UI evidence |
| MAIN-DB | Separate fresh MariaDB and one-time baseline initialization | VERIFIED COMPLETE | Old data not imported; reconcile production opening inventory separately |
| MAIN-RUNTIME | Fresh application, queue and scheduler; shared database sessions | VERIFIED COMPLETE | Local review runtime only; public HTTPS acceptance remains |
| MAIN-BACKUP | Original SQL/storage backup and archive integrity | FOR REVIEW | Perform a separate restore rehearsal before deployment |
| AUTH-LIVE-OTP | Actual authorized-recipient OTP receipt and verification | VERIFIED COMPLETE | User receipt plus database/audit evidence; no SAO approval or inventory allocation |
| AUTH-LIVE-RESET | Actual authorized-recipient forgot-password testing | IN PROGRESS | Email receipt confirmed; complete the current localhost reset link on the laptop hosting the QA server. Phone acceptance requires a reachable hostname first. |
| AUTH-LIVE-GOOGLE | Actual identity consent, callback, locked onboarding and linking | IN PROGRESS | User/account owner completes Google sign-in; record resulting callback/registration |
| PUBLIC-AUTH | Second-device HTTPS authentication and email links | NOT STARTED | Approved hosting/domain, provider callbacks, trusted proxies and rotated credentials |
| DEPLOYMENT | ICTU deliverables, restoration/security review, ISO evaluation | NOT STARTED for this increment | Carry forward the existing project gate register; do not equate these auth tests with institutional approval |

## Local run and recovery

Main fresh system: `http://localhost:8080/login`. The existing local `sprint1/merge-incoming/RUN-SPMU.cmd` now starts the owned fresh stack rather than the old SQLite preview. Restarting it never resets the database. The protected configuration stays outside Git. After source/configuration edits, the owned `sprint1/main-baseline01/refresh-fresh-stack.ps1` rebuilds/recreates only application/worker containers and keeps all data volumes. The old Compose stack must not also claim port 8080. Its original app is stopped; its database and storage remain intact.

Live mail tests use the same current application image on `http://localhost:8081`, a separate QA schema and clearly marked synthetic pending external account. They do not create a real borrower account, CSPC assignment or approval in the fresh main database. Do not rerun destructive test-schema initialization while this user-assisted mail test is pending. The user enters OTP/password only in the test application's pages; neither belongs in chat or Git.

The user's phone reset attempt established a network-address limitation: localhost on the phone addresses the phone, not the laptop. No password-reset submission reached the application during that failed navigation. The local reset email must be opened on the laptop. For multi-device acceptance, use an approved reachable HTTPS hostname as APP_URL, refresh server configuration and issue a new reset email. Google also needs that hostname's exact callback registered with its provider. Changing app logic or sending more localhost reset emails does not address the phone's connection failure.

Public setup and remaining provider acceptance steps are in [AUTH-PUBLIC-DEPLOYMENT.md](AUTH-PUBLIC-DEPLOYMENT.md).
