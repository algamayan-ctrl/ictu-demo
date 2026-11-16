# Approved accountability E-signature confirmation — 16 November 2026

The user approved both before/after previews for Late Return Billing and Accountability Decision. This revision adds explicit consent to those existing signing actions. It does not implement report signatories or revise other RSC items.

## Scope and behavior

- Keep the existing forms, labels, original action buttons, destinations and case layouts.
- After the original required fields are valid, open one compact **Confirm E-signature** dialog. Show the actual case reference and authenticated Head's name, plus the assessed amount for billing or selected Confirm/Clear decision for accountability.
- Start with consent unchecked. Enable **Confirm E-signature & Issue** or **Confirm E-signature & Record** only after consent is checked. No signature preview or additional signatory selection is introduced.
- Cancel, Escape and backdrop dismissal preserve entered form data, reset consent and restore focus. Keyboard navigation stays inside the modal. The dialog follows existing appearance variables and fits small screens.
- Require a fresh confirmation after a pending form changes, for another case and after browser history restoration. Repeated submit clicks retain the existing submission protection. Opening or dismissing the dialog does not lock the original form.
- Reject absent or unaccepted `confirm_e_signature` on the server before signature capture, billing, penalties, case changes or notices. The older Head decision handler is protected too; physical compliance verification remains on its existing separate branch.
- Preserve the registered-signature ownership check, signature purpose codes, immutable snapshots, permissions, billing calculations, sanction rules and document masters. Record accepted consent in the existing signature-applied audit context. Historical signatures and documents are not rewritten.

## Edited parts

| Part | Change |
|---|---|
| `app/Http/Controllers/AccountabilityController.php` | Consent validation on late billing and both current/legacy electronic Head decisions; consent evidence in the signature audit context. |
| `resources/views/accountability/index.blade.php` | Connect the two existing Head forms and load the shared dialog for the Head. |
| `resources/views/accountability/borrower-workspace.blade.php` | Connect the same actions in the borrower-scoped focused case view. |
| `resources/views/accountability/partials/signature-confirmation.blade.php` | New shared accessible dialog using the original styling variables. |
| `public/js/accountability-signature-confirmation.js` | Consent, dismissal, validation, focus and submission behavior for the annotated forms only. |
| Four affected feature-test files and one new frontend test | Explicit consent in valid existing fixtures, negative consent/ownership/rendering checks and dialog interactions. |
| RSC status and this tracker | Record the approved implementation and its verification limits. |

## Tracker

- [x] User approved both visual previews.
- [x] Preserve original edited files and the 887-file working-copy hash manifest in `C:/Users/Admin/.codex/tmp/ictu-signature-consent-20261116`. The original four test files were also preserved after matching their baseline hashes exactly.
- [x] Record the pre-change backend and frontend baseline.
- [x] Add the shared dialog and connect both actions in both existing Head views.
- [x] Add server consent guards before electronic signature capture.
- [x] Complete 29 frontend interaction checks, including 12 new dialog checks; zero failures/skips. The new checks use JSDOM with the actual dialog partial, shipped script and shared application submission-lock code. Native dialog behavior is simulated, so these are not browser screenshots.
- [x] Complete affected backend regression and reconcile baseline failures: 112 distinct checks evaluated across the related suite and one corrected fixture rerun, 111 passed, one unchanged baseline failure, 887 assertions; no unexplained failures.
- [x] Compare working-copy hashes: only the 12 listed implementation, test and tracker files changed or were added; no files removed. Whitespace and PHP/JavaScript syntax checks passed.
- [ ] Actual desktop/mobile visual review in the application browser.

## Baseline and review limits

Before implementation, 99 of 100 selected backend tests passed (647 assertions). The existing failure is `RsldppWorkflowTest::test_custody_return_filter_dropdown_only_includes_physical_return_statuses`, whose Return Inspection option assertion already failed before this revision. All 17 existing selected frontend checks passed.

The final verification covers `LateReturnAccountabilityTest`, `AccountabilityConsistencyTest`, `RoleBasedSignatureTest`, `RsldppWorkflowTest` and `AccountabilityDocumentLabelingTest`. Current and legacy Head decisions reject missing/unaccepted consent; both Confirm and Clear are covered. Billing negative checks verify unchanged case fields, financial rows, restrictions, snapshot/document/file counts, audits and notification records. Positive checks verify the authenticated Head's snapshot and preserve the billing/payment, sanction and physical compliance paths. Both existing Head view entry points render the consent hook with actual case values, and Officer views do not render the Head dialog.

New fixture corrections used the application's existing `notification_events`/`notification_deliveries` tables, supplied the focused case query and compared the late-return record after reloading its persisted SQLite values. The final no-consent billing check passed separately with 60 assertions after that last fixture correction. No production behavior or existing assertions were weakened to resolve fixture differences. `final-verification.json` combines the unique related checks and that rerun; the original suite and baseline reports remain available.

All 29 selected frontend checks passed with zero failures/skips, including all 12 new consent interactions. Run the new DOM checks with `ACCOUNTABILITY_SIGNATURE_JSDOM` set to an installed `jsdom` module, or make that module available through Node's normal resolution. The test file reports an explicit skip if the optional DOM dependency is absent; it was present for this verification.

The native application's `/login` and the new consent JavaScript asset responded HTTP 200 without restarting or reconfiguring the server. The browser testing connection failed while claiming its tab (`No handler registered for method: claimUserTab`), so no new authenticated browser screenshots or actual native-dialog visual checks are claimed. Approved generated previews remain illustrative.

No migrations, live test records, account changes, signature registrations or deployment were performed for this revision. Tests use the disposable in-memory SQLite database. Evidence is under `C:/Users/Admin/.codex/tmp/ictu-signature-consent-20261116`.
