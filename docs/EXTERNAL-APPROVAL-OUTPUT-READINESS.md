# S1-ROUTE-02C-PLAN — external approval/output readiness

Status: VERIFIED COMPLETE for planning only. Gate: PASS. This is a mapping/decision/test-design increment. No application, approval, document-generation or inventory behavior is changed here.

## Objective and governing requirements

Map the actual external request → current SAO verification → Admin approval → reservation/custody → applicable controlled documents, identify truthful affiliation fields and decide whether generation timing needs any change. Use the manuscript's XP/BPR planning/design practices; retain small, testable increments and source-to-test evidence. Operational KPI evaluation and ISO questionnaires remain separate from code checks.

Sources are the user's confirmed decisions; [supplied workflow](<C:/Users/Admin/.codex/.chatgpt-projects/g-p-6ac71669f7348191b486af096a71c81b/sources/Wofklow FINAL (AutoRecovered).docx>); [manuscript](<C:/Users/Admin/.codex/.chatgpt-projects/g-p-6ac71669f7348191b486af096a71c81b/sources/Capstone Manuscript.docx>); read-only ZIP baseline; current checkout and retained S1-ROUTE-02B evidence. P numbers below are zero-based direct body-paragraph indexes extracted from DOCX XML, not page numbers. The manuscript images reviewed are branding and the XP/BPR phase diagram; no alternate form-generation timing was found there. Formatting/template assets in the ZIP establish existing code/layout, not an independent institutional sign-off.

| ID | Requirement / classification | Source | Implementation / verification trace |
|---|---|---|---|
| C-R01 | Confirmed: registered external affiliation is reviewed company; no fabricated employee number/internal assignment | User decisions; workflow P1/P3/P4 | BorrowerRequestAffiliationService::snapshot; RequestVersion::isExternalAffiliation/affiliationLabel; existing registration/draft/revision tests |
| C-R02 | Confirmed: on-campus → Admin; off-campus → current SAO verification → Admin; retain existing final authority | User confirmation; workflow P52/P56/P162 | ApprovalController::verify/decide; RequestWorkflowService::verifyByActionOfficer/decide; S1-ROUTE-02B actual corrected cycle |
| C-R03 | Confirmed baseline: Admin approval generates Borrower Slip plus applicable Laundry Form/Gate Pass before SAO preparation | Workflow P164/P167/P168/P191; manuscript gives general borrowing/issuance requirements without contrary timing | RequestWorkflowService::decide; CustodyService::prepare; decision TIME-01 below |
| C-R04 | Confirmed: approval reserves exact configured eligible quantity; verification/rejection do not reserve | Workflow P41/P42 full-issuance principle; current approved technical direction | InventoryService::allocate locks each item/rechecks availability; existing regression and planned AP tests below; institutional balances remain unverified |
| C-R05 | Confirmed: approved applicable forms and history are accessible to authorized Borrower/SAO/Admin | Workflow P172/P191 | DocumentService and DocumentController; existing closed-record/wrong-custody protection; planned current-version/packet checks |
| C-R06 | Confirmed: E-sign at authorized borrower/SAO/Admin actions; external SoG/laundry physical actions recorded without inventing their portal identities/signatures | User E-sign/external-laundry decisions; workflow P173/P174/P202/P203 | Existing immutable signature snapshots and physical accomplished evidence; no new actor/signing authority proposed |
| C-R07 | Confirmed: no walk-ins, no automatic cancellation; descriptive/scheduled-demand only | Direct user instructions override workflow P158 and automated-cancellation wording P42 | Separate S1-CANCEL/S1-ANALYTICS work; no cancellation/analytics change in this plan |
| C-R08 | Confirmed engineering: iterative XP/BPR and evidence-based correctness | Manuscript P75/P77/P245/P249/P251/P281/P312 | Planning artifacts, diagnostics and acceptance design here; no institutional KPI improvement claimed |

