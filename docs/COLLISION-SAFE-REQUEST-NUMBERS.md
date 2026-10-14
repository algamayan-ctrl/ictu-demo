# S1-REQ-01A — collision-safe borrowing request numbers

Status: VERIFIED COMPLETE. Selected technical gate: PASS,13 October2026. Full S1-ROUTE-02 remains IN PROGRESS; deployment remains NOT READY.

## Pre-implementation requirement and design

Objective: allow two distinct legitimate borrowing requests from the same eligible Borrower in the same second, without losing either request or changing existing identifiers/evidence. This is an XP defect increment supporting the supplied workflow's draft/submission steps and manuscript's reliable borrowing records. Neither source establishes a new institutional serial-number policy; the suffix below is a technical uniqueness mechanism, not an official sequential numbering rule.

Confirmed actors/permissions: existing approved active employee/external Borrower eligibility and workspace authorization; existing employee-unit restrictions and reviewed external affiliation; on-campus submission directly to Head/Admin and off-campus to SAO. SAO and Admin authority is unchanged. Draft/submission never reserves stock, creates custody, or generates the approval packet.

Stories: an eligible Borrower can save two different drafts or signed requests at one frozen second and receive distinct usable references. A failed create leaves previous requests and private evidence intact and can be retried after the cause is corrected. Repeating a completed action token still creates one record under existing duplicate-submission middleware.

Preconditions: current validated fields/items/dates, eligible actor, authorized employee unit or reviewed external company; submitted requests also require consent, registered E-signature and applicable readable letter/PTC. Postconditions: successful creates each have one header, v1/items and their own applicable evidence, audit and existing reviewer step/notice; failed creates leave no new rows/files. Previous identifiers/history remain unchanged.

Existing code: BorrowingRequestController::store creates `BR-{YmdHis}-{borrower ID}` inside an outer transaction. The existing `VARCHAR(50)` unique column rejects a second same-second create. The middleware fingerprints distinct payloads separately or uses the existing explicit action token. Existing code consumers treat the number as opaque display/search/document text; the reviewed active code has no timestamp/user parser.

Proposed implementation: retain the readable timestamp/Borrower prefix and append ten uppercase random alphanumeric characters for new records. A small service inserts the header with the existing unique constraint as arbiter, retrying only a request-number unique violation, at most five candidates. Each candidate insertion uses a nested transaction/savepoint; do not retry unrelated write/foreign-key failures or the whole file/signature workflow. Candidate insertion precedes versions/uploads/signing. Even a 20-digit unsigned Borrower ID gives a 49-character number, within the existing 50-character column. No existence-check race, schema/dependency migration, counter table, historical renumbering, or institutional sequence claim.

Inputs/outputs: existing validated request input and actor; output is the existing redirect and a distinct stored request number. Database impact: new header values only; existing schema, IDs, relationships and unique index retained. State transitions remain new DRAFT → existing UNDER_SPMU when explicitly signed/submitted; collision retries do not create versions, status history, audits, notices or inventory allocations.

Failure scenarios: forced candidate collision/exhaustion; foreign-key/unrelated database failure; late supporting-file metadata, signature or submission-audit failure; missing/corrupt upload or consent; unauthorized/ineligible actor; repeated completed action; concurrent independent MariaDB creates. Failed writes must preserve all pre-existing row contents and private SHA-256 hashes, remove only newly created private paths, and allow a corrected retry. No new durable mail/outbox guarantee is implied.

Acceptance: failing actual-endpoint same-second cases before the fix; distinct same-second drafts/submissions for employee/external actors on SQLite/MariaDB; forced number-collision recovery and bounded exhaustion; unrelated failure not retried; unchanged middleware/authority/affiliation; zero premature allocation/custody/output; real independent MariaDB workers; late-write rollback and intact old evidence; appropriate retained regressions; scoped quality checks and working/test/preview source comparison. Preserve the existing preview database and all synced reference/private bytes. Record actual counts/logs before PASS.

Dependencies/risks: verified PHP 8.4/Laravel 13 and existing MariaDB 11.4 runtime; cryptographic randomness and database uniqueness. All five collisions fail safely rather than silently overwrite a request. Identifier consumers are reviewed for the longer value; UI/form layout is not redesigned. Broad idempotency, sequential institutional numbering, approval/physical issuance, pending operating-hour conflict, missing ICTU/hosting attachments and deployment certification remain outside this increment.

## Requirement → implementation → acceptance trace

| Requirement | Implementation | Automated acceptance in BorrowingRequestNumberTest |
|---|---|---|
| Two different legitimate same-second creates | New-number suffix and unique header insert | Eight Employee/External × Draft/Submitted × On/Off-campus cases; both saved and visible |
| No guessed sequence or historical renumbering | Existing readable prefix; existing records never updated | Original rows and private SHA-256 hashes retained during recovery/failed writes; native legacy-format record retained |
| Database arbitrates candidate collisions | Existing unique index, nested savepoint, maximum five candidates | Forced collision recovers once without duplicated versions/signatures; exhaustion leaves state unchanged |
| Do not retry unrelated failures | Exact Laravel unique index/column metadata guard | Real foreign-key and primary-key violations propagate after one candidate |
| Keep employee/external authorization and queues | Existing validation/affiliation/workflow and middleware retained | Saved employee unit or reviewed external company/null units; existing SAO/Head entry step; retained related role/invalid-evidence regression |
| Atomic failed create and corrected retry | Existing outer store transaction and ProtectedFileService callbacks | Late metadata and submission-audit failures restore exact old rows/files; same action token succeeds after correction |
| No premature reservation or output | No inventory/approval/timing modification | Zero allocations/custody/generated documents after distinct creates, collision recovery and failed/retried writes |
| Protect completed actions | Existing duplicate-submission middleware | Completed explicit action token remains exactly one request |
| Fit existing schema | Ten-character uppercase alphanumeric suffix | Maximum unsigned Borrower ID produces49 characters within VARCHAR(50) |

