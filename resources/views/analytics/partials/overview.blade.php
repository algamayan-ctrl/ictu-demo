@php
/*
 | Overview: what is happening right now.
 |
 | Layout only. Every figure below is computed in AnalyticsService and
 | handed to the view by AnalyticsController; nothing on this page is
 | calculated from scratch here, and nothing is fabricated when the data
 | is thin - an absent reading is stated as absent.
 |
 | A card's primary click opens an Analytics detail for that figure, not
 | the record module: clicking a number is a question about the number.
 | Reports is offered inside the detail as a secondary step.
 */
 use App\Support\AnalyticsDetailLink;

 $division = $selectedDivision === 'all' ? null : $selectedDivision;
 $unit = $selectedUnit === 'all' ? null : $selectedUnit;

 $lowAvailabilityPercent = (int) ($lowAvailability['threshold'] * 100);

 /* Compact Overview-only duration label for Operational Processing. */
 $formatDuration = static function (?float $minutes): string {
 if ($minutes === null) {
 return '—';
 }

 if ($minutes < 60) {
 return number_format($minutes, $minutes < 10 ? 1 : 0).' min';
 }

 if ($minutes < 1440) {
 return number_format($minutes / 60, 1).' hr';
 }

 return number_format($minutes / 1440, 1).' d';
 };

 /*
 * unitRankings() returns the top units per division. Ranking them against
 * each other is a re-read of rows already fetched, not a second query, and
 * keeping each division's top five guarantees the combined top five is
 * complete. The bar width is re-normalised against the overall leader so
 * the two ranking cards use one scale.
 */
 $rankedUnits = collect($units['columns'])
 ->flatMap(fn (array $column): array => collect($column['units'])
 ->map(fn (array $row): array => $row + [
 'division_code' => $column['code'],
 'division_label' => $column['label'],
 ])
 ->all())
 ->sortByDesc('count')
 ->take(5)
 ->values();

 $topItems = array_slice($equipment['items'], 0, 5);

 /*
 * Priority rows.
 *
 * Every row restates a figure the controller already handed this view, so
 * the panel costs no extra query and can state nothing the services do not
 * compute. A reading that does not exist is omitted rather than filled in.
 *
 * Rows are ordered by operational weight, not by a fixed list: a risk that
 * is live right now outranks an informational pattern. Inventory sits at
 * weight 20 while it is a risk; with nothing below the threshold the same
 * row is merely reassurance, so it drops below the demand insight instead
 * of holding a risk slot.
 */
 $priorityRows = [];

 /* Weight 10 - live risk. Silent at zero: "0 overdue" is not a signal. */
 if ($overview['needs_follow_up'] > 0) {
 $overdueCount = $overview['needs_follow_up'];

 $priorityRows[] = [
 'weight' => 10,
 'tone' => 'urgent',
 'icon' => 'warning',
 'title' => 'Currently overdue',
 'text' => $overdueCount.' '.($overdueCount === 1 ? 'borrowing is' : 'borrowings are')
 .' currently past '.($overdueCount === 1 ? 'its return date' : 'their return dates').'.',
 'href' => AnalyticsDetailLink::to('follow-up', 'overview', $periodSelection, $division, $unit),
 ];
 }

 /*
 * Weight 15 - accountability still open. returns() counts cases opened in
 * the selected period that remain unresolved; silent at zero like overdue.
 */
 if (($returns['open_cases'] ?? 0) > 0) {
 $openCaseCount = (int) $returns['open_cases'];

 $priorityRows[] = [
 'weight' => 15,
 'tone' => 'warning',
 'icon' => 'accountability',
 'title' => 'Unresolved accountability',
 'text' => $openCaseCount.' accountability '.($openCaseCount === 1 ? 'case' : 'cases')
 .' opened in this period '.($openCaseCount === 1 ? 'remains' : 'remain').' unresolved.',
 'href' => AnalyticsDetailLink::to('card', 'overview', $periodSelection, $division, $unit, ['for' => 'returns.issues']),
 ];
 }

 /* Weight 20 - inventory risk, only while there is one. */
 if ($lowAvailability['count'] > 0) {
 $priorityRows[] = [
 'weight' => 20,
 'tone' => 'warning',
 'icon' => 'inventory',
 'title' => 'Low availability',
 /* The service names the worst-affected item; use it when it stands alone. */
 'text' => $lowAvailability['count'] === 1 && $lowAvailability['watch']
 ? $lowAvailability['watch']['name'].' is at or below '.$lowAvailabilityPercent.'% usable stock.'
 : $lowAvailability['count'].' item types are at or below '.$lowAvailabilityPercent.'% usable stock.',
 'href' => AnalyticsDetailLink::to('low-availability', 'overview', $periodSelection, $division, $unit),
 ];
 }

 /* Weight 30 - demand pattern for the selected period. */

 /*
 * Weight 35. equipment() sums actual_released_quantity, so this is
 * released volume - not how often an item was asked for. The title says
 * released for that reason.
 */

 /* Weight 36 - the same inventory reading, reassurance rather than risk. */
 if ($lowAvailability['count'] === 0) {
 $priorityRows[] = [
 'weight' => 36,
 'tone' => 'neutral',
 'icon' => 'check-circle',
 'title' => 'Inventory availability',
 'text' => 'No item type is currently at or below '.$lowAvailabilityPercent.'% usable stock.',
 'href' => null,
 ];
 }

 /*
 * Weight 40. A null on-time rate means nothing has come back yet, which is
 * a different reading from 0% on time - only one of them is true here. No
 * service defines a compliance threshold, so the row states the measured
 * rate in a neutral tone rather than grading it against a made-up bar.
 */
 $priorityRows[] = $returns['on_time_rate'] === null
 ? [
 'weight' => 40,
 'tone' => 'neutral',
 'icon' => 'clock',
 'title' => 'Return compliance',
 'text' => 'No completed returns in this period; compliance cannot yet be measured.',
 'href' => null,
 ]
 : [
 'weight' => 40,
 'tone' => 'compliance',
 'icon' => 'check-circle',
 'title' => 'Return compliance',
 'text' => $returns['on_time'].' of '.$returns['completed'].' completed '
 .($returns['completed'] === 1 ? 'return was' : 'returns were').' on time ('
 .$returns['on_time_rate'].'%); '.$returns['late'].' '
 .($returns['late'] === 1 ? 'was' : 'were').' late.',
 'href' => AnalyticsDetailLink::to(
 'card',
 'overview',
 $periodSelection,
 $division,
 $unit,
 ['for' => 'overview.return-compliance']
 ),
 ];

 usort($priorityRows, fn (array $a, array $b): int => $a['weight'] <=> $b['weight']);
 $priorityRows = array_slice($priorityRows, 0, 4);