Actors: active approved external Borrower owns submitted evidence; SAO verifies off-campus current version using their own effective E-sign; Admin/Head or the existing formally authorized delegate decides. SAO prepares/releases; external guard/laundry workers stay physical actors. ICTU maintains technical records under existing scope. No new role, delegate, unit or borrowing eligibility.

User stories: borrower receives documents identifying their actual reviewed agency; Admin signs only the reviewed verified version and reserves exactly once; SAO sees an already approved packet and validates it before preparation; historical files/signatures remain readable and unchanged after profile updates.

Preconditions for the later approval increment: approved active registration/mailbox/account, current submitted signed version, readable/integrity-checked current letter and conditional PTC, current SAO VERIFIED step for off-campus requests, pending Head step, current Head E-sign and deliberate consent, valid configured calendar/eligible item/available stock. Postconditions: current Head APPROVED with captured E-sign, immutable reviewed affiliation, exact reservation, one custody/pickup record, one current applicable packet, audits/notices and zero physical release until SAO handover. Failure must leave no partial reservation/custody/new file/snapshot/output.

Inputs: saved version identity/affiliation, request items/premises, configured calendar/stock, signature consent and supporting evidence. Outputs: approval decision, captured signature, reservation ledger, custody/pickup, Borrower Slip and applicable Gate Pass/Laundry Form, scoped notices/audits. Proposed database impact: reuse existing records and fields; no new migration is expected for affiliation rendering. No historical backfill or regeneration is authorized here.

## Actual architecture and active paths

1. [ApprovalController::decide](C:/Users/Admin/capstone-spmu-acpmp/app/Http/Controllers/ApprovalController.php:116) validates existing authority/decision/consent/reviewed_version_id and calls the service. S1-ROUTE-02B added current-version consistency without changing authority.
2. [RequestWorkflowService::decide](C:/Users/Admin/capstone-spmu-acpmp/app/Services/RequestWorkflowService.php:615) locks the request, checks bound/current/supplied review identity and pending Head step, and requires current SAO verification for off-campus. Rejection/return have no reservation. Approval validates current evidence/calendar, calls allocation, captures Head signature, marks APPROVED, transitions status and creates custody/pickup.
3. [InventoryService::allocate](C:/Users/Admin/capstone-spmu-acpmp/app/Services/InventoryService.php:1576) uses a nested transaction, item row locks and date-overlap availability recheck. It records exact requested/approved quantities, allocation and AVAILABLE→ALLOCATED ledger movements; it does not silently reduce quantity. Reservation starts at the configured earlier pickup day. A stock ValidationException returns the request for correction under existing behavior. Multiple-item lock order and contention still require real parallel acceptance.
4. [CustodyService::ensurePickupRecord](C:/Users/Admin/capstone-spmu-acpmp/app/Services/CustodyService.php:55) locks the request and firstOrCreate's its custody. Approved quantities and pickup/due fields feed its lines. The same approval transaction then calls Borrower Slip, off-campus Gate Pass and laundry-required form generation.
5. [DocumentService::borrowerSlip](C:/Users/Admin/capstone-spmu-acpmp/app/Services/DocumentService.php:60) and [conditionalForm](C:/Users/Admin/capstone-spmu-acpmp/app/Services/DocumentService.php:596) enforce final approval, render controlled HTML/PDF, store private bytes and GeneratedDocument/version/subject metadata. ProtectedFileService tracks newly written files for outer rollback. Signatures resolve saved borrower/current approval/SAO snapshots, not another user's upload.
6. Production currently uses **built-in controlled layouts**. [activeUploadedTemplate](C:/Users/Admin/capstone-spmu-acpmp/app/Services/DocumentService.php:1894) returns null; template definition resolve returns defaults; routes expose no editable-template workflow. The ZIP has the same boundary. Office/XLSX/custom payload helpers and old editable masters remain legacy/inactive code/assets, not a second live generation architecture. Do not revive them to fix external borrowers.
7. [CustodyService::prepare](C:/Users/Admin/capstone-spmu-acpmp/app/Services/CustodyService.php:1101) validates the existing approved Borrower Slip/applicable Gate Pass/Laundry Form before persisting physical preparation. It generates no substitute Slip/Gate Pass. Release reuses the existing Laundry Form; its missing-form fallback is compatibility behavior after approval, not the normal initial generation point.