Available synced DOCX sources were checked through matching-hash paragraph extractions (`source-extraction-proof.json` / `numbering-source-search.json`); no request/transaction serial-format rule was found in that search. Existing form/manual examples are not substituted for a confirmed institutional serial policy.

## Verification evidence

Workspace evidence folder: `sprint1/req01a/`; baseline captures current application/source, preview database and private/reference hashes before code changes. The corrected pre-change endpoint run (`req01a-corrected-red-sqlite.txt`) failed all eight same-second cases with a request-number unique violation/HTTP500. The first red/focused logs are retained separately: fake uploaded-file objects exposed a public stream resource that invalidated the existing middleware's JSON fingerprint, so signed fixture attempts were falsely deduplicated. Ordinary UploadedFile fixtures corrected the test setup; no middleware/cache bypass or production deduplication rewrite was made.

Final focused `req01a-focused-sqlite.txt` and `req01a-focused-mariadb.txt`: **16/16 tests and471 assertions on each engine**. Cases cover eight actual create combinations, repeated action token, forced unique recovery, five-candidate exhaustion, actual foreign-key/primary-key failures without retry, 50-character schema boundary, and two late failed writes with corrected same-token retry and preserved old row/file hashes. Related `req01a-regression-sqlite.txt` and `req01a-regression-mariadb.txt`: **515/515,5683 assertions on each engine**, including all retained prior affected classes and the final C-03 exact-version signature cases. No exclusions/skips/risky tests; this is the selected31-class regression scope, not the entire repository suite.

Only the existing controller's create call/import changed; added one39-line service and one16-case feature class. Existing file rollback, workflow, schema, roles, UI/Bootstrap assets and dependency files were retained. New service/test pass Pint; the old controller has the exact same inherited ten-fixer formatting list before/after. Whole-controller formatting is not claimed clean and was not rewritten. PHP syntax and whitespace checks pass. Final comparison confirms**617 matching working/test/preview code/public/config files** and**162 byte-identical preview database/private/reference files**, including all3 synced references. The five Docker source files are preserved in the working repo and intentionally absent from host-PHP clones.

Real independent workers used a second empty owned MariaDB11.4.12 database with the existing REPEATABLE-READ isolation, one Borrower and a frozen `2026-10-13T09:00:00+08:00`. Both first candidates were deliberately identical. Worker two saved `BR-20261013090000-5-SHARED0001` in one attempt; worker one encountered the real unique conflict and saved `BR-20261013090000-5-TTA2UURXFU` in two attempts. Both submitted through the actual controller/workflow transaction. A retained legacy-format signed request remained unchanged. Native late private-metadata and signature/submission-audit failures restored exact old rows/file hashes; corrected retry succeeded. Final four requests are signed v1 with truthful external company/null internal units and SAO entry, **zero allocation/custody/operational output**,9 private files with matching metadata/hash and0 orphans. `native-acceptance.json` records the proof. Feature tests exercise HTTP middleware; native workers exercise independent real database transactions, without claiming shared production-cache concurrency or a durable SMTP guarantee.

Both exact labelled disposable containers/anonymous volumes were removed (`cleanup.json`); existing app/database containers remain healthy. Only the owned empty test schemas were initialized/migrated/seeded. No working/live migration/reset, old preview request/evidence rewrite, dependency change, institution-policy change, real message, commit/push or deployment. The preview source is updated, the existing database is byte-identical, HTTP200/PID16520 serves `http://127.0.0.1:8765/login`, and the working repo was reopened in VS Code. This local preview still uses SQLite and array mail.

Supporting receipts: `baseline.json`, corrected red/focused/regression logs in workspace sprint1/, `controller-change.diff`, `code-review.json`, `quality.json`, `source-manifest.json`, `preserved-final.json`, `native-setup.json`, both worker/rollback/inspection receipts, `cleanup.json`, `preview.json` and final `acceptance.json`.

## Completion and next increment

- [x] Requirements/actors/state/failure and data-impact map before application edits.
- [x] Reproduced actual number collision and retained fixture correction history.
- [x] Small insert-only numbering fix; unchanged old numbers, schema and authority.
- [x] Both-engine focused/related acceptance, independent forced collision and late-write rollback.
- [x] Preserved all previous preview/reference/private bytes, reviewed scope/style limits, updated local source and removed owned test containers.
- [x] Progress/step instructions and verification receipts updated.

Gate PASS for S1-REQ-01A. Recommended next small task: **S1-REG-03 — external Admin account-edit compatibility**, first mapping editable fields, reviewed-registration preservation and reverification rules; do not invent employee/unit assignments or approval transitions. The operating-window/form conflict, cancellation, analytics, Google-domain decisions and missing institutional deployment evidence remain separate pending increments.
