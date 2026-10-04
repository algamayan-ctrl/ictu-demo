@php
 /*
 | Borrowing & Return Performance.
 |
 | The tab answers one question: how are returns, overdue follow-up and
 | accountability outcomes performing? Borrowing demand - who borrows most,
 | which units borrow most - is a different question and lives in Demand &
 | Utilization, so it is not repeated here.
 |
 | Two measures are kept strictly apart, because conflating them would
 | misstate both:
 |
 | CURRENTLY OVERDUE - still physically out after the due date. Present
 | tense, not limited by the reporting period.
 | RETURNED LATE - came back, only after the due date. A finished
 | borrowing inside the reporting period.
 |
 | Nothing is calculated in this file. Every figure is computed in
 | AnalyticsService and handed over by AnalyticsController, and a reading
 | that does not exist is stated as absent rather than shown as zero.
 */
 use App\Support\AnalyticsDetailLink;

 $division = $selectedDivision === 'all' ? null : $selectedDivision;
 $unit = $selectedUnit === 'all' ? null : $selectedUnit;

 $completed = $returns['completed'];

 /*
 * The donut describes completed returns only. A borrowing that is still
 * out has no return outcome yet, so currently-overdue cases are not a
 * slice of it.
 */
 $onTimePct = $completed > 0 ? round($returns['on_time'] / $completed * 100) : null;
 $latePct = $completed > 0 ? 100 - $onTimePct : null;

 $trendPlotted = $returnTrend['plotted'];

 $returnTrendMode = match (true) {
 $trendPlotted === 0 => 'empty',
 count($returnTrend['points']) === 1 => 'single',
 default => 'line',
 };

 $singleReturnPoint = $returnTrend['points'][0] ?? null;

 /* Only conditions someone actually recorded are charted. */
 $conditionRows = $returnConditions['rows'];
@endphp

{{-- Headline figures ------------------------------------------------- --}}
<div class="analytics-kpis analytics-kpis-returns">
 <a
 class="analytics-kpi-card tone-accountability"
 href="{{ AnalyticsDetailLink::to('returns', 'returns', $periodSelection, $division, $unit, ['state' => 'on-time']) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="check-circle" size="19" /></span>
 <span class="analytics-kpi-card-label">Returned On Time</span>
 <strong class="analytics-kpi-card-value">{{ $returns['on_time'] }}</strong>
 <span class="analytics-kpi-card-note">Returned on or before the due date</span>
 {{--
 Completed returns are period outcomes, so they carry a previous
 period. Currently Overdue and Open Accountability below do not:
 one is today's backlog, the other a hybrid of opening date and
 present status, and neither has an equivalent past reading.
 --}}
 @include('analytics.partials.period-delta', ['comparison' => $returnComparison['on_time'], 'deltaClass' => 'analytics-kpi-card-meta'])
 </a>

 <a
 class="analytics-kpi-card tone-overdue"
 href="{{ AnalyticsDetailLink::to('returns', 'returns', $periodSelection, $division, $unit, ['state' => 'late']) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="clock" size="19" /></span>
 <span class="analytics-kpi-card-label">Returned Late</span>
 <strong class="analytics-kpi-card-value">{{ $returns['late'] }}</strong>
 <span class="analytics-kpi-card-note">Returned after the due date</span>
 @include('analytics.partials.period-delta', ['comparison' => $returnComparison['late'], 'deltaClass' => 'analytics-kpi-card-meta'])
 </a>

 <a
 class="analytics-kpi-card tone-overdue"
 href="{{ AnalyticsDetailLink::to('follow-up', 'returns', $periodSelection, $division, $unit) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="warning" size="19" /></span>
 <span class="analytics-kpi-card-label">Currently Overdue</span>
 <strong class="analytics-kpi-card-value">{{ $returns['overdue'] }}</strong>
 <span class="analytics-kpi-card-note">Still out past the return date</span>
 </a>

 <a
 class="analytics-kpi-card tone-attention"
 href="{{ AnalyticsDetailLink::to('card', 'returns', $periodSelection, $division, $unit, ['for' => 'returns.issues']) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="accountability" size="19" /></span>
 <span class="analytics-kpi-card-label">Open Accountability</span>
 <strong class="analytics-kpi-card-value">{{ $returns['open_cases'] }}</strong>
 <span class="analytics-kpi-card-note">Cases opened in the selected period that remain unresolved.</span>
 </a>
