# S1-ANALYTICS-01: descriptive analytics and recorded scheduled demand

Status: FOR REVIEW. Core automated checks pass; full Analytics/visual/deployment gate: NOT READY. See ANALYTICS-QA-BACKLOG.md for explicit remaining cases.

## Requirements and scope

Objective: remove active predictive analytics and expose only observed operational metrics and demand represented by saved borrowing requests. Manuscript paragraph67 explicitly excludes predictive analytics/AI; paragraphs75/77 specify BPR/XP. The user's confirmed decision retains descriptive analytics and scheduled demand. Existing approved borrowing, cancellation, release, affiliation and authority contracts remain authoritative. This increment uses dependency mapping, failing acceptance tests, a small implementation, regression, and review; operational KPI measurement and ISO/IEC25010:2023 Functional Suitability/Interaction Capability evaluation remain separate gates.

Actors/permissions: the existing Analytics workspace is for Admin/SPMU Head. Borrowers, SAO and other roles cannot acquire Analytics/Reports access through this change. Admin observes records; no approval, release, cancellation or stock mutation occurs here. A Head can view observed historical demand/returns/inventory, inspect scheduled counts and filter recorded bookings without seeing predicted activity.

Preconditions: authorized active account, existing request/version schema and reporting-period configuration. Postconditions: read-only counts reconcile to current saved versions; no workflow, stock, document, signature, audit or notification record is changed by reading analytics.

Confirmed business rules: no weighted-moving-average predictions, projected busy periods, forecast readiness, expected future equipment quantities, estimated days until stock exhaustion or risk bands calculated from projected depletion in active views/detail/report links. Historical comparisons, observed peak buckets and current stock thresholds are descriptive and retain their existing definitions; they are not institutional KPI targets. Pending bookings are explicitly distinguished from approved requests and are never represented as reservations or physical release. Draft, unsigned, rejected, cancelled and expired requests cannot count as scheduled demand. Completed/physically issued custody must not appear as outstanding scheduled demand. Corrected versions are counted once, using the current version. External companies must not become fabricated CSPC divisions/offices; explicitly label External and Unclassified where needed.

Implementation detail: preserve the existing scheduled next-window convention independently of ForecastService. A complete selected calendar month maps to the next complete month; other windows map to the immediately following inclusive window of equal days. Print these exact dates. This is an existing display convention, not a new institutional borrowing-period rule. Unit/borrower pickers on Scheduled Demand must use schedule dates, not submission dates. Invalid/unknown links fall back safely; legacy forecast section bookmarks open Scheduled Demand, while predictive/depletion detail keys cannot execute projections.

Inputs/outputs: reporting period, saved division/unit and legitimate scoped borrower; outputs are current/pending/approved request counts and saved affiliation breakdowns with exact date basis. Empty records show zero/no records, not a forecast or an invented KPI denominator. Database impact: no migration, seed, schema change or production data write. State transitions: none; analytics only observes existing states.

Failures/risks: stale versions, repeated items inflating request counts, legacy null submission timestamps, external null units, cancelled/issued custody, forged filters/array parameters, malformed detail keys and zero denominators. Preserve employee behavior and existing report source semantics. Missing institutional inventory/KPI/hosting references prevent institutional certification; do not invent targets, fees, domains or operating hours.

## Dependency map before application changes

| Entry point | Existing dependency/issue | Intended change |
|---|---|---|
| AnalyticsController sections + Overview | ForecastService injected; forecast summary; predictive tab invokes demand/division/unit/equipment/busy projections | Remove active forecast injection/calls; Scheduled Demand tab/summary uses actual records |
| AnalyticsService::scheduledDemand | Current version/schedule join already exists; null/external affiliations disappear from breakdowns; no explicit approval split | Keep date scope, exclude finished physical custody; truthful breakdowns and pending/approved split |
| Scheduled filters/picker | Existing controller scopes by filing date for every tab | Use scheduled date scope for scheduled tab/picker |
| Inventory Health | stockCoverage estimates depletion days/risk from historical usage | Remove active projected coverage output; retain current stock and observed utilization |
| AnalyticsDetailService + AnalyticsCardDetailService | Both inject forecasts; forecast/coverage keys can invoke projections outside the tab | Disconnect unsupported keys and forecast injection; actual scheduled detail only |
| Report analytics detail entry point | Shares AnalyticsDetailService/card descriptors | Unsupported projection keys cannot produce a dataset/export; preserve descriptive report contracts |
| ForecastService / old predictive partial / forecast tests | Legacy computation and historical expectations | Preserve as inactive audit material during this increment; no production consumer. Removal/reconciliation of obsolete internals is a separately visible cleanup, never described as full-suite success |
| Operational/KPI services | ReturnMetricsService, ReportingPeriodService, InventoryService; actual request/custody/audit data | Preserve current definitions; institutional KPI target confirmation and full measurements remain separate |

## Acceptance and dependencies

Tests must fail against the original active forecast/coverage routes first. Verify every supported section is forecast-free even with dense history; legacy section/detail/card/report keys cannot activate projections; a rejecting ForecastService test double sees no calls. Verify same-date boundaries, next-month/leap-year windows, current-version deduplication, explicit pending/approved counts, rejected/cancelled/draft/issued exclusions, saved employee/external/unclassified affiliation, picker/filter reconciliation, invalid/forged filters, unauthorized access, empty datasets and zero business writes. Retain descriptive analytics/report and latest registration/routing/cancellation acceptance. SQLite and MariaDB11.4 must agree.

No concurrent inventory test is needed for a read-only aggregate; existing critical reservation/cancellation tests remain regression dependencies. Actual Cloud/SMTP/OAuth/physical phone/printed institutional acceptance is not implied by local tests.

## Deployment dependencies

Laravel/PHP, Bootstrap/JS, MariaDB11.4, Git, VS Code and Docker remain the approved architecture. A separate Laravel Cloud compatibility register will distinguish official platform facts, actual application dependencies, institutional form fields and confirmed institutional requirements. The Hosting DOCX, nine-deliverable ICTU letter and TD RSC PDF are unavailable in this mirror; the reported `Bolivia` title mismatch remains unverified. No deployment, database-engine substitution or public upload is authorized by this analysis.

## Verification evidence

Evidence is stored in the project workspace under sprint1/analytics01. The initial27-case run had6 pass,19 failures and2 errors (one missing new method, one corrected new-fixture field error). Final focused30/30 cases pass with236 assertions on both SQLite and MariaDB11.4.12:29 feature cases plus the divergent-version-ID fixture guard.453 related cases pass in the broader engine runs; broad454-case attempts retain the subsequently corrected fixture quantity-type assertion failure, so there is no claimed clean single454/454 run. The selected scope includes descriptive/report, signature, approval, SAO cancellation and no-auto-cancellation dependencies; it is not the complete application suite.

Actual separate Admin UI showed Scheduled Demand3, pending2, approved1, one employee unit, saved external agency and Unclassified affiliation, with the existing Admin navigation and no Release & Return action. These were synthetic status fixtures, not actual workflow approval or institutional inventory/KPI data. The screenshot exposed white-on-white KPI text; the scoped analytics-scheduled-count style now uses the existing theme heading color/metric typography. Final desktop/mobile pixel verification remains FOR REVIEW after browser protocol/startup timeouts; no mobile success is claimed. Owned UI configuration caching is outside the test clone and main preview; main cache/environment/database are not changed. Source preservation and cleanup receipts are separate.