State trace: UNDER_SPMU/current SAO RECEIVED → VERIFIED + current Head RECEIVED → Head APPROVED + APPROVED_READY_FOR_RELEASE/reservation/custody/packet → PREPARING_RELEASE with active pickup → SAO prepared → actual release/return. Existing enums/statuses and operating-calendar logic are preserved; a label alone is not proof of physical release.

## TIME-01 — generation-timing decision

Decision: **retain the existing approval-time generation event. No timing rewrite and no additional confirmation are needed for retaining the available agreed baseline.** This does not grant permission for a different event.

| Evidence | Exact finding |
|---|---|
| Workflow P164 and P191 | Applicable forms generated on approval, then SAO prepares for release; accessible to authorized actors |
| Workflow P176/P182 | Pickup is created after approval and respects configured operation/closure schedules |
| Manuscript P249/P251 and reviewed figures | General requirements/design/iterative validation; no instruction to delay forms until physical preparation found |
| ZIP RequestWorkflowService | decide at L591; approval signature L1047; Borrower Slip L1158; conditional forms L1193/L1217 |
| Current RequestWorkflowService | Borrower Slip L1198; Gate Pass L1233; Laundry L1257 inside approval transaction |
| ZIP/current CustodyService | prepare L1101, validates existing approved release documents at L1145; no creation there |
| ZIP/current CLIENT-WORKFLOW-FINAL | Already says only Head approval reserves and creates operational documents, then preparation validates them. This code document is supporting history, not a replacement institutional requirement. |

The earlier progress/QA notes describing the **current implementation** as preparation-time generation were incorrect. Reviewed code/ZIP/source evidence resolves that factual issue. An initial suspicion of older test mismatch is not itself a conflicting approved requirement; no genuine preparation-time institutional requirement was located. Historical execution logs stay intact; append a correction to their documentation instead of retroactively changing evidence. File names marked Final were not used to rank conflicting authorities.

Operational implication: keep the approved packet available before pickup/preparation; preserve conditional applicability and whole-transaction rollback. Moving generation later would remove those pre-pickup documents and require a separate confirmed workflow redesign. Future contradictory institutional evidence must be presented to the user before changing this event.

## Truthful affiliation mapping and readiness findings

The reviewed external source is `request_versions.external_company_name` with affiliation_type EXTERNAL, not the borrower's later registration/profile/unit. Its schema and registration validation allow255 characters. External `borrowing_requests.accountable_unit_id` and version division/office remain null. CSPC header/logo identify the institution issuing the form; they are not borrower employment or office assignments.

| Output / path | Existing source and observed behavior | Proposed truthful behavior / scope |
|---|---|---|
| Request form/details/printable request letter | Version affiliationLabel already displays reviewed company; UI prevents fabricated unit assignment | Retain; regression against later profile/company/unit edits and forged input |
| Active built-in Borrower Slip | borrowerSlipHtml L287 hard-codes checked Employee; Others blank. Company never read/displayed | First small fix: explicit EXTERNAL → Employee unchecked, Others checked; fill existing Others line with external classification and reviewed company, with safe wrapping/escaping. Preserve all form artwork/labels/signature blocks. Employee branch remains unchanged. |
| Active built-in Laundry Form | laundryFormHtml L1019 prefers version office/program then live borrower organizationalUnit. Normal external yields blank; an inconsistent later profile unit leaks into that field | External Requesting Office displays frozen agency as printable affiliation, without writing it to internal-unit columns. Employee fallback order remains unchanged. Later increment; physical worker fields/signature/date remain actual records/blank until real evidence. |
| Active built-in Gate Pass | Bearer name/purpose/destination and SAO/Head snapshot blocks; no company/office field consumed | Later increment: reviewed company as secondary affiliation text in the existing bearer block; keep personal name separate, existing title/artwork/purpose/destination unchanged. Validate fit and physical readability; do not invent a new designation or CSPC office. |
| Legacy custom render payloads | No company/affiliation keys; external Slip checkboxes both blank. Gate/Laundry requesting_office may fall back to live profile unit | Diagnostic findings only. No activation or expansion of editable templates. If shared safe payload code is reused, expose frozen scalar affiliation separately from internal-unit facts and keep the same scope boundary. |
| Legacy Excel payload | External Employee☐/Others☒ already truthful checkbox selection, but external caption/company missing | Preserve inactive status; do not replace active rendering with the old workbook. |
| Legacy Office runtime context | No company/affiliation semantic path; safe runtime details include live profile office | Diagnostic compatibility concern; a future separately authorized change must keep company/display affiliation and internal-office semantics separate. Never bind an agency to a CSPC office fact. |
| Existing generated files/history | Stored private bytes/metadata and immutable signature snapshots remain historical evidence | Do not regenerate/backfill/correct old PDFs silently. New corrected rendering affects newly generated permitted documents only; controlled historical correction requires its own authorized process. |

