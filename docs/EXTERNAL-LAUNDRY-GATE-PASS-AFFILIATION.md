# S1-ROUTE-02C-02 — reviewed company on active Laundry Form and Gate Pass

Status: VERIFIED COMPLETE for C-02. Gate: PASS. Full S1-ROUTE-02 remains IN PROGRESS; deployment NOT READY.

## Requirements analysis before implementation

Objective: use the C-01 saved-version affiliation resolver in the active built-in Laundry Form and Gate Pass, preserving employee output and physical-worker fields. This is the user's expressly authorized increment; later tasks remain separate gates.

Sources: user-confirmed external affiliation, E-sign and offline laundry decisions; manuscript XP/BPR iterative development; supplied workflow P164/P167/P168/P191 approval-time conditional documents; C-PLAN C-R01/C-R03/C-R05/C-R06/C-R08 and TIME-01. Keep existing artwork, form revision, labels, role permissions and conditional applicability. No institutional eligibility, penalty, opening balance or worker portal is invented.

Actors/permissions: approved borrower owns their version/documents; SAO verifies/prepares/records actual accomplished forms; Admin/Head retains final approval. External laundry worker and security guard remain physical actors with no account or fabricated E-sign. Existing private document authorization remains.

User stories: an external borrower receives a Laundry Form identifying their reviewed company and a Gate Pass with personal name separately identified from agency; an employee receives exactly the existing layout/fallback behavior; SAO records accomplished physical evidence without the company renderer altering worker/date/condition records.

Preconditions: saved version and existing final-approved custody; relevant off-campus or laundry-required positive-quantity line for real generation. Postconditions: new permitted documents show version affiliation, retain proper signatures/version/subject/private file hashes, and leave historical bytes and operational records unchanged. Existing preapproval/inapplicable-form guards still reject without artifacts.

Business rules/field contract: external Laundry Requesting Office displays resolver.display_label as printable affiliation, never a CSPC unit assignment. Gate Pass prints resolver.display_label as secondary text below the existing bearer-name block; personal name stays separate. EXTERNAL ignores later company/unit and inconsistent version office/program values. Missing reviewed external company uses the existing truthful missing-affiliation marker; a new-approval guard is C-03. Employee/Faculty/Staff/legacy null keep Laundry office → represented program → current employee unit → blank fallback and unchanged Gate Pass output. Guard release name/date/time and Laundry Issued by/Received by/completion fields remain blank for actual physical accomplishment. Saved worker/guard records must not be edited or misrepresented by generation.

Inputs/outputs: version affiliation/company and existing borrower/approval/SAO signature snapshots, item lines and custody; outputs are the existing applicable controlled PDFs with new external affiliation text. No new database field, migration, state transition, stock update or approval authority. Renderer validation occurs before superseding an existing copy, matching C-01 protection.

Exceptions/tests: normal external, later/inconsistent profile and unit, each employee fallback, unsafe/255-character accented/unbroken company, missing company, invalid loaded affiliation, absent/unreadable physical evidence, wrong owner, preapproval/inapplicable form, existing historical hashes/signatures/audits/notices and inventory consistency. This pure display increment adds no concurrent operation; actual approval/reservation concurrency belongs to C-03. Supporting-document/E-sign and accomplished-file validation remain covered by retained tests.

Acceptance: meaningful failing active render tests first; real synthetic already-approved document generation and authorization; final both-engine affected regression including C-01 and all inherited cases; original employee HTML byte equivalence; rendered ordinary/boundary PDFs fit without clipping/overlap and preserve blank physical fields; source/reference/private/history preservation; documentation and owned runtime cleanup. No actual corrected external approval or production compliance is certified here.

Proposed code: focused DocumentService::laundryFormHtml/gatePassHtml using existing DocumentAffiliationService; conditional external-only wrapping; render before supersede in conditionalForm. Add ExternalConditionalDocumentAffiliationTest, reuse a test-only approved fixture where helpful, and update guides/progress. No dormant-template changes. Dependencies: final approval, conditional-line rules, protected storage and immutable snapshots. Risks: portrait width with255-character names, unavailable institutional print approval and missing hosting/ICTU/KPI/inventory attachments; Docker runtime readiness must be verified, not presumed.

## Steps and requirement trace

1. Preserve baseline/reference/preview hashes and inspect current active source/fixtures.
2. Reproduce missing agency/live-unit fallback with failing Laundry/Gate Pass cases.
3. Add only saved-company display and needed external wrapping; keep employee and physical semantics.
4. Verify guards, private generation/access/history, real saved signatures and unchanged operational data in synthetic fixtures.
5. Run isolated SQLite and MariaDB11.4 tests, inspect final rendered PDFs, update progress and safely restore local preview without resetting its data.

| Requirement | Acceptance evidence to record |
|---|---|
| C02-AFF-01 | Laundry requesting affiliation and Gate Pass secondary company use reviewed version only |
| C02-AFF-02 | Employee/Faculty/Staff/legacy fallbacks and employee HTML unchanged |
| C02-AFF-03 | Full escaped/bounded company, missing marker, invalid loaded affiliation before supersede |
| C02-EVID-01 | Borrower/SAO/Head saved signatures and private version/subject/hash metadata; original bytes/access scoped |
| C02-PHYS-01 | Guard and laundry physical labels/blank fields retained; saved worker/date/condition evidence unmodified |
| C02-GUARD-01 | Preapproval and inapplicable forms rejected; no added inventory/custody/audit/notice transitions |

## Implemented change and accepted results