</div>

{{-- Return trend and outcome ------------------------------------------ --}}
@if($detail)
 @include('analytics.partials.detail-panel')
@endif

<div class="analytics-overview-main">
 <section
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.trend']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="analytics" size="15" /></span>
 <div>
 <h2>Return Performance Trend</h2>
 <p>Completed returns per {{ $returnTrend['granularity'] }}, split into on time and late.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.trend']) }}" aria-label="View Return Performance Trend details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 @if($returnTrendMode === 'empty')
 <div class="analytics-card-body">
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="custody" size="19" /></span>
 No completed returns during this period.
 </p>
 </div>
 @elseif($returnTrendMode === 'single')
 <div class="analytics-card-body analytics-trend-single">
 <div class="analytics-trend-single-figure">
 <span class="analytics-trend-single-value">
 <strong>{{ $singleReturnPoint['on_time'] + $singleReturnPoint['late'] }}</strong>
 completed
 </span>
 <span class="analytics-return-split">
 <span class="is-ontime">{{ $singleReturnPoint['on_time'] }} on time</span>
 <span class="is-late">{{ $singleReturnPoint['late'] }} late</span>
 </span>
 <span class="analytics-trend-single-label">{{ $singleReturnPoint['label'] }}</span>
 </div>
 </div>
 @else
 <div class="analytics-card-body is-plot">
 @include('analytics.partials.return-trend-line', ['returnTrend' => $returnTrend])
 </div>
 @endif

 @if($returnTrendMode !== 'empty' && $trendPlotted < $returnTrend['total'])
 <footer class="analytics-card-foot">
 {{ $returnTrend['total'] - $trendPlotted }} completed
 {{ $returnTrend['total'] - $trendPlotted === 1 ? 'return' : 'returns' }}
 closed outside this period, not plotted.
 </footer>
 @endif
 </section>

 <style>
/* SPMU RETURN OUTCOME DONUT V2 */

.return-outcome-v2 .analytics-card-body {
 padding: 18px;
}

.return-outcome-v2-layout {
 display: grid;
 grid-template-columns: 142px minmax(0, 1fr);
 align-items: center;
 gap: 20px;
}

/* DONUT */
.return-outcome-v2-donut {
 position: relative;
 display: grid;
 place-items: center;
 width: 132px;
 height: 132px;
 margin: 0 auto;
 border-radius: 50%;
 background:
 conic-gradient(
 #07845f 0deg var(--outcome-on-time-angle),
 #d34a16 var(--outcome-on-time-angle) 360deg
 );
}

.return-outcome-v2-donut.is-empty {
 background: var(--surface-muted);
}

.return-outcome-v2-donut::after {
 content: '';
 position: absolute;
 inset: 10px;
 border-radius: 50%;
 background: var(--surface-elevated);
}

.return-outcome-v2-center {
 position: relative;
 z-index: 1;
 display: grid;
 justify-items: center;
 gap: 2px;
 text-align: center;
}

.return-outcome-v2-center strong {
 color: var(--heading);
 font-size: 28px;
 font-weight: 800;
 line-height: 1;
 font-variant-numeric: tabular-nums;
}

.return-outcome-v2-center span {
 max-width: 76px;
 color: var(--text-muted);
 font-size: 9.5px;
 font-weight: 650;
 line-height: 1.25;
}

/* OUTCOME ROWS */
.return-outcome-v2-results {
 display: grid;
 gap: 10px;
 min-width: 0;
}

.return-outcome-v2-row {
 display: grid;
 grid-template-columns: minmax(0, 1fr) auto auto;
 align-items: center;
 gap: 12px;
 min-height: 58px;
 padding: 10px 12px;
 border: 1px solid var(--border);
 border-radius: 12px;
}

.return-outcome-v2-row.is-ontime {
 background: color-mix(
 in srgb,
 #07845f 6%,
 var(--surface-elevated)
 );
}