@endphp

@include('analytics.partials.overview-summary-styles')

{{-- Headline figures ------------------------------------------------- --}}


<div class="analytics-kpis analytics-kpis-overview">
 <a
 class="analytics-kpi-card tone-stock"
 href="{{ AnalyticsDetailLink::to('requests', 'overview', $periodSelection, $division, $unit) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="requests" size="19" /></span>
 <span class="analytics-kpi-card-label">Total Requests</span>
 <strong class="analytics-kpi-card-value">{{ $demandSummary['requests'] }}</strong>
 <span
 class="analytics-kpi-card-note"
 title="Filed borrowing demand and review workload, including requests that were rejected. This is not a measure of asset usage."
 >Filed; excludes drafts, cancelled and expired</span>
 {{--
 Period-scoped, so it has a previous period to stand against. The
 three cards beside it are current-state and deliberately do not.
 --}}
 @include('analytics.partials.period-delta', ['comparison' => $demandComparison['requests'], 'deltaClass' => 'analytics-kpi-card-meta'])
 </a>

 <a
 class="analytics-kpi-card tone-custody"
 href="{{ AnalyticsDetailLink::to('currently-out', 'overview', $periodSelection, $division, $unit) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="custody" size="19" /></span>
 <span class="analytics-kpi-card-label">On Custody</span>
 <strong class="analytics-kpi-card-value">{{ $overview['on_custody'] }}</strong>
 <span class="analytics-kpi-card-note">Released, not yet returned</span>
 </a>

 <a
 class="analytics-kpi-card tone-overdue"
 href="{{ AnalyticsDetailLink::to('follow-up', 'overview', $periodSelection, $division, $unit) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="warning" size="19" /></span>
 <span class="analytics-kpi-card-label">Overdue</span>
 <strong class="analytics-kpi-card-value">{{ $overview['needs_follow_up'] }}</strong>
 <span class="analytics-kpi-card-note">Borrowings past their return date</span>
 </a>

 <a
 class="analytics-kpi-card tone-stock"
 href="{{ AnalyticsDetailLink::to('card', 'overview', $periodSelection, $division, $unit, ['for' => 'inventory.available']) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="inventory" size="19" /></span>
 <span class="analytics-kpi-card-label">Available Stock</span>
 <strong class="analytics-kpi-card-value">{{ number_format((float) $inventorySummary['totals']['available']) }}</strong>
 <span class="analytics-kpi-card-note">Current stock across all inventory</span>
 </a>
