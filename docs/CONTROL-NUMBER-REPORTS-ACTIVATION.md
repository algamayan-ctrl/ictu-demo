# Academic-year Control Number activation

The approved numbering code is staged. Applying code does not migrate the PHP database or enable the new numbering scheme.

The user will configure Academic Periods through the existing Configuration page. Use actual academic-year labels and term dates. Each submission date must resolve to exactly one academic year, from its earliest configured term start through its latest configured term end. Missing or overlapping years block allocation rather than guessing a reset date.

## Before activation

1. Finish calendar configuration and review the current working copy and verification results.
2. Back up the database and protected document storage. Pause request creation/submission while mapping historical identifiers.
3. Review pending migrations. Install the additive `2026_11_14_000001_create_academic_request_numbering_tables.php` migration separately from implementation review. Do not run a blanket migration without inspecting other pending changes.
4. Generate a read-only preview: `php artisan requests:review-numbers --output=NEW_FILE.json`. The output directory must already exist and the file must not exist.
5. Review every old/new number, academic year, sequence and unresolved record. Numbers use the original first submission, chronological order and request ID as the stable tie-breaker. Unsubmitted drafts consume no number. Corrections and later resubmissions retain the assigned number.
6. Review dependent outputs if records have been added since the initial check. Original uploaded/signed files and signature snapshots remain preserved. Historical PDFs already downloaded or printed cannot update themselves; replacement controlled copies must pass the existing authorized generation/signing process. Do not copy an old signature onto modified content. Fresh report/print/download generation reads the current identifier.

The initial read-only inspection found zero request headers and no Academic Periods in the PHP database. There were therefore no historical request outputs to replace at that inspection. Recheck at activation.

## Apply the reviewed map

Use `php artisan requests:review-numbers --apply --expect=FINGERPRINT` with the fingerprint of the reviewed preview. A changed calendar or request map invalidates that fingerprint. Unverifiable submissions and conflicting historical references block activation.

The transaction records old identifiers as searchable aliases, journals the map, initializes the academic-year counters and enables first-submission numbering. Keep the returned batch ID with the database backup. Confirm a draft displays “Control No. pending submission,” then verify an explicitly signed first submission receives `BR-YYYY-MM-N` without leading zeros. No live activation was performed during implementation.

## Reversal

`php artisan requests:review-numbers --rollback=BATCH_ID` reverses an unchanged batch only before any new request headers or assignments are created. It restores old numbers and prior counters, removes that batch's aliases/assignments and disables the switch. A changed map or later activity requires a separate reviewed recovery; the command will refuse to discard it. Reverse an applied batch before rolling back its schema.

## Protected behavior

Internal request IDs, custody/return references, approval decisions, stock calculations, account permissions, existing signature consent, original evidence and document masters retain their existing behavior. This activation procedure changes request identifiers and their traceable references only.
