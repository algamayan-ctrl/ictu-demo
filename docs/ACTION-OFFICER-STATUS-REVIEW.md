# Action Officer status review

Date: 28 October 2026

Implemented locally on 28 October 2026 after the user confirmed the simplification and preservation of case-specific information. The SAO list/filter presentation is updated on the local demo at port 8080. Saved workflow states, permissions, and transaction processing are retained.

## Finding

The supplied screenshots show six Release filter statuses and fourteen Request Records filter statuses, excluding All statuses. The count alone is not a workflow defect. The difficulty is that the lists mix processing stages, required actions, timing exceptions, and a document-related acronym. The same operational state also has different names on different pages.

Request Records can additionally display For Approval when a record is awaiting the Head. This is a real distinction from Action Officer verification and must remain identifiable even if both are grouped under Under Review in the overview.

## Release: four display statuses

| Display label | Existing labels grouped or retained | Meaning |
| --- | --- | --- |
| For Preparation | For Pickup Scheduling; For Item Preparation; Preparing for Release | The pickup requirements or item preparation are incomplete. A missing pickup schedule must remain an explicit warning in the row and detail page. |
| Pickup Scheduled | Pickup Scheduled | The items are prepared, but the pickup window has not opened. |
| Ready for Release | Ready for Release | The items are prepared and the pickup window is open. |
| Pickup Missed | Pickup Missed | The pickup window has expired and requires SPMU review. |

Pickup Scheduled and Ready for Release are distinct in the current implementation. Combining them indiscriminately would hide whether physical release is currently allowed. Preparing for Release is the generic fallback after the more specific preparation/schedule conditions; it does not need its own normal filter option.

Preparation grouping is applied to the Release list only. Automatic scheduling, schedule-exception review, item preparation, pickup timing guards, and the physical release confirmation remain separate requirements.

## Request Records: nine overview filter groups

| Filter group | Existing overview labels or states covered |
| --- | --- |
| Under Review | For Verification; For Approval. Identify the current reviewer in the row or request details. The dedicated For Verification queue remains unchanged. |
| Needs Revision | Needs Revision |
| For Release | For Issuance; Pickup Scheduled; Ready for Pickup; unclaimed release records |
| On Custody | Items issued and not yet returned |
| Return Processing | Return Inspection and unfinished applicable return documentation |
| Accountability Processing | Filter category for unresolved accountability cases. Each row must retain its specific case reason and current action; the category alone is not a sufficient row status. |
| Completed | All applicable completion requirements are satisfied |
| Cancelled | Cancelled |
| Rejected | Rejected |

The specific preparation/pickup statuses remain available in Release. Request Records is the lifecycle overview, so it does not need to repeat all operational sub-stages in its main dropdown.

The SAO Request Records filter has these nine groups plus Needs Attention and All statuses. Rows retain the actual status, including For Verification, For Approval, Ready for Release, Pickup Missed, and the specific accountability stage. Search also matches the displayed status and case details. Other roles retain their existing record-list presentation.

Staff list wording is aligned for Ready for Release, Pickup Missed, and Return Processing. Return Inspection still describes the actual inspection task.

## Confirmed clarification: retain case-specific information

The user confirmed that simplifying the filter must not make the Action Officer unable to identify late-return, overdue, property, payment, or compliance cases.

- Accountability Processing may group records in the filter. Do not replace each record's specific state with that generic label alone.
- Overdue identifies an outstanding physical return after the deadline. Keep it visible as a physical-return warning; it does not by itself establish a confirmed accountability decision.
- Late Return Review identifies an unresolved review or assessment after a late return. Returned Late by itself is a timing result and must not create a new open case or imply that a resolved case is still awaiting action.
- Property Case Review identifies a recorded property finding. Preserve the actual recorded damage, loss, or other finding so a property case cannot be mistaken for a late-return case.
- For Payment or Payment Verification identifies the current financial step. Preserve the related case reason alongside it.
- For Compliance identifies required corrective action. Preserve the recorded repair, replacement, recovery, or other approved requirement and its actual verification state; do not invent a decision from the category name.
- If more than one issue exists, retain both the physical-return warning and the applicable case information. Simplifying the filter must not hide one behind the other.