Current reproduction evidence: `sprint1/route2c-plan/payload-observations-active.json`. Nine unsaved model graphs (external, external with deliberately inconsistent later profile unit, employee; each across Slip/Gate/Laundry) invoke existing pure HTML/payload helpers. They execute **zero database queries** under a query tripwire, and no workflow/allocation/save/generation method. External company appears in **none** of the three current built-in forms; Slip falsely checks Employee; inconsistent external profile office prints on Laundry. These observations demonstrate rendering gaps, not a passed external approval or proof of corrupt real borrower records. Earlier payload-only observations are retained separately.

Read-only existing preview inventory confirms only BUILT_IN ACTIVE rows for the three types; no uploaded template file/render file. Request6 remains REJECTED/v2 with null unit; global3 allocations/3 custodies/2 historical generated documents remain. PDO query_only and before/after database byte hash confirm no change during inspection. The two existing documents are unrelated historical QA fixtures, not a new approval packet.

Existing DOCX masters and one-page A4 static PDF previews were read/rendered/visually inspected. Borrower master/preview says Rev.3 and differs from active built-in Rev.4; Gate/Laundry have their own version labels. They are inactive assets, not a basis to silently switch official revision, activate templates or rename fields. No AcroForm filling occurred. Institutional approval of a physical form revision remains separate; the next fix preserves the current active ZIP layout.

## Issues, dependencies and acceptance design

| ID | Status | Finding / next action |
|---|---|---|
| TIME-01 | VERIFIED COMPLETE for mapping | Existing approval-time event matches available workflow/ZIP; correct old notes, retain behavior |
| OUT-01 | VERIFIED COMPLETE in C-01 | Active Slip misclassification/company corrected; saved-only resolver, both-engine cases and inspected outputs recorded in EXTERNAL-BORROWER-SLIP-AFFILIATION.md |
| OUT-02 | VERIFIED COMPLETE in C-02 | Active Laundry now uses saved external agency; employee fallback retained; both-engine/PDF evidence in EXTERNAL-LAUNDRY-GATE-PASS-AFFILIATION.md |
| OUT-03 | VERIFIED COMPLETE in C-02 | Active Gate Pass secondary bearer company added; separate personal name, original artwork and physical guard fields preserved; rendered output accepted |
| OUT-04 | FOR REVIEW | Inactive template/runtime discrepancies; remain inactive and do not block active Slip repair |
| AP-01 | VERIFIED COMPLETE in C-03 technical scope | Actual corrected UI approval, exact applicable packet, fresh MariaDB current-read concurrency and persisted rollback passed; EXTERNAL-CORRECTED-APPROVAL.md records evidence/limits |
| AP-02 | VERIFIED COMPLETE in C-03 scoped approval/conflict path | Audits before notices; notification records transactional and emails deferred until commit; persistence rollback and truthful FAILED transport cases passed. Broader outbox/SMTP/crash recovery remains unclaimed |
| PAPER-01 | FOR REVIEW | Physical revision/print sign-off and any proposed layout change; preserve current active form baseline |
| INST-01 | FOR REVIEW | Hosting form, nine-deliverable ICTU letter, separate authoritative inventory/Test-KPI attachments and TD RSC PDF unavailable in sources. Reported Bolivia title mismatch remains unverified; no institutional deployment certification. |