.return-outcome-v2-row.is-late {
 background: color-mix(
 in srgb,
 #d34a16 5%,
 var(--surface-elevated)
 );
}

.return-outcome-v2-name {
 display: flex;
 align-items: center;
 gap: 8px;
 min-width: 0;
 color: var(--text-secondary);
 font-size: 11px;
 font-weight: 700;
}

.return-outcome-v2-dot {
 width: 9px;
 height: 9px;
 flex: 0 0 9px;
 border-radius: 50%;
}

.return-outcome-v2-row.is-ontime .return-outcome-v2-dot {
 background: #07845f;
}

.return-outcome-v2-row.is-late .return-outcome-v2-dot {
 background: #d34a16;
}

.return-outcome-v2-count {
 min-width: 20px;
 color: var(--heading);
 font-size: 17px;
 font-weight: 800;
 text-align: right;
 font-variant-numeric: tabular-nums;
}

.return-outcome-v2-percent {
 min-width: 44px;
 font-size: 12px;
 font-weight: 800;
 text-align: right;
 font-variant-numeric: tabular-nums;
}

.return-outcome-v2-row.is-ontime .return-outcome-v2-percent {
 color: #07845f;
}

.return-outcome-v2-row.is-late .return-outcome-v2-percent {
 color: #c2410c;
}

/* INSIGHT */
.return-outcome-v2-insight {
 display: flex;
 align-items: center;
 gap: 8px;
 grid-column: 1 / -1;
 min-height: 38px;
 margin-top: 2px;
 padding: 9px 11px;
 border-radius: 10px;
 background: var(--surface-subtle);
 color: var(--text-secondary);
 font-size: 10.5px;
 font-weight: 600;
 line-height: 1.35;
}

.return-outcome-v2-insight-icon {
 display: grid;
 place-items: center;
 width: 22px;
 height: 22px;
 flex: 0 0 22px;
 border-radius: 50%;
 color: #07845f;
 background: color-mix(in srgb, #07845f 10%, transparent);
}

/* RESPONSIVE */
@media (max-width: 760px) {
 .return-outcome-v2-layout {
 grid-template-columns: 1fr;
 }

 .return-outcome-v2-donut {
 width: 122px;
 height: 122px;
 }
}

html[data-theme="dark"] .return-outcome-v2-donut::after {
 background: var(--surface-elevated);
}
</style>

@php
 $outcomeOnTime = (int) ($returns['on_time'] ?? 0);
 $outcomeLate = (int) ($returns['late'] ?? 0);

 $outcomeTotal = $outcomeOnTime + $outcomeLate;

 $outcomeOnTimePercent = $outcomeTotal > 0
 ? (int) round(($outcomeOnTime / $outcomeTotal) * 100)
 : 0;

 $outcomeLatePercent = $outcomeTotal > 0
 ? 100 - $outcomeOnTimePercent
 : 0;

 $outcomeOnTimeAngle = $outcomeTotal > 0
 ? round(($outcomeOnTime / $outcomeTotal) * 360, 2)
 : 0;

 $outcomeInsight = match (true) {
 $outcomeTotal === 0 =>
 'No completed returns were recorded during this period.',

 $outcomeLate === 0 =>
 'All completed returns were returned on time.',

 $outcomeOnTime === 0 =>
 'All completed returns were returned late.',

 default =>
 $outcomeOnTimePercent.'% of completed returns were returned on time.',
 };
@endphp

<section
 class="analytics-card return-outcome-v2"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to(
 'card',
 'returns',
 $periodSelection,
 $selectedDivision === 'all' ? null : $selectedDivision,
 $selectedUnit === 'all' ? null : $selectedUnit,
 ['for' => 'returns.outcome']
 ) }}"