These display requirements are implemented without new saved transaction states or additional approval steps. Damage/loss findings and recorded repair/replacement requirements remain visible alongside the current case stage. Simultaneous late-return and property cases retain both sets of information. Existing payment/compliance verification details are preserved.

## Timing and exception information must remain visible

- Overdue: items are still outstanding after the return deadline. Keep a prominent warning; it must not disappear under a generic stage.
- Returned Late: a historical return-timing result. It does not mean the same thing as Overdue. A fully cleared late return may be Completed with a Returned Late badge. Unfinished documentation or accountability must still prevent a Completed label.
- Pickup Missed: a release exception. Keep a visible badge in Request Records and the dedicated filter in Release.
- A Needs Attention overview filter can find actionable overdue or missed-pickup cases without creating additional lifecycle statuses. A resolved late return should not be treated as requiring attention solely because it was late.
- Existing late-return dates, accountability decisions, document requirements, stock movements, and recorded history must be retained.

## Other inspected queues

- Return retains three filters: On Custody, Return Processing, and Overdue. These represent different conditions and do not require a count reduction. The existing Return Inspection filter key is preserved.
- For Verification has For review and Needs attention choices. These are review categories, not additional transaction milestones.
- Laundry uses Awaiting Laundry Form and For Return Recording for active records. Updating Record appears only when an older record is awaiting automatic availability reconciliation; it does not create another SAO review task. Filter keys and the accomplished-form requirement are retained.
- Completed Laundry uses Return Result: Available for Borrowing and With Return Findings. Recorded late or adverse findings remain visible without implying that a previously settled accountability case is still open. The redundant Completed filter is removed; older records without detailed results remain visible under All results.
- View Record shows one Return Details card with the form, recorded dates, quantities, findings when present, and a link to the existing Return form when action is needed. The offline-work tracker and duplicate completion summary are removed. Available quantity reads completed_quantity, rather than all received linen, so damaged linen is not counted as serviceable.

## Source evidence

- `resources/views/custody/index.blade.php`: Release/Return filter lists, operational row labels, separate schedule and preparation facts.
- `app/Models/CustodyTransaction.php::workflowStatus()`: preparation, future/open/expired pickup windows, completion and accountability conditions.
- `resources/views/requests/index.blade.php`: Action Officer status mapping, supported filter labels, SLDDP grouping, late-return display precedence, and pending Head approval.
- `app/Services/ReturnMetricsService.php::state()`: distinction between currently overdue and returned late.
- `resources/views/approvals/index.blade.php`: Action Officer verification review categories.
- `resources/views/laundry/index.blade.php`: active laundry status mapping and filter list.
- `app/Support/ActionOfficerStatusPresentation.php`: read-only SAO categories, specific row labels, late-return timing, and case details.

## Verification of the local implementation

- 101 distinct focused feature cases passed across the related run and the corrected-fixture rerun: 10 new status-presentation cases and 91 existing release, role-isolation, late-return and linen-return cases.
- Existing frontend checks: 206 passed, 1 optional fixture skipped, 0 failures.
- New cases cover schedule/preparation grouping, future/open/expired pickup windows, reviewer identity, simultaneous damage and late return, overdue property, payment stages, cleared late returns, and read-only record snapshots.
- The local app was rebuilt and recreated alone with migrations disabled. The running presenter and three changed views match the workspace sources. All 22 captured transaction/accountability tables and original Borrower Slip files match before/after snapshots.
- Browser checks confirmed 4 Release statuses, 9 Request Records groups plus Needs Attention, correct Under Review and Completed filtering, the empty Needs Attention result for the two current records, and the simpler Laundry labels. The existing pending record still displays For Verification while filtering under Under Review.
- A later post-browser snapshot shows all 21 non-audit business/accountability tables and original slips unchanged. The audit table gained three normal SESSION_IDLE_TIMEOUT entries for expired sessions. Its original 46 events are preserved; no workflow decision or transaction change was recorded by this verification.
- No production deployment, database reset/migration/reseed, commit, or push was performed. These selected checks do not constitute a full-system test or institutional sign-off.