Other risks: pending external Admin account editing, automatic-cancellation correction and approved analytics scope remain separate; no opening balance/fees/sanctions/domain policy invented. Baseline PHP8.4.26/Laravel13.25.0 and historical MariaDB11.4.12 evidence are retained; no dependency/configuration upgrade. Company escaping and255-character Unicode wrapping must not overwrite signatures, labels, page boundaries or custody quantities. Office runtime fields containing private paths/signature assets must stay protected.

### Planned tests — none marked executed/passed here

| Case ID | Scenario | Expected result / evidence to retain |
|---|---|---|
| AFF-01 | Active Borrower Slip for approved external version | Employee unchecked; Others checked; external caption and frozen company; correct borrower/Head snapshots; unchanged active layout |
| AFF-02 | Later registration/company/unit/designation edits and forged client unit fields | Version's reviewed company remains; no external internal assignment/fallback; original versions/signatures/files unchanged |
| AFF-03 | Employee, Faculty and Staff fixtures | Existing employee classification/fallback/permission behavior retained; no external company substituted |
| AFF-04 | Ampersands/quotes/HTML and255-character Unicode company | Escaped text; complete legible company; no XSS, clipping, page/signature overlap; unauthorized attachment access still denied |
| AFF-05 | Blank/missing/corrupt external snapshot; legacy employee missing snapshot | New approval must fail closed for missing reviewed external affiliation; render-only legacy history must never invent an employee/unit; preserve agreed employee compatibility. Specify the new-approval guard in the approval increment, not a historical backfill. |
| AFF-06 | New document generation and retained historical outputs | New output uses correct company; old stored bytes/hash and signature snapshots unchanged; dormant templates stay unable to override production |
| AP-03 | Corrected external off-campus Barricade → actual SAO verify → Head E-sign approve | Exactly one approval signature/decision/reservation/custody/Gate Pass/Slip; truthful company and current-version metadata; no premature laundry form or physical issuance |
| AP-04 | On-campus regular and baseline-eligible laundry-required request | Existing direct Admin routing; Slip only or Slip+Laundry as applicable; no Gate Pass; no manufactured worker/SOG signatures or dates |
| AP-05 | Mixed premises, unauthorized/revoked/self/invalid-delegate actor, missing consent/required documents/corrupt signature | Existing rejection/validation gates; no new reservation/custody/packet/file/snapshot; correct queues/history |
| AP-06 | Stale bound/version-id review and duplicate/completed approval | Refused or existing safe duplicate response; exactly one effective decision/packet/reservation; refreshed reviewer only |
| AP-07 | Two actual MariaDB workers, same request; competing requests for insufficient overlapping stock; multi-item failure | Same request approved once; competing stock never over-allocated; existing stock-conflict correction behavior and no partial ledger/file/custody; record actual statuses, not assumed422 |
| AP-08 | Signature/PDF/private-file failure, later audit failure, notification failure | Transaction/metadata/snapshot/new-file rollback, original bytes retained, no misleading known-failed-audit mail attempt; no unproved full outbox/SMTP guarantee |
| AP-09 | Authorized closed-record previews, wrong request-version/custody/file owner, invalidated packet | Correct cached history remains readable; inconsistent links403; cancelled/rejected records cannot revive a new packet through public endpoints |
| AP-10 | SAO preparation after approval | Required current approved documents validated; missing packet blocks; no duplicate creation, quantity editing, release or automatic cancellation |