</div>



{{-- Priority signals --------------------------------------------------- --}}
@if($detail)
 @include('analytics.partials.detail-panel')
@endif

<section class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="lightbulb" size="15" /></span>
 <div>
 <h2>Priority Insights</h2>
 <p>Shows what needs attention now and how returns performed this period.</p>
 </div>
 </header>

 <div class="analytics-card-body is-flush">
 <ul class="analytics-priority">
 @foreach($priorityRows as $row)
 <li class="analytics-priority-row tone-{{ $row['tone'] }}">
 @if($row['href'])
 <a href="{{ $row['href'] }}">
 <span class="analytics-priority-icon" aria-hidden="true"><x-icon :name="$row['icon']" size="15" /></span>
 <span class="analytics-priority-body">
 <span class="analytics-priority-title">{{ $row['title'] }}</span>
 <span class="analytics-priority-text">{{ $row['text'] }}</span>
 </span>
 </a>
 @else
 <div>
 <span class="analytics-priority-icon" aria-hidden="true"><x-icon :name="$row['icon']" size="15" /></span>
 <span class="analytics-priority-body">
 <span class="analytics-priority-title">{{ $row['title'] }}</span>
 <span class="analytics-priority-text">{{ $row['text'] }}</span>
 </span>
 </div>
 @endif
 </li>
 @endforeach
 </ul>
 </div>
</section>

@include('analytics.partials.overview-highlights')

{{-- Cross-tab executive summary -------------------------------------- --}}
<section class="analytics-card analytics-summary-shell">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="analytics" size="15" /></span>
 <div>
 <h2>Analytics Summary</h2>
 <p>Quick snapshot across key areas. Open a card for detailed analytics.</p>
 </div>
 </header>

 <div class="analytics-summary-grid">
 <a class="analytics-summary-card" href="{{ route('analytics.index', $carry + ['section' => 'demand']) }}">
 <span class="analytics-summary-icon" aria-hidden="true"><x-icon name="analytics" size="16" /></span>
 <span class="analytics-summary-title">Demand &amp; Utilization</span>
 <span class="analytics-summary-arrow" aria-hidden="true"><x-icon name="chevron-right" size="14" /></span>

 <dl class="analytics-summary-metrics">
 <div>
 <dt>Requested quantity</dt>
 <dd>{{ number_format((float) $demandSummary['requested_quantity']) }}</dd>
 </div>
 <div>
 <dt>Released quantity</dt>
 <dd>{{ number_format((float) $demandSummary['released_quantity']) }}</dd>
 </div>
 </dl>
 </a>

 <a class="analytics-summary-card" href="{{ route('analytics.index', $carry + ['section' => 'inventory']) }}">
 <span class="analytics-summary-icon" aria-hidden="true"><x-icon name="inventory" size="16" /></span>
 <span class="analytics-summary-title">Inventory Health</span>
 <span class="analytics-summary-arrow" aria-hidden="true"><x-icon name="chevron-right" size="14" /></span>

 <dl class="analytics-summary-metrics">
 <div>
 <dt>Available asset units (now)</dt>
 <dd>{{ number_format((float) $inventorySummary['totals']['available']) }}</dd>
 </div>
 <div>
 <dt>Asset units on custody (now)</dt>
 <dd>{{ number_format((float) $inventorySummary['totals']['on_custody']) }}</dd>
 </div>
 </dl>
 </a>

 <a class="analytics-summary-card" href="{{ route('analytics.index', $carry + ['section' => 'returns']) }}">
 <span class="analytics-summary-icon" aria-hidden="true"><x-icon name="custody" size="16" /></span>
 <span class="analytics-summary-title">Borrowing &amp; Return Performance</span>
 <span class="analytics-summary-arrow" aria-hidden="true"><x-icon name="chevron-right" size="14" /></span>

 <dl class="analytics-summary-metrics">
 <div>
 <dt>On-time returns</dt>
 <dd>{{ $returns['on_time_rate'] === null ? '—' : $returns['on_time_rate'].'%' }}</dd>
 </div>
 <div>
 <dt>Completed returns</dt>
 <dd>{{ number_format((int) $returns['completed']) }}</dd>
 </div>
 </dl>
 </a>

 </div>
</section>




{{-- OPERATIONAL PERFORMANCE OVERVIEW V1 START --}}

