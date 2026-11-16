# Control Number and Reports revision tracker

Approved on 14 November 2026: the Control Number and Reports proposal, including the opened More filters popup. Preserve the existing working copy, roles, workflow mutations, signatures and panel corner styles.

Latest UI correction: report switching now reaches the existing preview updater, preserving each report's own builder, filters and title. The actions are **Print** and **Generate**, aligned at the far right of the Reports heading; the original heading font size is restored. Ten integrated interaction checks and two server-rendering checks passed. Actual browser switching from Summary to Inventory and All Transactions was verified with isolated PHP fixture data. See [correction details](REPORTS-UI-CORRECTION-20261114.md).

| Status | Milestone |
|---|---|
| Complete | Review DOCX, source, six clarification answers and visual concepts |
| Complete | Preserve 853 source files and Git status outside the repository |
| Complete | Baseline checks: 24 passed, 93 assertions |
| Complete | Read-only prerequisites: zero request headers; no configured Academic Periods |
| Complete | Compact shared toolbar and staged More filters popup |
| Complete | Request-based All Transactions and full print/download parity |
| Complete | Summary first-review timestamps and duration |
| Complete | Category, availability, period and case-status filter revisions |
| Complete in code | First-submission academic-year numbering and reversible historical mapping |
| Complete | Isolated regression, concurrency, export and DOM interaction checks |
| Pending user configuration | Academic Periods: user will configure through the existing Configuration page |
| Pending separate rollout | Additive live migration, reviewed mapping and numbering activation |
| Complete | Actual desktop Reports and opened More filters screenshot on an isolated PHP fixture |
| Pending browser access | Phone/dark-mode visual checks and final visual sign-off |

Snapshot and evidence: `C:\Users\Admin\.codex\tmp\ictu-control-reports-20261114`.

The user confirmed they will configure Academic Periods through the existing Configuration page. No calendar dates were inferred. Live migration/activation remains separate from implementation review; the current number flow remains in effect until that reviewed activation.

Verification: 174/176 report/numbering regression tests passed; the same two failures occur in the preserved baseline. The final targeted run passed 58/58 tests with 755 assertions. Additional rollback/collision guards passed 2/2, and the final selector/report checks passed 31/31 with 248 assertions. Eight popup DOM checks passed. Four concurrent workers assigned 40 unique sequential numbers while excluding four drafts. Print, CSV, PDF, Word and Excel carried all matching rows across pagination; PDF text and visual rendering were checked. All 215 Blade templates compiled, and rendered script/JSON/CSS validation found no syntax failures.

Actual desktop review caught and corrected a missing All Transactions selector entry and the toolbar's inherited layout direction. The anchored popup opened correctly in Chrome and Escape closed it. The screenshot is saved in the proposal artifact directory as `actual-more-filters-desktop.jpg`. Phone/dark-mode checks remain pending: Computer Use stopped when it could not confidently determine the browser URL. No browser-control workaround was attempted after that stop.

See [activation instructions](CONTROL-NUMBER-REPORTS-ACTIVATION.md) before any live numbering change.