Use isolated SQLite and MariaDB11.4 with separate private/Blade/cache storage; synthetic readable PDFs/PNG; actual authorization endpoints and meaningful output/DB/hash assertions. The prior S1-ROUTE-02B359/359,3564 assertions per engine and22 frontend cases are **historical retained results**, not rerun or a new approval/output certification. For the future change retain that entire affected filter without nine-case exclusions; add FixedDocumentTemplateScope/BorrowerSlip layout and relevant focused render/routing tests as justified. Visual PDF review is required alongside semantic extraction; physical printing/phone/load/KPI outcomes require separate evidence.

## Phased implementation and exact next task

| Increment | Authorized objective / gate | Scope boundary |
|---|---|---|
| S1-ROUTE-02C-PLAN | This mapping, TIME-01 resolution, proposed field contract, planned tests and preservation evidence | Documentation/analysis only |
| S1-ROUTE-02C-01 | Shared read-only saved-version affiliation resolver + active Borrower Slip external classification/company | No timing, inventory, authority, schema, template activation or historical-file rewrite |
| S1-ROUTE-02C-02 | Active Laundry/Gate Pass truthful company rendering, with rendered layout acceptance | Keep current form artwork; preserve employee fallbacks, physical actors and applicable-output rules |
| S1-ROUTE-02C-03 | Actual corrected external approval/conditional packet, stock/parallel/rollback/audit-notice acceptance | Fix proven defects only; pass both-engine/end-to-end gate before broader release work |

Exact next small task **S1-ROUTE-02C-01**:

1. Define a pure resolver reading saved RequestVersion affiliation/company; external output carries reviewed company, external classification, display label and null internal facts. Never query current external registration/unit as a replacement. Preserve the existing employee branch and missing-company/history distinctions above.
2. Add failing meaningful active Borrower Slip rendering/document cases AFF-01–AFF-04/AFF-06 first. Use a synthetic already-approved custody fixture for document rendering, explicitly separated from the later real approval cycle. Capture original file/signature hashes and current employees' expected behavior. Assert dormant templates still cannot override production.
3. Fix only the current built-in Slip's existing Employee/Others fields; use existing Others line for External Borrower / reviewed agency. Preserve form code/revision/branding/fonts/spacing/signature blocks. Add only targeted wrapping required by verified max-length failure.
4. Proposed files: new `app/Services/DocumentAffiliationService.php`, focused changes in DocumentService::borrowerSlipHtml and a new `tests/Feature/ExternalOperationalDocumentAffiliationTest.php`, plus progress/guide documentation. This is a proposed technical design, not code added in this plan. No migration expected.
5. Run focused and justified retained related tests on both engines, preserve current preview data, generate/render test PDFs and inspect length/Unicode/signatures. Gate only the Slip/resolver increment; Gate Pass/Laundry and actual reservation approval remain subsequent checks.

The pure resolver performs no state transition or allocation. Renderer failures must preserve historical files and rolled-back new writes. Dependency: available signed version/company and unchanged built-in scope. Institutional form redesign or changing timing requires a concrete decision before affected behavior changes; retaining the demonstrated baseline does not require another approval.

## Planning checklist

- [x] Actual source/ZIP/manuscript/workflow and active generation/renderer paths mapped.
- [x] Requirements, actors, stories, pre/postconditions, inputs/outputs, database/states and failure scenarios documented.
- [x] TIME-01 settled by available evidence; previous factual error identified without changing runtime behavior.
- [x] Active versus dormant template paths distinguished; relevant static PDF previews inspected without modifying them.
- [x] Truthful company/internal-unit field contract and concrete proposed changes prepared.
- [x] Read-only rendering observations and preview inventory captured; no actual approval executed.
- [x] Planned acceptance/parallel/rollback/output/employee-regression cases and phased next task specified.
- [x] Final600 application/resource/test/dependency files and3 synced source hashes match; no additional application files; docs/register reviewed and planning evidence manifest recorded.

Gate PASS here certifies the original planning increment. Subsequent OUT/AP implementations are tracked in the table and linked C-01/C-02/C-03 evidence above; full S1-ROUTE-02 stays IN PROGRESS and deployment NOT READY.