@php
    /*
     * Uses the exact output of:
     * AnalyticsService::processingPerformance()
     *
     * No timing is recalculated in the view.
     */
    $operationalSource =
        is_array($processingSummary ?? null)
            ? $processingSummary
            : [];

    $approvalMinutes =
        $operationalSource['approval_minutes']
        ?? null;

    $approvalSamples =
        (int) (
            $operationalSource['approval_samples']
            ?? 0
        );

    $releaseMinutes =
        $operationalSource['release_minutes']
        ?? null;

    $releaseSamples =
        (int) (
            $operationalSource['release_samples']
            ?? 0
        );

    $pendingCount =
        (int) (
            $operationalSource['pending_count']
            ?? 0
        );

    $oldestPendingMinutes =
        $operationalSource['oldest_pending_minutes']
        ?? null;

    $operationalAvailable =
        (bool) (
            $operationalSource['available']
            ?? false
        );


    /*
     * Short duration label for Overview.
     */
    $formatOperationalDuration =
        static function ($minutes): string {

            if (
                $minutes === null
                || ! is_numeric($minutes)
            ) {
                return '—';
            }

            $minutes =
                max(
                    0,
                    (float) $minutes
                );

            if ($minutes < 1) {
                return '< 1 min';
            }

            if ($minutes < 60) {
                return round($minutes).' min';
            }

            if ($minutes < 1440) {

                $hours =
                    $minutes / 60;

                return
                    (
                        $hours >= 10
                            ? round($hours)
                            : round($hours, 1)
                    )
                    .' hr';
            }

            $days =
                $minutes / 1440;

            return
                (
                    $days >= 10
                        ? round($days)
                        : round($days, 1)
                )
                .' d';
        };


    $operationalCards = [
        [
            'label' =>
                'Average Approval Time',

            'value' =>
                $formatOperationalDuration(
                    $approvalMinutes
                ),

            'note' =>
                $approvalSamples > 0
                    ? 'Filing to final approval, from '
                        .$approvalSamples
                        .' approved '
                        .(
                            $approvalSamples === 1
                                ? 'request'
                                : 'requests'
                        )
                    : 'No request filed in this period has been approved yet',

            'icon' =>
                'approval',
        ],

        [
            'label' =>
                'Avg. Approval-to-Release',

            'value' =>
                $formatOperationalDuration(
                    $releaseMinutes
                ),

            'note' =>
                $releaseSamples > 0
                    ? 'Final approval to release, from '
                        .$releaseSamples
                        .' released '
                        .(
                            $releaseSamples === 1
                                ? 'request'
                                : 'requests'
                        )
                    : 'No request filed in this period has been released yet',

            'icon' =>
                'release',
        ],

        [
            'label' =>
                'Pending Approvals',

            'value' =>
                number_format(
                    $pendingCount
                ),

            'note' =>
                $pendingCount === 0
                    ? 'No requests are awaiting approval right now'
                    : (
                        $pendingCount === 1
                            ? '1 request in review right now, from any period'
                            : $pendingCount.' requests in review right now, from any period'
                    ),

            'icon' =>
                'pending',
        ],

        [
            'label' =>
                'Oldest Pending Age',

            'value' =>
                $formatOperationalDuration(
                    $oldestPendingMinutes
                ),

            'note' =>
                $pendingCount === 0
                    ? 'Not applicable because there are no pending approvals'
                    : (
                        $oldestPendingMinutes === null
                            ? 'Pending age is not available'
                            : 'Time since filing of the longest-waiting request'
                    ),

            'icon' =>
                'age',
        ],
    ];
@endphp


<style>
.analytics-page
.analytics-operational-performance {
    margin-top: 18px;
}

.analytics-page
.analytics-operational-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 11px;
}

.analytics-page
.analytics-operational-title {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}

.analytics-page
.analytics-operational-mark {
    display: grid;
    place-items: center;

    width: 31px;
    height: 31px;
    flex: 0 0 31px;

    border-radius: 9px;

    color: #1677c8;
    background: #edf6fd;
}

.analytics-page
.analytics-operational-mark svg,
.analytics-page
.analytics-operational-icon svg {
    width: 16px;
    height: 16px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.analytics-page
.analytics-operational-title h2 {
    margin: 0;

    color: var(--heading);

    font-size: 14px;
    font-weight: 800;
    line-height: 1.3;
}

.analytics-page
.analytics-operational-title p {
    margin: 2px 0 0;

    color: var(--text-muted);

    font-size: 10px;
    line-height: 1.4;
}


/* KPI GRID */

.analytics-page
.analytics-operational-grid {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0, 1fr)
        );

    gap: 10px;
}