>
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true">
 <x-icon name="check-circle" size="15" />
 </span>

 <div>
 <h2>Return Outcome</h2>
 <p>Completed returns only.</p>
 </div>

 <a
 class="analytics-card-open"
 href="{{ App\Support\AnalyticsDetailLink::to(
 'card',
 'returns',
 $periodSelection,
 $selectedDivision === 'all' ? null : $selectedDivision,
 $selectedUnit === 'all' ? null : $selectedUnit,
 ['for' => 'returns.outcome']
 ) }}"
 aria-label="View Return Outcome details"
 >
 <x-icon name="chevron-right" size="15" />
 </a>
 </header>

 <div class="analytics-card-body">
 <div class="return-outcome-v2-layout">

 <div
 class="return-outcome-v2-donut{{ $outcomeTotal === 0 ? ' is-empty' : '' }}"
 style="--outcome-on-time-angle: {{ $outcomeOnTimeAngle }}deg"
 role="img"
 aria-label="{{ $outcomeTotal }} completed returns. {{ $outcomeOnTime }} on time and {{ $outcomeLate }} late."
 >
 <div class="return-outcome-v2-center">
 <strong>{{ $outcomeTotal }}</strong>
 <span>
 Completed
 {{ $outcomeTotal === 1 ? 'return' : 'returns' }}
 </span>
 </div>
 </div>

 <div class="return-outcome-v2-results">

 <div class="return-outcome-v2-row is-ontime">
 <span class="return-outcome-v2-name">
 <span class="return-outcome-v2-dot"></span>
 On time
 </span>

 <strong class="return-outcome-v2-count">
 {{ $outcomeOnTime }}
 </strong>

 <span class="return-outcome-v2-percent">
 {{ $outcomeOnTimePercent }}%
 </span>
 </div>

 <div class="return-outcome-v2-row is-late">
 <span class="return-outcome-v2-name">
 <span class="return-outcome-v2-dot"></span>
 Late
 </span>

 <strong class="return-outcome-v2-count">
 {{ $outcomeLate }}
 </strong>

 <span class="return-outcome-v2-percent">
 {{ $outcomeLatePercent }}%
 </span>
 </div>

 </div>

 <div class="return-outcome-v2-insight">
 <span class="return-outcome-v2-insight-icon" aria-hidden="true">
 <x-icon name="check-circle" size="13" />
 </span>

 <span>{{ $outcomeInsight }}</span>
 </div>

 </div>
 </div>
</section>
</div>

{{-- Where late returns concentrate ----------------------------------------- --}}
@php
 /*
 | Late Return Patterns: the completed returns already counted above
 | (Returned On Time + Returned Late, by physical completion date),
 | split by the organisation recorded on the request at borrowing time.
 | Each bar is a rate of that segment's own completed returns, so the
 | track is 0-100 and a 20% rate fills a fifth of it; the counts are
 | always printed beside it so the sample is never hidden. Every figure
 | comes from AnalyticsService::lateReturnRates(); nothing is computed
 | here, and no comparison with a previous period is drawn.
 */
 $lateRateLink = static fn (string $level, ?array $row = null): string => AnalyticsDetailLink::to(
 'late-rate', 'returns', $periodSelection, $division, $unit,
 ['level' => $level]
 + ($row ? ['for' => $row['code'] ?? 'unspecified'] : [])
 + ($row && $level === 'unit' ? ['segment' => $row['unit']] : [])
 );

 $rateText = static fn (?float $rate): string => $rate === null ? '—' : $rate.'%';

 /* The card shows the leading rows; the detail lists every segment. */
 $lateRatePanels = [
 ['level' => 'division', 'title' => 'Late Return Rate by Division', 'data' => $lateRatesByDivision, 'shown' => 4],
 ['level' => 'unit', 'title' => 'Late Return Rate by Unit', 'data' => $lateRatesByUnit, 'shown' => 6],
 ];
