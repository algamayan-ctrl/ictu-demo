# Borrowing request upload correction — 16 November 2026

## Reported problem and cause

The native PHP instance at `http://127.0.0.1:8001` rejected a 16,562,834-byte multipart request before routing or database processing. Its runtime had `post_max_size=8M` and `upload_max_filesize=2M`, while the application supports an ICTU-configured policy of up to 64 MB per file.

The current database setting is **5 MB per file**. This policy has not been increased. A file over 5 MB must be replaced or reduced unless ICTU separately changes that setting. A higher transport ceiling permits Laravel to enforce the configured policy; it does not override it.

## Changes

- Native runtime configuration: `upload_max_filesize=64M`, `post_max_size=130M`, `memory_limit=256M`, and startup errors logged instead of printed into HTTP responses. The original runtime configuration is preserved outside the repository.
- Restarted only the identified native ICTU server on port 8001 to load those settings. Its existing 180-second execution limit remains.
- Docker source configuration: increase the total-body limit from 128M to 130M, providing multipart overhead above two 64 MB attachments. No Docker container was rebuilt or restarted.
- Borrowing Request Letter and Permission to Conduct inputs now check the displayed configured limit on selection and before saving/submitting. An inline message identifies an oversized attachment without clearing other form fields or automatically submitting/signing anything.
- Existing Submit readiness and final confirmation reject invalid attachments. The request steps, document requirements, signature consent, permissions and business validation remain intact.
- Turning student activity off disables its hidden PTC upload without clearing the selected file. Turning it back on restores validation, so a hidden invalid attachment cannot block an otherwise applicable submission or be uploaded accidentally.
- Oversized requests that exceed the total server ceiling receive an HTML or JSON HTTP 413 response with clear guidance and no debug stack trace. The HTML error page does not require a session, authentication or database query.

## Progress and verification

- [x] Preserve 878 working-copy files, hashes and Git status; preserve native php.ini and server state.
- [x] Record the focused baseline: 45 backend tests passed, 666 assertions.
- [x] Correct and verify the active native runtime limits.
- [x] Verify exact per-file boundaries, replacement/clear behavior, unchanged entered details, invalid-submit blocking and allowed combined attachments.
- [x] Run the final 17 upload/submit interaction checks: all passed, including inactive PTC handling and the unchanged signature/duplicate-submit controls.
- [x] Verify the HTML/JSON oversize response tests after correcting the standalone error view: 2 passed, 11 assertions.
- [x] Send an anonymous 16,562,834-byte upload to port 8001: it reaches the normal CSRF protection (419) instead of the old 413 size block. This is not an authenticated submission or an approval to exceed the per-file policy.
- [x] Verify an actual HTTP 413 response with a 2 MB request against an isolated PHP server configured with a 1 MB body limit and an in-memory test database. The response contains the friendly message and no PHP warning or debug trace. Stop the temporary server afterward.
- [x] Verify the primary login page still responds with HTTP 200.
- [x] Final full frontend suite: 262 cases, 257 passed, 2 failed, 3 skipped. The same two failures reproduce in the preserved baseline: 255 cases, 250 passed, 2 failed, 3 skipped. All seven added upload checks pass with the DOM dependency enabled.
- [x] Full backend suite: 1,865 cases, 1,828 passed, 36 failed assertions and 1 error, 16,288 assertions. All four added upload/oversize cases pass. Every failing case reproduces against the pre-fix source under matching non-secret environment settings: 37 selected baseline cases, 37 matching failures, zero unmatched cases.
- [x] After the final inactive-PTC client guard, rerun the focused request/layout/oversize suite: 13 cases passed, 131 assertions. The final frontend run above includes that guard.
- [x] Verify the final file scope: exactly nine repository files (four modified, five added), no removed or unrelated files, and no new trailing whitespace. Native php.ini differs only in the four reported settings.
- [x] Confirm live state after the anonymous probes: zero borrowing requests and one stored file, matching the pre-fix counts; configured file limit remains 5 MB. Temporary probe server stopped; primary port 8001 remains available.

An attempted 131 MB native-server probe was cancelled to avoid holding the single-worker development server. It is not counted as a passed boundary test; the completed isolated HTTP test and framework tests verify the oversize handler.

## Existing frontend failures

- `reported discrepancy blocks preparation and preserves the AO recheck path`
- `Borrower Request Records use the same short status set and filter by the displayed status`

These appear in both the current working copy and the saved pre-fix copy. Their associated product flows were not changed to satisfy stale assertions as part of this upload correction.

## Existing backend failures found by the broad review

All 37 cases below reproduce in the saved source before the upload correction. They remain unresolved and require separate review; passing upload checks does not clear them. Some assertions concern earlier UI/role expectations or local mail/domain configuration, so they must not be treated as authorization to change approved workflows.

| Test class | Failing cases, including errors |
|---|---:|
| AccountSettingsRoleTest | 1 |
| AdminInstitutionalAccountVerificationTest | 4 |
| AdminUserManagementTest | 6 |
| AnalyticsSectionsTest | 1 |
| AuditRecoveryTest | 1 |
| BorrowerSelfRegistrationTest | 2 |
| DeliveryRecordsTest | 3 |
| ExternalAdminAccountEditTest | 1 |
| GoogleAuthTest | 1 |
| GoogleRegistrationOnboardingTest | 1 |
| IctuTechnicalTurnoverTest | 1 |
| InventoryAdjustmentWorkflowTest | 1 |
| NotificationReliabilityTest | 4 |
| ProductionCorrectnessHardeningTest | 2 |
| ProtectedFilePreviewTest | 1 |
| ReportSystemPreviewTest | 2 |
| RsldppWorkflowTest | 1 |
| SaoConfirmedCancellationTest | 1 |
| SinglePortalRoleIsolationTest | 1 |
| SpmuRoleSeparationTest | 1 |
| UniversalDocumentActionUiTest | 1 |

Exact case names and comparison receipts are in `full-results.json`, `baseline-failures-junit.xml` and `baseline-comparison.json` in the evidence directory. No new unexplained failing case was found in this revision.

## Evidence and scope

Evidence: `C:/Users/Admin/.codex/tmp/ictu-upload-fix-20261115`. The directory date records when the work began; the review continued past midnight in Asia/Manila.

Changed application parts are limited to the request form's upload feedback/readiness, the new upload-check script, the oversize exception response/view and upload transport configuration. Regression tests and this tracker accompany those changes. No live migration, seed, historical record renumbering, signature application, approval, stock movement, release, return or billing action was performed.

Automated checks and transport probes do not certify that every application function is perfect. Any remaining full-suite failures must be reported separately from the corrected upload issue; live browser visual acceptance is not claimed.