.analytics-page
.analytics-operational-card {
    display: grid;

    grid-template-columns:
        auto
        minmax(0, 1fr);

    gap: 11px;

    min-height: 112px;

    padding: 14px;

    border: 1px solid var(--border);
    border-radius: 11px;

    background:
        var(
            --surface-elevated,
            #ffffff
        );
}

.analytics-page
.analytics-operational-icon {
    display: grid;
    place-items: center;

    width: 32px;
    height: 32px;

    border-radius: 9px;

    color: #1677c8;
    background: #edf6fd;
}

.analytics-page
.analytics-operational-copy {
    min-width: 0;
}

.analytics-page
.analytics-operational-label {
    display: block;

    color: var(--text-muted);

    font-size: 9px;
    font-weight: 800;
    line-height: 1.25;

    letter-spacing: 0.035em;
    text-transform: uppercase;
}

.analytics-page
.analytics-operational-value {
    display: block;

    margin-top: 5px;

    color: var(--heading);

    font-size: 22px;
    font-weight: 850;
    line-height: 1;

    font-variant-numeric:
        tabular-nums;
}

.analytics-page
.analytics-operational-note {
    display: block;

    margin-top: 7px;

    color: var(--text-secondary);

    font-size: 9.5px;
    line-height: 1.35;
}


/* INFORMATION NOTE */

.analytics-page
.analytics-operational-footnote {
    display: flex;
    align-items: flex-start;

    gap: 8px;

    margin-top: 10px;
    padding: 10px 12px;

    border-radius: 9px;

    color: var(--text-secondary);

    background:
        var(
            --surface-subtle,
            #f6f9fc
        );

    font-size: 9.5px;
    line-height: 1.4;
}

.analytics-page
.analytics-operational-footnote svg {
    width: 14px;
    height: 14px;

    flex: 0 0 14px;

    color: #1677c8;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}


/* RESPONSIVE */

@media (max-width: 920px) {

    .analytics-page
    .analytics-operational-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }
}

@media (max-width: 560px) {

    .analytics-page
    .analytics-operational-grid {
        grid-template-columns:
            1fr;
    }
}
</style>


<section
    class="analytics-operational-performance"
    id="operational-performance"
>

    <header class="analytics-operational-head">

        <div class="analytics-operational-title">

            <span
                class="analytics-operational-mark"
                aria-hidden="true"
            >
                <svg viewBox="0 0 24 24">
                    <path d="M4 18V9"></path>
                    <path d="M10 18V5"></path>
                    <path d="M16 18v-7"></path>
                    <path d="M22 18H2"></path>
                </svg>
            </span>

            <div>

                <h2>
                    Operational Performance
                </h2>

                <p>
                    Shows approval times and requests awaiting action.
                </p>

            </div>

        </div>

    </header>


    <div class="analytics-operational-grid">

        @foreach($operationalCards as $card)

            <article class="analytics-operational-card">

                <span
                    class="analytics-operational-icon"
                    aria-hidden="true"
                >

                    @if($card['icon'] === 'approval')

                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path d="M12 8v4l3 2"></path>
                        </svg>

                    @elseif($card['icon'] === 'release')

                        <svg viewBox="0 0 24 24">
                            <path d="M4 7h10v10H4z"></path>
                            <path d="M14 10h3l3 3v4h-6"></path>
                            <path d="M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4"></path>
                            <path d="M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4"></path>
                        </svg>

                    @elseif($card['icon'] === 'pending')

                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path d="M12 7v5"></path>
                            <path d="M12 16h.01"></path>
                        </svg>

                    @else

                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path d="M12 8v4"></path>
                            <path d="M12 12l3 2"></path>
                        </svg>

                    @endif

                </span>


                <div class="analytics-operational-copy">

                    <span class="analytics-operational-label">
                        {{ $card['label'] }}
                    </span>

                    <strong class="analytics-operational-value">
                        {{ $card['value'] }}
                    </strong>

                    <span class="analytics-operational-note">
                        {{ $card['note'] }}
                    </span>

                </div>

            </article>

        @endforeach

    </div>


    <div class="analytics-operational-footnote">

        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 11v5"></path>
            <path d="M12 8h.01"></path>
        </svg>

        <span>
            Averages cover requests filed in the selected period,
            using timestamps recorded by the system. Pending figures
            are current. These are operational analytics, separate
            from the formal manual-versus-system KPI evaluation used
            in the study.
        </span>

    </div>