@endphp
<section
 data-card-detail="{{ $lateRateLink('division') }}"
 class="analytics-card analytics-laterate">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="users" size="15" /></span>
 <div>
 <h2>Late Return Patterns</h2>
 <p>Late-return share among completed returns in the selected period.</p>
 </div>
 <a class="analytics-card-open" href="{{ $lateRateLink('division') }}" aria-label="View Late Return Patterns details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 @if(! $lateRatesByDivision['available'])
 <div class="analytics-card-body">
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="users" size="19" /></span>
 No completed returns are available for late-return rate analysis in this period.
 </p>
 </div>
 @else
 <div class="analytics-card-body analytics-laterate-body">
 @foreach($lateRatePanels as $panel)
 @php
 $rows = array_slice($panel['data']['groups'], 0, $panel['shown']);
 $hidden = count($panel['data']['groups']) - count($rows);
 @endphp
 <div class="analytics-laterate-panel">
 <h3 class="analytics-laterate-title">
 <a href="{{ $lateRateLink($panel['level']) }}">{{ $panel['title'] }}</a>
 </h3>

 @if($rows === [])
 <p class="analytics-laterate-none">
 {{ $panel['level'] === 'unit'
 ? 'No completed return in this period carries a recorded unit.'
 : 'No completed returns in this period.' }}
 </p>
 @else
 <ul class="analytics-laterate-rows" aria-label="{{ $panel['title'] }}">
 @foreach($rows as $row)
 <li>
 <a
 href="{{ $lateRateLink($panel['level'], $row) }}"
 data-chart-tip
 data-tip-title="{{ $row['label'] }}{{ $panel['level'] === 'unit' ? ' - '.$row['division_label'] : '' }}"
 data-tip-rows="{{ json_encode([
 ['Late return rate', $rateText($row['late_rate'])],
 ['Returned late', (string) $row['late']],
 ['Completed returns', (string) $row['completed']],
 ]) }}"
 aria-label="View details for {{ $row['label'] }}: late return rate {{ $rateText($row['late_rate']) }}, {{ $row['late'] }} late of {{ $row['completed'] }} completed returns"
 >
 <span class="analytics-laterate-head">
 <span class="analytics-laterate-name">
 {{ $row['label'] }}
 @if($panel['level'] === 'unit')
 <small class="analytics-rank-tag is-{{ strtolower(explode('_', (string) ($row['code'] ?? 'unspecified'))[0]) }}">{{ $row['division_label'] }}</small>
 @endif
 </span>
 <strong class="analytics-laterate-rate">{{ $rateText($row['late_rate']) }}</strong>
 </span>
 {{-- The track is 0-100: the fill is the rate itself, not a share of the largest row. --}}
 <span class="analytics-laterate-track" aria-hidden="true">
 <span class="analytics-laterate-fill" style="width: {{ $row['late_rate'] ?? 0 }}%"></span>
 </span>
 <span class="analytics-laterate-counts">{{ $row['late'] }} late - {{ $row['completed'] }} completed</span>
 </a>
 </li>
 @endforeach
 </ul>

 @if($hidden > 0)
 <a class="analytics-laterate-more" href="{{ $lateRateLink($panel['level']) }}">
 View all {{ count($panel['data']['groups']) }} {{ $panel['level'] === 'unit' ? 'units' : 'classifications' }}
 </a>
 @endif
 @endif
 </div>
 @endforeach
 </div>

 <footer class="analytics-card-foot">
 Rates are shown with their counts; a small count is a small sample.
 @if($selectedBorrower)
 Scoped to the selected borrower's completed returns.
 @endif
 </footer>

 @if($lateRatesByDivision['summary'])
 <p class="analytics-insight-strip">
 <x-icon name="information" size="14" aria-hidden="true" />
 <span>{{ $lateRatesByDivision['summary'] }}</span>
 </p>
 @endif
 @endif
</section>

{{-- Overdue follow-up and return condition ----------------------------- --}}
@php
 $agingLink = static fn (?string $bucket = null): string => AnalyticsDetailLink::to(
 'overdue-aging', 'returns', $periodSelection, $division, $unit, $bucket ? ['bucket' => $bucket] : []
 );
