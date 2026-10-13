# S1-ROUTE-02C-01 — saved affiliation and active Borrower Slip

Status: VERIFIED COMPLETE for this resolver/Slip increment. Gate: PASS. Full S1-ROUTE-02 remains IN PROGRESS; deployment NOT READY.

## Analysis before implementation

Objective: correct the active built-in Rev.4 Borrower Slip's external classification and print the reviewed company from the saved request version. Reuse that source through a pure document-affiliation resolver, keeping agency names distinct from CSPC unit facts.

Confirmed requirements: C-R01/C-R03/C-R05/C-R06/C-R08 and TIME-01 in `EXTERNAL-APPROVAL-OUTPUT-READINESS.md`; user authorization for this increment. Workflow P164/P191 places document generation at Admin approval before SAO preparation. The manuscript's XP/BPR requirements require small tested increments. The active ZIP layout, employee checkbox behavior, two saved E-signature blocks and existing authority remain the baseline. No new institutional classification or eligibility rule is introduced.

Actors/permissions: authorized Borrower/SAO/Admin retain their existing document access. Only the existing permitted generation path produces new controlled documents. This increment changes neither approval authority nor file-access policies.

User stories: an external borrower sees Others checked and their reviewed agency; an employee keeps the existing Employee classification; reviewers can trust that subsequent profile edits do not rewrite the affiliation on a saved version or historical PDF.

Preconditions: an existing saved request version, and final approval/custody for real operational generation. Postconditions: newly generated Slip uses that version's affiliation, correct classification and escaped company; its existing signature/version/subject/private-file metadata remain intact. Historical bytes and snapshots are not regenerated.

Business rules: explicit EXTERNAL uses Others and a printable external-borrower/company label, never a CSPC office. Explicit EMPLOYEE and legacy null affiliation preserve active Employee behavior, including faculty/staff. An explicitly external historical version with missing/blank company prints an affiliation-not-recorded marker; it never falls back to current registration, profile or internal units. An unknown explicit affiliation is rejected. The resolver reads scalar version attributes only and performs no query or write.

Inputs/outputs: saved affiliation_type, external_company_name, division_code and office_unit → scalar classification/display data → the existing Others line. Safe wrapping is an implementation detail needed to fit the existing 255-character company limit; preserve artwork, labels, spacing and employee output. No migration or new database field. No approval, inventory, release or request-state transition is added.

Failures/boundaries: stale/inconsistent profile units, deliberately inconsistent external version units, missing company, invalid affiliation, HTML-sensitive content, 255-character accented company and a long unbroken name. Preserve preapproval guards, signature evidence, protected access and old file hashes. Concurrency does not apply to this pure resolver; reservation/concurrent approval belongs to C-03.

Acceptance: resolver truthfulness and zero database operations; correct external/employee active HTML; readable one-page ordinary/boundary PDF without collisions; final-approved private generated document has correct saved version/subject/hash and existing signatures; preapproval denied; existing document access/history preserved; accepted related regression passes on isolated SQLite and MariaDB 11.4; source and dependency preservation checks.

Proposed code: new DocumentAffiliationService; focused dependency injection/classification/conditional external wrapping in DocumentService; new ExternalOperationalDocumentAffiliationTest. No editable-template activation. Dependencies: existing RequestVersion fields, signature resolver, built-in PDF renderer, private storage and final-approval guard. Risks: document fit, legacy missing affiliation and unavailable institutional physical revision sign-off. Missing hosting/ICTU/KPI/inventory attachments still prevent deployment certification.

Out of scope/pending: Laundry/Gate Pass affiliation (C-02); actual corrected external approval/reservation/conditional packet and rollback/concurrency (C-03); historical correction; cancellation/analytics/domain policy; institutional print approval and deployment. This task does not certify the full external approval cycle.

## Planned procedure and traceability

1. Preserve current source/preview/reference hashes and read existing code/tests.
2. Add failing tests for frozen affiliation and active external/employee rendering.
3. Implement the pure resolver and the smallest active Slip change; inspect rendered PDFs.
4. Verify real synthetic approved-document metadata, private bytes/signatures, guards and history/access.
5. Run accepted related regressions on isolated SQLite and owned MariaDB; check syntax/source preservation.
6. Update progress/guides/evidence and copy only verified application changes to the running preview, preserving its database and historical files.

## Implemented change and verification trace

Existing behavior: the active Slip always checked Employee and left Others blank. New behavior: DocumentAffiliationService reads only version attributes; the active external branch checks Others and fills the existing line with External Borrower / reviewed agency. No current registration/company/unit fallback. Internal Employee/Faculty/Staff and legacy null affiliation retain the existing active layout. Unknown loaded values raise an affiliation validation error; HTML is validated/rendered before superseding an existing controlled copy. No custom-template activation or form-revision switch.

The external line alone gains wrapping and top-aligned choices. Initial max-length rendering kept the full company but split checkbox/label alignment; latest rendered samples correct that. Employee HTML before/after is byte-identical (SHA256 `6ea0c92fc3dd30d27812512dede4625b7da51de4d31d7f4f2a573c94cc6fb88f`). Four detached synthetic fixtures render as one-page A4 landscape each, with full company, no out-of-page characters, aligned labels, and three images (institutional logo and two synthetic signature snapshots). All four merged-packet PNGs were inspected. They are render examples, not an actual external approval or operational packet.