</section>

{{-- OPERATIONAL PERFORMANCE OVERVIEW V1 END --}}



{{-- OPERATIONAL PERFORMANCE PARENT CARD V2 START --}}

<style>
/* ==========================================================
   OPERATIONAL PERFORMANCE
   MATCH ANALYTICS SUMMARY PARENT CARD
   ========================================================== */

.analytics-page
.analytics-operational-performance {
    margin-top: 18px;

    overflow: hidden;

    border: 1px solid var(--border);
    border-radius: 13px;

    background:
        var(
            --surface-elevated,
            #ffffff
        );

    box-shadow:
        0 1px 2px
        rgba(
            15,
            23,
            42,
            0.035
        );
}


/* ==========================================================
   HEADER
   ========================================================== */

.analytics-page
.analytics-operational-head {
    margin: 0;

    padding:
        14px
        16px;

    border-bottom:
        1px solid
        var(--border);

    background:
        var(
            --surface-elevated,
            #ffffff
        );
}


.analytics-page
.analytics-operational-title {
    align-items: center;
}


.analytics-page
.analytics-operational-mark {
    width: 31px;
    height: 31px;

    flex:
        0
        0
        31px;

    border-radius: 8px;

    color: #1677c8;

    background:
        #edf6fd;
}


.analytics-page
.analytics-operational-title h2 {
    margin: 0;

    font-size: 14px;
    font-weight: 800;
    line-height: 1.25;
}


.analytics-page
.analytics-operational-title p {
    margin:
        2px
        0
        0;

    font-size: 9.5px;
    line-height: 1.35;

    color:
        var(--text-muted);
}


/* ==========================================================
   INNER KPI AREA
   ========================================================== */

.analytics-page
.analytics-operational-grid {
    padding:
        13px
        12px
        12px;

    gap: 10px;

    background:
        transparent;
}


/*
 * Inner metric cards remain cards, just like the four
 * Analytics Summary workspace cards.
 */
.analytics-page
.analytics-operational-card {
    min-height: 108px;

    padding: 13px;

    border:
        1px solid
        var(--border);

    border-radius: 10px;

    background:
        var(
            --surface-elevated,
            #ffffff
        );

    box-shadow: none;
}


.analytics-page
.analytics-operational-card:hover {
    border-color:
        color-mix(
            in srgb,
            #1677c8 28%,
            var(--border)
        );
}


/* ==========================================================
   KPI TYPOGRAPHY
   ========================================================== */

.analytics-page
.analytics-operational-label {
    font-size: 8.5px;

    letter-spacing:
        0.035em;
}


.analytics-page
.analytics-operational-value {
    margin-top: 6px;

    font-size: 21px;
}


.analytics-page
.analytics-operational-note {
    margin-top: 7px;

    font-size: 9px;
    line-height: 1.35;
}


/* ==========================================================
   FOOTNOTE INSIDE THE SAME PARENT CARD
   ========================================================== */

.analytics-page
.analytics-operational-footnote {
    margin:
        0
        12px
        12px;

    padding:
        9px
        11px;

    border-radius: 8px;

    border:
        1px solid
        color-mix(
            in srgb,
            #1677c8 10%,
            var(--border)
        );

    background:
        color-mix(
            in srgb,
            #1677c8 4%,
            var(
                --surface-elevated,
                #ffffff
            )
        );
}


/* ==========================================================
   DARK MODE
   ========================================================== */

html[data-theme="dark"]
.analytics-page
.analytics-operational-performance,

html[data-theme="dark"]
.analytics-page
.analytics-operational-head,

html[data-theme="dark"]
.analytics-page
.analytics-operational-card {
    background:
        var(--surface-elevated);
}


html[data-theme="dark"]
.analytics-page
.analytics-operational-mark {
    background:
        rgba(
            22,
            119,
            200,
            0.12
        );
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 920px) {

    .analytics-page
    .analytics-operational-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }
}


@media (max-width: 560px) {

    .analytics-page
    .analytics-operational-grid {
        grid-template-columns:
            1fr;
    }

    .analytics-page
    .analytics-operational-head {
        padding:
            13px
            14px;
    }

    .analytics-page
    .analytics-operational-grid {
        padding:
            11px;
    }

    .analytics-page
    .analytics-operational-footnote {
        margin:
            0
            11px
            11px;
    }
}
</style>

{{-- OPERATIONAL PERFORMANCE PARENT CARD V2 END --}}