@endphp
<div class="analytics-overview-rankings">
 <section
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.followup']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="warning" size="15" /></span>
 <div>
 <h2>Current Overdue Exposure</h2>
 <p>Current overdue cases with aging context.</p>
 </div>
 @if($currentOverdue['total'] > $currentOverdue['shown'])
 <a class="analytics-card-action" href="{{ AnalyticsDetailLink::to('follow-up', 'returns', $periodSelection, $division, $unit) }}">
 View all {{ $currentOverdue['total'] }}
 </a>
 @endif
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.followup']) }}" aria-label="View Current Overdue Exposure details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if($currentOverdue['cases'] === [])
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark is-good" aria-hidden="true"><x-icon name="check-circle" size="19" /></span>
 No borrowings are currently overdue.
 </p>
 @else
 <ul class="analytics-followup">
 @foreach($currentOverdue['cases'] as $case)
 <li>
 <a
 class="analytics-followup-row"
 href="{{ AnalyticsDetailLink::to('follow-up', 'returns', $periodSelection, $division, $unit) }}"
 >
 <span class="analytics-followup-main">
 <span class="analytics-followup-name">{{ $case['borrower'] }}</span>
 <span class="analytics-followup-ref">
 {{ $case['custody_no'] }}
 @if($case['request_no']) &middot; {{ $case['request_no'] }} @endif
 </span>
 </span>
 <span class="analytics-followup-due">
 <span>Due {{ optional($case['due_at'])->format('d M Y') ?: '—' }}</span>
 <strong>
 {{ $case['days_overdue'] === null
 ? 'Overdue'
 : $case['days_overdue'].' '.($case['days_overdue'] === 1 ? 'day' : 'days').' overdue' }}
 </strong>
 </span>
 </a>
 </li>
 @endforeach
 </ul>
 @endif
 </div>

 @if($overdueAging['available'])
 <div class="analytics-card-body">
 <ul class="analytics-aging-bands" aria-label="Currently overdue borrowings by days past due">
 @foreach($overdueAging['groups'] as $row)
 <li class="{{ $row['count'] === 0 ? 'is-none' : '' }}">
 <a
 href="{{ $agingLink($row['key']) }}"
 data-chart-tip
 data-tip-title="{{ $row['label'] }} past due"
 data-tip-rows="{{ json_encode([
 [$row['count'] === 1 ? 'Borrowing' : 'Borrowings', (string) $row['count']],
 ['Share of currently overdue', $row['share'].'%'],
 ]) }}"
 aria-label="View details for {{ $row['label'] }} past due"
 >
 <span class="analytics-aging-label">{{ $row['label'] }}</span>
 <span class="analytics-aging-track" aria-hidden="true">
 <span class="analytics-aging-fill" style="width: {{ $row['width'] }}%"></span>
 </span>
 <strong>{{ $row['count'] }}</strong>
 <small>{{ $row['share'] }}%</small>
 </a>
 </li>
 @endforeach
 </ul>
 </div>
 @endif
 </section>

 <section
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.condition']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="box" size="15" /></span>
 <div>
 <h2>Return Condition</h2>
 <p>Recorded at return inspection, by quantity received.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'returns', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'returns.condition']) }}" aria-label="View Return Condition details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if($conditionRows === [])
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="box" size="19" /></span>
 No return inspections were recorded during this period.
 </p>
 @else
 @php
 $conditionTotal = max(1, (float) $returnConditions['total']);
 @endphp
 <div class="analytics-condition-composition" role="img" aria-label="Return condition composition">
 <div class="analytics-condition-bar">
 @foreach($conditionRows as $row)
 @php $pct = round(((float) $row['quantity'] / $conditionTotal) * 100, 1); @endphp
 <span
 class="analytics-condition-segment {{ $row['is_fine'] ? 'is-good' : 'is-issue' }}"
 style="width: {{ $pct }}%"
 tabindex="0"
 role="img"
 data-chart-tip
 data-tip-title="{{ $row['label'] }}"
 data-tip-rows="{{ json_encode([
 ['Quantity received', (string) $row['quantity']],
 ['Share of returned quantity', $pct.'%'],
 ]) }}"
 aria-label="{{ $row['label'] }}: {{ $row['quantity'] }} received, {{ $pct }} percent"
 ></span>
 @endforeach
 </div>

 <ul class="analytics-condition-legend">
 @foreach($conditionRows as $row)
 @php $pct = round(((float) $row['quantity'] / $conditionTotal) * 100, 1); @endphp
 <li>
 <span class="analytics-condition-key {{ $row['is_fine'] ? 'is-good' : 'is-issue' }}" aria-hidden="true"></span>
 <span>{{ $row['label'] }}</span>
 <strong>{{ $row['quantity'] }}</strong>
 <small>{{ $pct }}%</small>
 </li>
 @endforeach
 </ul>
 </div>
 @endif
 </div>
 </section>
</div>

{{-- Overdue Aging is consolidated into Current Overdue Exposure above. --}}