| Requirement | Feature/output evidence |
|---|---|
| AFF-01 | Active external Others/company; synthetic approved generation, saved version/subject/private metadata and borrower/Head signature images |
| AFF-02 | Later registration/unit changes and deliberately inconsistent employee fields cannot replace reviewed company; resolver has a query tripwire and no mutation |
| AFF-03 | Employee/Faculty/Staff/legacy null retain checkbox/unit semantics; byte-identical employee HTML; existing permission tests retained |
| AFF-04 | Complete HTML-sensitive/255-accented/255-unbroken values, escaping, scoped owner/Admin/wrong-borrower access and inspected PDFs |
| AFF-05 render-only portion | Null/empty/whitespace external company shows an explicit missing-affiliation marker; unknown loaded attribute fails before changing the old copy. New-approval missing-company guard remains C-03. |
| AFF-06 | Existing PDF/file metadata/bytes and signatures unchanged after profile edits; generation changes neither fixture allocations nor custody/gate/laundry counts/audit/notices; dormant-template guard retained |

Automated evidence is in workspace `sprint1/route2c01`. The accepted filter retains all22 prior B classes and adds this feature class, BorrowerSlipRev4LayoutTest and FixedDocumentTemplateScopeTest:25 classes,369 retained cases and18 new cases. Interim broad runs must not be called clean387/387 runs: each reports386 passes plus one new **fixture** error. The corrected feature-class reruns separately establish acceptance; final engine counts/gate are recorded below when both finish.

Corrections during verification:

- An initial helper named head collided with Laravel's HTTP-test method; renamed headUser before defect reproduction. The real external checkbox reproduction then failed as intended (Employee was checked).
- A newly created document's in-memory attributes omit defaults that fresh database models include. Reloaded the fixture document before immutable-attribute comparisons; no production data rewrite.
- Eight existing OffCampusGatePassWorkflowTest cases failed when Friday schedule + one calendar day selected a closed Saturday return. The normal-flow fixture now chooses nextOpenDate(RETURN); no operating-calendar application change, hardcoded new date, excluded case or weakened assertion.
- The new invalid-affiliation fixture initially tried to persist UNKNOWN, which both database enum constraints correctly rejected before rendering. It now simulates an unsupported loaded attribute without writing invalid data. The production enum is untouched. The fixture error is recorded and corrected, not waived.
- Detached rendering required explicit source/autoload binding; the final QA helper asserts the loaded DocumentService filename matches its preserved render snapshot. Only matching final output is accepted.

Sources/configuration/dependencies remain unchanged except the focused existing DocumentService and the one calendar test fixture. New code files: resolver and feature test. No migration, working/live database reset, historical PDF regeneration, approval/stock/authority/timing change, commit/push or deployment. The current preview's database, private files and three synced references were hashed before work; final preservation and39-file working/test/preview comparisons are required before PASS.

## Acceptance checklist

- [x] Preimplementation requirements and scope documented.
- [x] Actual failing external checkbox case retained.
- [x] Resolver, scoped Slip correction and protected supersede ordering implemented.
- [x] SQLite18/18 feature cases,152 assertions;369/369 retained cases in broad run.
- [x] MariaDB369/369 retained cases in broad run; engine11.4.12 verified.
- [x] Corrected MariaDB18/18 feature cases,152 assertions; final gate evidence recorded.
- [x] Both new/changed PHP syntax, scoped Pint and diff checks;11/11 existing Slip layout/workflow frontend checks.
- [x] Four latest synthetic PDF samples/merged pages inspected, no clipping/collisions; employee HTML unchanged.
- [x] Final preview/source/reference/history preservation and owned test-container cleanup.

Final acceptance: **369/369 retained related cases plus18/18 corrected feature cases per engine**,25 classes,387 unique covered cases. Focused runs each have152 assertions and exit0. The broad logs each show386 passes and the recorded invalid-enum fixture error; they are retained unchanged, not relabelled as a single clean387/387 run. Corrected complete18-case feature reruns on SQLite and MariaDB11.4.12 verify the final feature source, while all other369 cases passed with the same final application code. No case is excluded or ignored. Eleven frontend checks pass. Four inspected synthetic PDFs have no out-of-page text, complete companies and aligned choices.

Preservation evidence:39 working/test/preview source hashes match;591 baseline application/resource/schema/config/test/dependency files unchanged, exactly two existing files changed (DocumentService and calendar fixture) and two new code/test files. All153 preserved files match, including the preview database, existing private files and three synced references. No schema/dependency change. Preview login returns HTTP200 with owned PID20304; VS Code folder command exits0. The preview remains an isolated SQLite/array-mail demo and retains the rejected corrected external request and unrelated historical fixtures. No live external approval, real email, migration/reset or new operational preview document was performed. Owned MariaDB container `a84959af6aa67c78f9e7cbd52a109b1220bed1a1036083cab0eb8473b9378e53` was removed after ID/task-label checks; pre-existing app/database/build containers remain.

Evidence files: `route2c01-final-{sqlite,mariadb}.txt`, `route2c01-final-focused-{sqlite,mariadb}.txt`, `route2c01-accepted-filter.txt`; task folder `acceptance.json`, `final-source-manifest.json`, `preserved-final.json`, `pdf-acceptance.json`, `packet-acceptance.json`, `container-cleanup.json`, frontend logs and source/PDF diagnostics. Final four-page synthetic review copy is under workspace `output/pdf/`. Physical institutional print sign-off and missing ICTU/hosting/KPI/inventory attachments remain separate.

Next increment after PASS: S1-ROUTE-02C-02 — active Laundry Form requesting affiliation and Gate Pass bearer-company display using the same resolver, with preserved employee fallbacks, physical actors, timing and rendered-layout tests. Actual corrected external approval/reservation/conditional packet/rollback/concurrency remains C-03. Full S1-ROUTE-02 is IN PROGRESS; deployment NOT READY.