{{-- OPERATIONAL PERFORMANCE POLISH V3 START --}}

<style>
/* ==========================================================
   OPERATIONAL PERFORMANCE - FINAL COMPACT POLISH
   ========================================================== */

.analytics-page
.analytics-operational-grid {
    padding:
        11px
        12px
        10px;

    gap: 9px;
}


/*
 * Slightly shorter KPI cards to match Analytics Summary.
 */
.analytics-page
.analytics-operational-card {
    min-height: 94px;

    padding:
        11px
        12px;

    gap: 10px;

    align-items: flex-start;
}


/*
 * Compact icon block.
 */
.analytics-page
.analytics-operational-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    border-radius: 8px;
}

.analytics-page
.analytics-operational-icon svg {
    width: 15px;
    height: 15px;
}


/*
 * KPI title stays on one line when possible.
 */
.analytics-page
.analytics-operational-label {
    font-size: 8.5px;

    line-height: 1.15;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


/*
 * Slightly tighter metric value.
 */
.analytics-page
.analytics-operational-value {
    margin-top: 5px;

    font-size: 20px;

    line-height: 1;
}


/*
 * Keep helper text readable but compact.
 */
.analytics-page
.analytics-operational-note {
    margin-top: 6px;

    font-size: 8.8px;

    line-height: 1.3;
}


/*
 * Compact footer note.
 */
.analytics-page
.analytics-operational-footnote {
    margin:
        0
        12px
        10px;

    padding:
        8px
        10px;

    font-size: 8.8px;

    line-height: 1.35;
}


/*
 * Slightly tighter header.
 */
.analytics-page
.analytics-operational-head {
    padding:
        12px
        15px;
}


.analytics-page
.analytics-operational-title h2 {
    font-size: 13.5px;
}


.analytics-page
.analytics-operational-title p {
    font-size: 9px;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 920px) {

    .analytics-page
    .analytics-operational-label {
        white-space: normal;
    }
}

</style>

{{-- OPERATIONAL PERFORMANCE POLISH V3 END --}}

{{-- SPMU OVERVIEW SMART UI V4 : presentation only; original data/cards/links preserved --}}
<style>
.analytics-page .spmu-smart-accordion > header {display:flex;align-items:center;gap:10px;min-height:57px;padding:11px 15px!important;cursor:pointer;}
.analytics-page .spmu-smart-accordion > header > div {min-width:0;flex:1;}
.analytics-page .spmu-smart-accordion > header h2 {margin:0;}
.analytics-page .spmu-smart-accordion > header p {margin:2px 0 0;}
.analytics-page .spmu-smart-toggle {appearance:none;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;min-width:32px;margin-left:auto;padding:0;border:1px solid var(--border,#d4dfeb);border-radius:9px;background:var(--surface-elevated,#fff);color:#235c9a;cursor:pointer;transition:background .15s,color .15s,border-color .15s;}
.analytics-page .spmu-smart-toggle:hover,.analytics-page .spmu-smart-accordion > header:hover .spmu-smart-toggle {background:#e9f3ff;border-color:#91bcea;color:#115bba;}
.analytics-page .spmu-smart-toggle:focus-visible {outline:2px solid #1769dc;outline-offset:2px;}
.analytics-page .spmu-smart-toggle svg {display:block;width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.analytics-page .spmu-smart-accordion[data-smart-open="false"] > :not(header) {display:none!important;}
.analytics-page .spmu-smart-accordion[data-smart-open="false"] {padding-bottom:0!important;min-height:0!important;}
/* Preserve visual identity; tighten the most space-consuming overview-only rows. */
.analytics-page .overview-leader-grid {gap:10px;padding:11px;}
.analytics-page .overview-leader-card {gap:6px;padding:12px;}
.analytics-page .overview-leader-value {padding-top:3px;}
.analytics-page .overview-leader-scope {padding:0 11px 9px;}
@media (max-width: 900px) {.analytics-page .overview-leader-grid {grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width: 540px) {.analytics-page .overview-leader-grid {grid-template-columns:1fr;}}
</style>
<script>
(function () {
  function setup() {
    const root = document.querySelector('.analytics-page');
    if (!root) return;
    const configs = [
      {selector: 'section.analytics-card', title: 'Priority Insights', open: true},
      {selector: 'section.analytics-summary-shell', title: 'Analytics Summary', open: false},
      {selector: '.analytics-operational-performance', title: 'Operational Performance', open: false}
    ];
    const svgUp = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>';
    const svgDown = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    configs.forEach(function(cfg, i) {
      const element = Array.from(root.querySelectorAll(cfg.selector)).find(function(el) {
        const h = el.querySelector(':scope > header h2');
        return h && h.textContent.trim() === cfg.title;
      });
      if (!element || element.dataset.smartReady) return;
      const header = element.querySelector(':scope > header');
      if (!header || !Array.from(element.children).some(function(c){return c!==header;})) return;
      element.classList.add('spmu-smart-accordion');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'spmu-smart-toggle';
      const id = 'overview-smart-' + i;
      button.setAttribute('aria-controls', id);
      const panelNodes = Array.from(element.children).filter(function(n){return n!==header;});
      if (panelNodes.length === 1 && !panelNodes[0].id) panelNodes[0].id = id;
      else button.removeAttribute('aria-controls');
      function change(open) {
        element.dataset.smartOpen = String(open);
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', (open?'Collapse ':'Expand ') + cfg.title);
        button.title = (open?'Collapse ':'Expand ') + cfg.title;
        button.innerHTML = open ? svgUp : svgDown;
      }
      header.appendChild(button);
      change(cfg.open);
      button.addEventListener('click', function(e) {e.stopPropagation();change(element.dataset.smartOpen!=='true');});
      header.addEventListener('click', function(e) {
        if (e.target.closest('a,button,input,select')) return;
        change(element.dataset.smartOpen!=='true');
      });
      element.dataset.smartReady = '1';
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
  else setup();
})();
</script>
{{-- SPMU OVERVIEW PLAIN CHEVRON COMPACT V5 --}}
<style>
/* Presentation-only: keep original Analytics visuals, links, and accordion JS. */
.analytics-page .spmu-smart-accordion > header {
    min-height: 0 !important;
    padding: 9px 14px !important;
    gap: 8px !important;
}
.analytics-page .spmu-smart-accordion > header p { margin-top: 1px !important; }
/* Chevron-only. The full header remains interactive; no separate button card. */
.analytics-page .spmu-smart-toggle,
.analytics-page .spmu-smart-toggle:hover,
.analytics-page .spmu-smart-accordion > header:hover .spmu-smart-toggle {
    display: inline-grid !important;
    place-items: center !important;
    width: 28px !important;
    height: 28px !important;
    min-width: 28px !important;
    margin-left: auto !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    color: #285d93 !important;
    opacity: 1 !important;
    font-size: 0 !important;
    line-height: 0 !important;
}
.analytics-page .spmu-smart-toggle svg {
    display: block !important;
    width: 19px !important;
    height: 19px !important;
    min-width: 19px !important;
    min-height: 19px !important;
    opacity: 1 !important;
    visibility: visible !important;
    stroke: currentColor !important;
    fill: none !important;
    stroke-width: 2.25 !important;
    pointer-events: none !important;
}
.analytics-page .spmu-smart-toggle:hover { color: #1268cc !important; }
.analytics-page .spmu-smart-toggle:focus-visible {
    outline: 2px solid #1769dc !important;
    outline-offset: 2px !important;
    border-radius: 4px !important;
}
/* Reduce gaps without affecting other Analytics tabs. */
.analytics-page .spmu-smart-accordion[data-smart-open="false"] { min-height: 0 !important; }
.analytics-page .overview-leader-grid { gap: 9px !important; padding: 10px !important; }
.analytics-page .overview-leader-card { padding: 11px !important; gap: 5px !important; }
.analytics-page .overview-leader-scope { padding: 0 10px 8px !important; }
@media (max-width: 640px) {
    .analytics-page .spmu-smart-accordion > header { padding: 10px 12px !important; }
}
</style>
<style>
/* SPMU ANALYTICS OVERVIEW EQUAL SPACING */

/* Consistent vertical spacing for Overview only */
.analytics-page:has(.spmu-smart-accordion) {
    row-gap: 14px !important;
}

/* Remove extra margin causing uneven spacing */
.analytics-page:has(.spmu-smart-accordion)
.analytics-operational-performance {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* Keep accordion headers compact */
.analytics-page .spmu-smart-accordion > header {
    min-height: 0 !important;
    padding: 10px 14px !important;
}

/* Keep collapsed sections at natural height */
.analytics-page
.spmu-smart-accordion[data-smart-open="false"] {
    min-height: 0 !important;
}

/* Preserve equal spacing on smaller screens */
@media (max-width: 768px) {
    .analytics-page:has(.spmu-smart-accordion) {
        row-gap: 12px !important;
    }
}
</style>