Existing Laundry behavior preferred version office/program then live employee unit even for an explicit external version; Gate Pass had no agency. New active renderers consume the unchanged DocumentAffiliationService: external Laundry Requesting Office prints the reviewed company/missing marker, and Gate Pass prints secondary affiliation below the bearer name. Employee fallback order and complete employee HTML are unchanged. External-only word wrapping keeps255-character names within the original portrait field/block. Conditional-form HTML is resolved/rendered before superseding an existing controlled copy, so unsupported loaded affiliation leaves the old copy valid. Dormant template/render payloads, physical accomplishment logic and form revisions remain unchanged.

Verified code scope: one existing application file (DocumentService) changed; new test fixture and new feature class only. No migration, dependency/configuration/authority/inventory/timing change. Source baseline:615 of616 captured files unchanged; exactly two test files added. All43 working/test/preview source hashes match. All153 preserved preview database/private/reference files match; the three synced references were not edited. Historical documents were not regenerated.

Execution: **433/433 tests and4047 assertions on each of SQLite and MariaDB11.4.12**, exit0,28 related feature classes. This includes all387 C-01 cases,31 new conditional-document cases and15 existing laundry/report cases. No inherited exclusions. The new31-case focused SQLite run also passed with172 assertions. **17/17 frontend checks** passed for Gate/Laundry headers and existing Borrower Slip layout/single-copy semantics. Changed/new PHP syntax, scoped Pint and diff checks passed. Installed PHP8.4.26, Laravel13.25.0 and PHPUnit12.5.33 were verified locally.

Output acceptance: eight detached synthetic PDFs (each form × ordinary external/employee/255-accented/255-unbroken company), each one-page A4 portrait, complete field text and no out-of-page characters; Laundry has logo+borrower/Head images, Gate Pass has logo+borrower/SAO/Head images. All eight samples were visually inspected. The final eight-page merged review packet was re-rendered: every final PNG is pixel-identical to its inspected individual sample and extracted text agrees via pdfplumber. Employee HTML before/after hashes match for both forms. Blank physical-worker/completion/guard/release fields and unchanged saved physical records are asserted in generation tests. These samples are not actual external approval or operational documents.

Interim findings and corrections: the initial test fixture omitted the schema-required Gate Pass custody_line_id; adding the existing line reference fixed the fixture without application/schema changes. The corrected reproduction then showed both actual gaps: Laundry printed the deliberately wrong CSPC office and Gate Pass agency was blank. Initial whole-page text searches for long Laundry names were interrupted by adjacent labels; extraction from the underlined field verifies the complete company. pypdf text extraction rejected Gate Pass content in an interim helper; final independent text checks use pdfplumber alongside Poppler rendering/pixel comparison. These helper problems do not represent passed checks; only the final verified output is accepted.

Evidence: workspace `sprint1/route2c02`, `route2c02-red-corrected-fixture.txt`, `route2c02-first-focused.txt`, `route2c02-final-sqlite.txt`, `route2c02-final-mariadb.txt`, and `route2c02-accepted-filter.txt`. Task folder holds source delta, tested/final source manifests, employee-preservation hashes, render manifest, PDF/packet acceptance, screenshots, frontend checks, final preservation, preview and container-cleanup evidence. Final synthetic sample packet is under workspace `output/pdf`.

Docker Desktop's backend initially was not accessible; the existing Desktop start command returned already running and the backend became available. Existing application/container migration mode was checked as RUN_MIGRATIONS=false. Only a separately labelled, localhost-bound disposable MariaDB test database was used for tests, then removed after exact ID/task-label checks. Pre-existing containers were retained. The old portable preview was stopped before this task; it was restarted with the existing guarded runner/data after acceptance. Login HTTP200 and an owned PHP process were verified; VS Code folder command succeeded. No working/live database migration/reset, real mail/OAuth, actual corrected external approval, historical backfill, commit/push or deployment occurred.

## Completion checklist and boundary

- [x] Requirement/actor/input/output/state/database/failure analysis before implementation.
- [x] Two genuine failing active rendering cases retained after fixture correction.
- [x] Saved-only affiliation; full safe company and truthful missing marker; internal assignment ignored externally.
- [x] Employee/Faculty/Staff/legacy fallback and both byte-identical employee layouts.
- [x] Real synthetic private PDF/signature/version/hash metadata; scoped owner/SAO/Admin access and wrong borrower denial.
- [x] Preapproval/inapplicable/invalid loaded affiliation guards; existing history and physical records preserved.
- [x] Both-engine433/433 regression,17 frontend, eight inspected output samples.
- [x] Documentation/progress,43 source matches,153 preserved files, owned database cleanup and restored local preview.

Gate PASS covers this company-rendering increment only. Physical institutional print approval and missing hosting/ICTU/KPI/inventory evidence remain separate. Next checkpoint is S1-ROUTE-02C-03: actual corrected external approval/exact reservation/applicable packet, concurrency, rollback and audit/notice consistency. Broader controlled-replacement/whole-packet atomicity is part of that later acceptance, not established by these direct conditional-generation cases.

## Remaining implementation checkpoints

C-02 is authorized now. C-03 (actual approval/exact reservation/conditional packet/concurrency/rollback), S1-REG-03 (external account editing), S1-CANCEL-01 (SAO-confirmed cancellation) and S1-ANALYTICS-01 (descriptive/scheduled-demand scope) remain small gated increments. S1-AUTH-01 requires confirmed domain/fallback decisions before affected authentication changes; S0-ICTU-01 is blocked by missing institutional attachments and the reported, unverified Bolivia-title mismatch. Full release/return/laundry/accountability/notification/KPI and system-quality/deployment acceptance follow these increments; no new success claim or policy is established by this list.
