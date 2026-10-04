@php
 /*
 | Forecast & Planning.
 |
 | FOUR THINGS ARE KEPT APART, AND NEVER MERGED
 | --------------------------------------------
 | 1. Observed history - what was actually filed in completed periods.
 | 2. Scheduled demand - requests ALREADY FILED for the period the
 | forecast covers. These are records, not a
 | projection. They are never called a forecast.
 | 3. Forecasted demand - the weighted moving average in ForecastService.
 | A statistical estimate, and labelled as one.
 | 4. Forecast readiness - whether enough completed history exists for (3)
 | to be produced at all.
 |
 | The method is a weighted moving average over three completed periods. It
 | is arithmetic, not a trained model, and nothing here describes it as one.
 |
 | Layout only: every figure comes from ForecastService or AnalyticsService
 | through AnalyticsController. Where a reading cannot be produced the page
 | says so rather than printing a zero that would read as a real count.
 */
 use App\Support\AnalyticsDetailLink;

 $division = $selectedDivision === 'all' ? null : $selectedDivision;
 $unit = $selectedUnit === 'all' ? null : $selectedUnit;

 $hasDemand = $demand['available'] ?? false;
 $hasDivision = $divisionForecast['available'] ?? false;
 $hasUnit = $unitForecast['available'] ?? false;
 $hasEquipment = ($equipmentForecast['available'] ?? false) && ($equipmentForecast['items'] ?? []) !== [];
 $hasBusy = $busyPeriod['available'] ?? false;

 $readiness = $demand['readiness'] ?? [];
 $ready = ($readiness['periods_met'] ?? false) && ($readiness['observations_met'] ?? false);

 $scheduledTotal = (int) ($scheduled['requests'] ?? 0);
    $windowLabel =
        $forecastFrom->format('d M')
        .' - '.
        $forecastTo->format('d M Y');

 $num = static fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');

 /*
 | The outlook series.
 |
 | Observed = the three completed history periods plus the selected one.
 | The forecast point is appended only when the service produced one; there
 | is deliberately no placeholder point when it did not, because a drawn
 | point is indistinguishable from a real projection.
 */
 $observed = [];

 foreach ($demand['history'] ?? [] as $row) {
 $observed[] = [
 'label' => trim(\Illuminate\Support\Str::of($row['label'])->after('–')->before(' 20')),
 'full' => $row['label'],
 'count' => (int) $row['count'],
 ];
 }

 $observed[] = [
 'label' => 'This period',
 'full' => 'The selected reporting period',
 'count' => (int) ($demand['current'] ?? 0),
 ];

 $forecastPoint = $hasDemand
 ? ['label' => 'Next period', 'full' => $windowLabel, 'count' => (int) $demand['forecast']]
 : null;

 /* Geometry over the whole series so the two segments share one scale. */
 $series = $observed;

 if ($forecastPoint) {
 $series[] = $forecastPoint;
 }

 $seriesMax = max(1, (int) max(array_column($series, 'count')));
 $seriesCount = count($series);
 $topPad = 8.0;
 $baseline = 92.0;
 $span = $baseline - $topPad;

 $coords = [];

 foreach ($series as $index => $point) {
 $coords[] = $point + [
 'x' => $seriesCount > 1 ? round($index / ($seriesCount - 1) * 100, 3) : 50.0,
 'y' => round($baseline - ($point['count'] / $seriesMax * $span), 3),
 'forecast' => $forecastPoint !== null && $index === $seriesCount - 1,
 ];
 }

 $observedCoords = $forecastPoint ? array_slice($coords, 0, -1) : $coords;

 $solidPath = 'M'.implode(' L', array_map(
 static fn (array $c): string => $c['x'].','.$c['y'],
 $observedCoords
 ));

 /* The dashed leg joins the last observed point to the projected one. */
 $dashedPath = null;

 if ($forecastPoint) {
 $last = $observedCoords[count($observedCoords) - 1];
 $next = $coords[$seriesCount - 1];
 $dashedPath = 'M'.$last['x'].','.$last['y'].' L'.$next['x'].','.$next['y'];
 }

 /*
 | Division and unit demand fall back to what is actually scheduled when
 | the statistical forecast is unavailable. The card title changes with it,
 | so a scheduled count is never presented under a forecast heading.
 */
 $divisionRows = $hasDivision
 ? collect($divisionForecast['groups'])
 ->map(fn (array $row): array => [
 'code' => $row['code'],
 'label' => $row['short_label'],
 'value' => (int) $row['forecast'],
 ])
 ->sortByDesc('value')->values()
 : collect($scheduled['divisions']['groups'] ?? [])
 ->map(fn (array $row): array => [
 'code' => $row['code'],
 'label' => $row['label'],
 'value' => (int) $row['count'],
 ])
 ->sortByDesc('value')->values();

 $divisionMax = (int) ($divisionRows->max('value') ?: 0);

 $unitRows = $hasUnit
 ? collect($unitForecast['units'])
 ->map(fn (array $row): array => ['unit' => $row['unit'], 'value' => (int) $row['forecast']])
 ->take(5)->values()
 : collect($scheduled['units']['columns'] ?? [])
 ->flatMap(fn (array $column): array => collect($column['units'])
 ->map(fn (array $row): array => ['unit' => $row['name'], 'value' => (int) $row['count']])
 ->all())
 ->sortByDesc('value')->take(5)->values();

 $unitMax = (int) ($unitRows->max('value') ?: 0);

 /* Shortfalls first: a covered item is not what a planner is looking for. */
 $equipmentRows = $hasEquipment
 ? collect($equipmentForecast['items'])
 ->sortBy(fn (array $row): int => $row['status'] === 'Sufficient' ? 1 : 0)
 ->take(6)->values()
 : collect();

 $busyPeak = $hasBusy ? (int) max(array_column($busyPeriod['buckets'], 'expected')) : 0;
 $busyPeaks = $busyPeak > 0 ? collect($busyPeriod['buckets'])->where('expected', $busyPeak) : collect();
 $busySummary = $busyPeaks->count() > 1
 ? $busyPeaks->pluck('label')->implode(', ').' share the projected peak of '.$busyPeak.' '.($busyPeak === 1 ? 'request' : 'requests').' each.'
 : ($busyPeriod['summary'] ?? null);

 /*
 | Planning notes.
 |
 | Each note restates a figure already on this page, in the order a planner
 | would act on it: coverage risk, then known commitments, then where the
 | demand sits, then what is still missing. Nothing is recommended and
 | nothing is inferred; a note with no figure behind it is simply absent.
 */
 $notes = [];

 if ($hasEquipment && ($equipmentForecast['at_risk_count'] ?? 0) > 0) {
 $notes[] = ['tone' => 'urgent', 'icon' => 'warning', 'text' => $equipmentForecast['summary']];
 } elseif ($hasEquipment) {
 $notes[] = ['tone' => 'steady', 'icon' => 'check-circle', 'text' => $equipmentForecast['summary']];
 }

 if ($scheduledTotal > 0) {
 $notes[] = [
 'tone' => 'info',
 'icon' => 'calendar',
 'text' => $scheduledTotal.' '.($scheduledTotal === 1 ? 'request is' : 'requests are')
 .' already scheduled for '.$windowLabel.'. These are filed records, not a projection.',
 ];
 }

 if ($divisionRows->isNotEmpty() && $divisionMax > 0) {
 $leader = $divisionRows->first();
 $notes[] = [
 'tone' => 'info',
 'icon' => 'users',
 'text' => $hasDivision
 ? $leader['label'].' is projected to remain the largest borrowing division.'
 : $leader['label'].' accounts for the largest known scheduled demand.',
 ];
 }

 if ($hasDemand && ! $hasBusy) {
 $notes[] = ['tone' => 'muted', 'icon' => 'information', 'text' => 'A busy-period pattern is not yet available for this period.'];
 }

     /* PLANNING_NOTES_DATA_V4_START */

    /*
     * Scheduled demand is a valid planning signal even when
     * the value is zero.
     *
     * A zero means no request has been filed for the next
     * forecast period yet. It is not missing data.
     *
     * Do not add a duplicate when an existing Planning Note
     * already represents scheduled demand.
     */
    $hasScheduledPlanningNote =
        collect($notes)->contains(
            static function (array $note): bool {

                $text =
                    strtolower(
                        trim(
                            (string) (
                                $note['text']
                                ?? ''
                            )
                        )
                    );

                return
                    str_contains(
                        $text,
                        'scheduled demand'
                    )
                    ||
                    str_contains(
                        $text,
                        'already recorded'
                    )
                    ||
                    str_contains(
                        $text,
                        'already filed'
                    )
                    ||
                    str_contains(
                        $text,
                        'currently recorded for the next forecast period'
                    );
            }
        );


    if (! $hasScheduledPlanningNote) {

        $scheduledText =
            $scheduledTotal > 0
                ? (
                    $scheduledTotal
                    .' '
                    .(
                        $scheduledTotal === 1
                            ? 'request is'
                            : 'requests are'
                    )
                    .' already recorded for the next forecast period.'
                )
                : 'No requests are currently recorded for the next forecast period.';


        /*
         * Insert immediately after Capacity Coverage/Risk.
         * This keeps the planning order:
         *
         * 1. Capacity
         * 2. Scheduled demand
         * 3. Demand concentration
         * 4. Readiness/data gap, when applicable
         */
        array_splice(
            $notes,
            min(
                1,
                count($notes)
            ),
            0,
            [[
                'tone' =>
                    'neutral',

                'icon' =>
                    'calendar',

                'text' =>
                    $scheduledText,
            ]]
        );
    }

    /* PLANNING_NOTES_DATA_V4_END */
$notes = array_slice($notes, 0, 4);
@endphp

{{-- Headline state ------------------------------------------------------- --}}
@php
 /*
 * FORECAST_PLANNING_PEAK_KPI
 *
 * Reuse the exact same busy-period calculation already used by
 * Expected Busy Period below. The service payload stores the weekly
 * forecast in busyPeriod.buckets and each bucket uses "expected".
 */
 $forecastPeakValue = $busyPeak;

 $forecastPeakRows = $busyPeak > 0
 ? collect($busyPeriod['buckets'] ?? [])
 ->where('expected', $busyPeak)
 ->values()
 : collect();

 $forecastPeakLabel = match (true) {
 $forecastPeakRows->count() === 1 =>
 (string) ($forecastPeakRows->first()['label'] ?? 'Peak period'),

 $forecastPeakRows->count() > 1 =>
 'Multiple weeks',

 default =>
 'Not measurable',
 };
@endphp
<div class="analytics-kpis analytics-kpis-forecast">
 <a
 class="analytics-kpi-card tone-forecast"
 href="#expected-busy-period"
>
 <span class="analytics-kpi-card-icon" aria-hidden="true">
 <x-icon name="calendar" size="18" />
 </span>

 <span class="analytics-kpi-card-content">
 <span class="analytics-kpi-card-label">
 Peak Period
 </span>

 <strong class="analytics-kpi-card-value">
 {{ $forecastPeakValue > 0 ? $forecastPeakLabel : 'Not measurable' }}
 </strong>

 <span class="analytics-kpi-card-note">
 @if($forecastPeakValue > 0)
 {{ $forecastPeakValue }}
 projected
 {{ $forecastPeakValue === 1 ? 'request' : 'requests' }}
 @else
 No projected peak yet
 @endif
 </span>
 </span>
</a>

 {{--
 Already-filed records for the forecast window. A real zero here means
 nothing has been filed for that period yet, which is a true count and
 not an unavailable reading, so it is printed as a number.
 --}}
 <a
 class="analytics-kpi-card tone-requests"
 href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.scheduled']) }}"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="calendar" size="19" /></span>
 <span class="analytics-kpi-card-label">Scheduled Demand</span>
 <strong class="analytics-kpi-card-value">{{ $scheduledTotal }}</strong>
 <span class="analytics-kpi-card-note">Requests already recorded for the next period</span>
 </a>

 <a
 class="analytics-kpi-card {{ $hasDemand ? 'tone-requests' : 'tone-stock' }}"
 href="{{ AnalyticsDetailLink::to('forecast', 'predictive', $periodSelection, $division, $unit, ['metric' => 'demand']) }}"
 aria-label="View how the demand forecast was produced"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="analytics" size="19" /></span>
 <span class="analytics-kpi-card-label">Forecasted Demand</span>
 @if($hasDemand)
 <strong class="analytics-kpi-card-value">{{ $demand['forecast'] }}</strong>
 <span class="analytics-kpi-card-note">Projected requests for the next period</span>
 @else
 <strong class="analytics-kpi-card-value is-text">Not available</strong>
 <span class="analytics-kpi-card-note">See the readiness checklist below</span>
 @endif
 </a>

 <a
 class="analytics-kpi-card {{ ! $hasEquipment ? 'tone-stock' : ((($equipmentForecast['at_risk_count'] ?? 0) > 0) ? 'tone-attention' : 'tone-available') }}"
 href="{{ AnalyticsDetailLink::to('forecast', 'predictive', $periodSelection, $division, $unit, ['metric' => 'equipment']) }}"
 aria-label="View equipment coverage against expected demand"
 >
 <span class="analytics-kpi-card-icon" aria-hidden="true"><x-icon name="box" size="19" /></span>
 <span class="analytics-kpi-card-label">Equipment Coverage Risk</span>
 @if($hasEquipment)
 <strong class="analytics-kpi-card-value">{{ $equipmentForecast['at_risk_count'] }}</strong>
 <span class="analytics-kpi-card-note">
 {{ ($equipmentForecast['at_risk_count'] ?? 0) === 0
 ? 'Expected availability covers every item'
 : ((($equipmentForecast['at_risk_count'] ?? 0) === 1 ? 'Item' : 'Items').' may not cover expected demand') }}
 </span>
 @else
 {{-- Nothing was measured, which is not the same as nothing at risk. --}}
 <strong class="analytics-kpi-card-value is-text">Not measurable</strong>
 <span class="analytics-kpi-card-note">Insufficient item-level history</span>
 @endif
 </a>
</div>

{{-- Outlook and readiness ------------------------------------------------ --}}
@if($detail)
 @include('analytics.partials.detail-panel')
@endif

<div class="analytics-forecast-main">
 <section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.outlook']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="analytics" size="15" /></span>
 <div>
 <h2>Borrowing Demand Outlook</h2>
 <p>Requests filed per period, with the projection shown separately.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.outlook']) }}" aria-label="View Borrowing Demand Outlook details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 {{-- Only states that actually appear in the plot are listed. --}}
 <ul class="analytics-outlook-legend">
 <li><span class="analytics-outlook-key is-observed" aria-hidden="true"></span>Observed</li>
 @if($forecastPoint)
 <li><span class="analytics-outlook-key is-forecast" aria-hidden="true"></span>Forecast</li>
 @endif
 </ul>

 <div class="analytics-outlook" role="img" aria-label="Borrowing requests filed per period{{ $forecastPoint ? ', with a projection for the next period' : '' }}">
 <div class="analytics-outlook-plot">
 <span class="analytics-line-grid" aria-hidden="true"></span>

 <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
 <path class="analytics-outlook-solid" d="{{ $solidPath }}" vector-effect="non-scaling-stroke" />
 @if($dashedPath)
 <path class="analytics-outlook-dashed" d="{{ $dashedPath }}" vector-effect="non-scaling-stroke" />
 @endif
 </svg>

 <div class="analytics-outlook-markers">
 @foreach($coords as $coord)
 <span
 class="analytics-outlook-marker{{ $coord['forecast'] ? ' is-forecast' : '' }}"
 style="left: {{ $coord['x'] }}%; top: {{ $coord['y'] }}%"
 tabindex="0"
 role="img"
 data-chart-tip
 data-tip-title="{{ $coord['full'] }}"
 data-tip-rows="{{ json_encode([
 $coord['forecast']
 ? ['Forecast', $coord['count'].' projected '.($coord['count'] === 1 ? 'request' : 'requests')]
 : ['Observed', $coord['count'].' '.($coord['count'] === 1 ? 'request' : 'requests')],
 ]) }}"
 aria-label="{{ $coord['forecast'] ? 'Forecast' : 'Observed' }} {{ $coord['full'] }}: {{ $coord['count'] }} {{ $coord['count'] === 1 ? 'request' : 'requests' }}"
 >
 <span class="analytics-outlook-dot" aria-hidden="true"></span>
 <span class="analytics-outlook-tip" aria-hidden="true">{{ $coord['count'] }}</span>
 </span>
 @endforeach
 </div>
 </div>

 <div class="analytics-outlook-axis" aria-hidden="true">
 @foreach($coords as $coord)
 <span class="{{ $coord['forecast'] ? 'is-forecast' : '' }}">{{ $coord['label'] }}</span>
 @endforeach
 </div>
 </div>

 @if($scheduledTotal > 0)
 <p class="analytics-outlook-note is-scheduled">
 <x-icon name="calendar" size="14" aria-hidden="true" />
 <span>
 <strong>{{ $scheduledTotal }} {{ $scheduledTotal === 1 ? 'request is' : 'requests are' }} already scheduled for the next period.</strong>
 Filed records, not a projection.
 </span>
 </p>
 @endif
 </div>

 @if(($demand['history'] ?? []) !== [])
 <details class="analytics-fold">
 <summary>View historical periods</summary>
 <div class="analytics-fold-body">
 <table class="analytics-mini-table">
 <thead>
 <tr>
 <th scope="col">Historical period</th>
 <th scope="col" class="numeric">Observed requests</th>
 <th scope="col" class="numeric">Weight</th>
 </tr>
 </thead>
 <tbody>
 @foreach($demand['history'] as $row)
 <tr>
 <td>{{ $row['label'] }}</td>
 <td class="numeric">{{ $row['count'] }}</td>
 <td class="numeric">{{ $row['weight'] }}</td>
 </tr>
 @endforeach
 </tbody>
 </table>
 </div>
 </details>
 @endif
 </section>

 <section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.readiness']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="clipboard-check" size="15" /></span>
 <div>
 <h2>Forecast Readiness</h2>
 <p>Whether enough historical data exists to calculate the forecast.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.readiness']) }}" aria-label="View Forecast Readiness details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @php
 /*
 * Forecast Readiness indicators.
 *
 * These values are visualizations of the exact same readiness checks
 * already returned by ForecastService. No forecasting rule is changed.
 */
 $periodsComplete = (int) ($readiness['periods_complete'] ?? 0);
 $periodsRequired = max(1, (int) ($readiness['periods_required'] ?? 3));

 $periodsPercent = min(
 100,
 (int) round(($periodsComplete / $periodsRequired) * 100)
 );

 $historicalRequests = (int) ($readiness['observations'] ?? 0);
 $historicalRequestsRequired = max(
 1,
 (int) ($readiness['observations_required'] ?? 3)
 );

 $historicalRequestsPercent = min(
 100,
 (int) round(
 ($historicalRequests / $historicalRequestsRequired) * 100
 )
 );

 $periodsMet = (bool) ($readiness['periods_met'] ?? false);
 $requestsMet = (bool) ($readiness['observations_met'] ?? false);
@endphp

<div class="forecast-readiness-v2">

 {{-- Completed historical periods --}}
 <div class="forecast-readiness-v2-item">
 <div class="forecast-readiness-v2-top">
 <div class="forecast-readiness-v2-name">
 <span
 class="forecast-readiness-v2-mark {{ $periodsMet ? 'is-met' : 'is-unmet' }}"
 aria-hidden="true"
 >
 <x-icon
 :name="$periodsMet ? 'check-circle' : 'warning'"
 size="15"
 />
 </span>

 <span>Historical periods</span>
 </div>

 <strong class="forecast-readiness-v2-value">
 {{ $periodsComplete }} of {{ $periodsRequired }} required
 </strong>
 </div>

 <div
 class="forecast-readiness-v2-track"
 role="progressbar"
 aria-label="Historical periods readiness"
 aria-valuemin="0"
 aria-valuemax="{{ $periodsRequired }}"
 aria-valuenow="{{ min($periodsComplete, $periodsRequired) }}"
 >
 <span
 class="forecast-readiness-v2-fill {{ $periodsMet ? 'is-met' : 'is-unmet' }}"
 style="width: {{ $periodsPercent }}%"
 ></span>
 </div>

 <div class="forecast-readiness-v2-meta">
 <span>{{ $periodsPercent }}% complete</span>

 <span class="{{ $periodsMet ? 'is-met' : 'is-unmet' }}">
 {{ $periodsMet ? 'Requirement met' : 'More history required' }}
 </span>
 </div>
 </div>


 {{-- Historical borrowing observations --}}
 <div class="forecast-readiness-v2-item">
 <div class="forecast-readiness-v2-top">
 <div class="forecast-readiness-v2-name">
 <span
 class="forecast-readiness-v2-mark {{ $requestsMet ? 'is-met' : 'is-unmet' }}"
 aria-hidden="true"
 >
 <x-icon
 :name="$requestsMet ? 'check-circle' : 'warning'"
 size="15"
 />
 </span>

 <span>Historical requests</span>
 </div>

 <strong class="forecast-readiness-v2-value">
 {{ $historicalRequests }} recorded
 </strong>
 </div>

 <div
 class="forecast-readiness-v2-track"
 role="progressbar"
 aria-label="Historical request readiness"
 aria-valuemin="0"
 aria-valuemax="{{ $historicalRequestsRequired }}"
 aria-valuenow="{{ min($historicalRequests, $historicalRequestsRequired) }}"
 >
 <span
 class="forecast-readiness-v2-fill {{ $requestsMet ? 'is-met' : 'is-unmet' }}"
 style="width: {{ $historicalRequestsPercent }}%"
 ></span>
 </div>

 <div class="forecast-readiness-v2-meta">
 <span>
 Minimum required: {{ $historicalRequestsRequired }}
 </span>

 <span class="{{ $requestsMet ? 'is-met' : 'is-unmet' }}">
 {{ $requestsMet ? 'Requirement met' : 'More records required' }}
 </span>
 </div>
 </div>


 {{-- Overall forecast state --}}
 <div class="forecast-readiness-v2-state {{ $ready ? 'is-ready' : 'is-waiting' }}">
 <span class="forecast-readiness-v2-state-icon" aria-hidden="true">
 <x-icon
 :name="$ready ? 'check-circle' : 'warning'"
 size="16"
 />
 </span>

 <div>
 <strong>
 {{ $ready ? 'Forecast available' : 'Forecast unavailable' }}
 </strong>

 <p>
 {{ $ready
 ? ($demand['summary'] ?? '')
 : ($demand['requirement'] ?? 'Additional historical data is required.') }}
 </p>
 </div>
 </div>

</div>

 </div>
 </section>
</div>

{{-- Where the demand sits ------------------------------------------------ --}}
<div class="analytics-forecast-pair{{ $ready ? '' : ' is-waiting' }}">
 <section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.division']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="users" size="15" /></span>
 <div>
 {{-- The heading follows the data: a scheduled count is never called a forecast. --}}
 <h2>{{ $hasDivision ? 'Forecasted' : 'Scheduled' }} Demand by Division</h2>
 <p>{{ $hasDivision
 ? 'Projected requests for the next period.'
 : 'Requests already recorded for '.$windowLabel.'.' }}</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.division']) }}" aria-label="View Demand by Division details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if($divisionRows->isEmpty() || $divisionMax <= 0)
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="users" size="19" /></span>
 {{ $hasDivision
 ? 'No division is expected to record borrowing activity next period.'
 : 'No borrowing requests are recorded for the next period yet.' }}
 </p>
 @else
 <ol class="analytics-rank">
 @foreach($divisionRows as $index => $row)
 <li>
 <a
 class="analytics-rank-row"
 href="{{ AnalyticsDetailLink::to('division', 'predictive', $periodSelection, $row['code'], null, ['for' => $row['code']]) }}"
 data-chart-tip
 data-tip-title="{{ $row['label'] }}"
 data-tip-rows="{{ json_encode([[
 $hasDivision ? 'Projected requests' : 'Requests already recorded',
 (string) $row['value'],
 ]]) }}"
 aria-label="View Details for {{ $row['label'] }}"
 >
 <span class="analytics-rank-no">{{ $index + 1 }}</span>
 <span class="analytics-rank-main">
 <span class="analytics-rank-name">{{ $row['label'] }}</span>
 <span class="analytics-rank-track">
 <span class="analytics-rank-fill" style="width: {{ max(3, round($row['value'] / $divisionMax * 100)) }}%"></span>
 </span>
 </span>
 <span class="analytics-rank-value">
 {{ $row['value'] }}
 <small>{{ $row['value'] === 1 ? 'request' : 'requests' }}</small>
 </span>
 </a>
 </li>
 @endforeach
 </ol>
 @endif
 </div>
 </section>

 <section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.unit']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="id-badge" size="15" /></span>
 <div>
 <h2>{{ $hasUnit ? 'Forecasted' : 'Scheduled' }} Demand by Unit</h2>
 <p>{{ $hasUnit
 ? 'Units with enough history of their own to project.'
 : 'Requests already recorded for '.$windowLabel.'.' }}</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.unit']) }}" aria-label="View Demand by Unit details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if($unitRows->isEmpty() || $unitMax <= 0)
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="id-badge" size="19" /></span>
 {{ $hasUnit
 ? 'No unit is expected to record borrowing activity next period.'
 : 'No unit demand is recorded for the next period yet.' }}
 </p>
 @else
 <ol class="analytics-rank">
 @foreach($unitRows as $index => $row)
 <li>
 <a
 class="analytics-rank-row"
 href="{{ AnalyticsDetailLink::to('unit', 'predictive', $periodSelection, null, $row['unit'], ['for' => $row['unit']]) }}"
 data-chart-tip
 data-tip-title="{{ $row['unit'] }}"
 data-tip-rows="{{ json_encode([[
 $hasUnit ? 'Projected requests' : 'Requests already recorded',
 (string) $row['value'],
 ]]) }}"
 aria-label="View Details for {{ $row['unit'] }}"
 >
 <span class="analytics-rank-no">{{ $index + 1 }}</span>
 <span class="analytics-rank-main">
 <span class="analytics-rank-name">{{ $row['unit'] }}</span>
 <span class="analytics-rank-track">
 <span class="analytics-rank-fill" style="width: {{ max(3, round($row['value'] / $unitMax * 100)) }}%"></span>
 </span>
 </span>
 <span class="analytics-rank-value">
 {{ $row['value'] }}
 <small>{{ $row['value'] === 1 ? 'request' : 'requests' }}</small>
 </span>
 </a>
 </li>
 @endforeach
 </ol>
 @endif
 </div>
 </section>
</div>

{{-- Can the stock cover it ------------------------------------------------ --}}
<section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.equipment']) }}"
 class="analytics-card analytics-forecast-equipment">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="box" size="15" /></span>
 <div>
 <h2>Equipment Demand &amp; Availability</h2>
 <p>Expected demand against expected availability for {{ $windowLabel }}.</p>
 </div>
 @if($hasEquipment && ($equipmentForecast['at_risk_count'] ?? 0) > 0)
 <span class="analytics-count-pill">{{ $equipmentForecast['at_risk_count'] }} at risk</span>
 @endif
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.equipment']) }}" aria-label="View Equipment Demand and Availability details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if(! $hasEquipment)
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="box" size="19" /></span>
 Equipment demand cannot be projected yet.
 </p>
 @else
 <ul class="analytics-coverbars">
 @foreach($equipmentRows as $row)
 @php
 /* Both bars share one scale, so the two are comparable. */
 $scale = max(1, max($row['demand'], $row['expected_available']));
 @endphp
 <li class="{{ $row['status'] === 'Sufficient' ? '' : 'is-risk' }}">
 <a
 href="{{ AnalyticsDetailLink::to('equipment', 'predictive', $periodSelection, null, null, ['item' => $row['item_id']]) }}"
 data-chart-tip
 data-tip-title="{{ $row['name'] }}"
 data-tip-rows="{{ json_encode([
 ['Expected demand', $num((float) $row['demand'])],
 ['Expected available', $num((float) $row['expected_available'])],
 ['Status', $row['status']],
 ]) }}"
 >
 <span class="analytics-coverbars-head">
 <span class="analytics-coverbars-name">{{ $row['name'] }}</span>
 <span class="analytics-tag {{ $row['status'] === 'Sufficient' ? 'is-positive' : ($row['status'] === 'Limited' ? 'is-attention' : 'is-critical') }}">
 {{ $row['status'] }}
 </span>
 </span>

 <span class="analytics-coverbars-row">
 <span class="analytics-coverbars-key">Demand</span>
 <span class="analytics-coverbars-track">
 <span class="analytics-coverbars-fill is-demand" style="width: {{ max(2, round($row['demand'] / $scale * 100)) }}%"></span>
 </span>
 <span class="analytics-coverbars-value">{{ $num((float) $row['demand']) }}</span>
 </span>

 <span class="analytics-coverbars-row">
 <span class="analytics-coverbars-key">Available</span>
 <span class="analytics-coverbars-track">
 <span class="analytics-coverbars-fill is-available" style="width: {{ max(2, round($row['expected_available'] / $scale * 100)) }}%"></span>
 </span>
 <span class="analytics-coverbars-value">{{ $num((float) $row['expected_available']) }}</span>
 </span>
 </a>
 </li>
 @endforeach
 </ul>

 <p class="analytics-insight-strip">
 <x-icon name="information" size="14" aria-hidden="true" />
 <span>{{ $equipmentForecast['summary'] }}</span>
 </p>
 @endif
 </div>
</section>

{{-- Shape of the period and what it implies ------------------------------- --}}
<div class="analytics-forecast-pair{{ $ready ? '' : ' is-waiting' }}">
 <section id="expected-busy-period"
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.busy']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="calendar-clock" size="15" /></span>
 <div>
 <h2>Expected Busy Period</h2>
 <p>Projected borrowing demand across the next forecast period.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.busy']) }}" aria-label="View Expected Busy Period details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 @if(! $hasBusy)
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="calendar-clock" size="19" /></span>
 Insufficient history for busy-period forecasting.
 </p>
 @else
 <ul class="analytics-peak">
 @foreach($busyPeriod['buckets'] as $bucket)
 <li
 class="{{ $busyPeak > 0 && $bucket['expected'] === $busyPeak ? 'is-peak' : '' }}"
 tabindex="0"
 data-chart-tip
 data-tip-title="{{ $bucket['range'] }}"
 data-tip-rows="{{ json_encode(array_values(array_filter([
 ['Projected requests', (string) $bucket['expected']],
 $busyPeak > 0 && $bucket['expected'] === $busyPeak ? ['Busiest period', 'Yes'] : null,
 ]))) }}"
 aria-label="{{ $bucket['range'] }}: {{ $bucket['expected'] }} projected {{ $bucket['expected'] === 1 ? 'request' : 'requests' }}"
 >
 <span class="analytics-peak-label">{{ $bucket['label'] }}</span>
 <span class="analytics-peak-track">
 <span class="analytics-peak-fill" style="width: {{ $busyPeak > 0 ? max(2, round($bucket['expected'] / $busyPeak * 100)) : 2 }}%"></span>
 </span>
 <span class="analytics-peak-value">
 {{ $bucket['expected'] }}
 @if($busyPeak > 0 && $bucket['expected'] === $busyPeak)
 <small class="analytics-peak-tag">Peak</small>
 @endif
 </span>
 </li>
 @endforeach
 </ul>

 <p class="analytics-insight-strip">
 <x-icon name="lightbulb" size="14" aria-hidden="true" />
 <span>{{ $busySummary }}</span>
 </p>
 @endif
 </div>
 </section>

 <section
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.notes']) }}"
 class="analytics-card">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="lightbulb" size="15" /></span>
 <div>
 <h2>Planning Notes</h2>
 <p>What the figures above mean for the next period.</p>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.notes']) }}" aria-label="View Planning Notes details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body is-flush">
 @if($notes === [])
 <p class="analytics-blank is-visualization-empty">
 <span class="analytics-blank-mark" aria-hidden="true"><x-icon name="lightbulb" size="19" /></span>
 No planning signals are available for this period yet.
 </p>
 @else
 <ul class="analytics-notes">
 @foreach($notes as $note)
 <li class="tone-{{ $note['tone'] }}">
 <span class="analytics-notes-icon" aria-hidden="true"><x-icon :name="$note['icon']" size="15" /></span>
 <span>{{ $note['text'] }}</span>
 </li>
 @endforeach
 </ul>
 @endif
 </div>
 </section>
</div>

{{-- How the numbers were produced ----------------------------------------- --}}
<section
 data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.methodology']) }}"
 class="analytics-card forecast-methodology-compact">
 <header class="analytics-card-head">
 <span class="analytics-card-mark" aria-hidden="true"><x-icon name="help" size="15" /></span>
 <div>
 <h2>Forecast Methodology</h2>
 </div>
 <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'predictive', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'forecast.methodology']) }}" aria-label="View Forecast Methodology details"><x-icon name="chevron-right" size="15" /></a>
 </header>

 <div class="analytics-card-body">
 <div class="analytics-method">
 <div>
 <span>Method</span>
 <strong>{{ $readiness['method'] ?? 'Weighted Moving Average' }}</strong>
 </div>
 <div>
 <span>History used</span>
 <strong>{{ $readiness['periods_required'] ?? 3 }} completed periods</strong>
 </div>
 <div>
 <span>Weights</span>
 <strong>{{ implode(' - ', $readiness['weights'] ?? [3, 2, 1]) }}</strong>
 </div>
 </div>
 </div>

 <details class="analytics-fold">
 <summary>How forecasts are calculated</summary>
 <div class="analytics-fold-body">
 <p class="analytics-method-summary">{{ $forecastBasis['summary'] }}</p>
 <ul class="analytics-method-list">
 @foreach($forecastBasis['details'] as $detail)
 <li>{{ $detail }}</li>
 @endforeach
 </ul>
 </div>
 </details>
</section>
<style>
/* FORECAST_METHODOLOGY_COMPACT */

/*
 * Methodology stays visible for transparency but does not compete with the
 * operational forecast. Its summary figures remain immediately readable,
 * while the explanation stays collapsed until requested.
 */
.analytics-page .forecast-methodology-compact {
 margin-top: 0;
}

.analytics-page .forecast-methodology-compact .analytics-card-body,
.analytics-page .forecast-methodology-compact .forecast-methodology-body {
 padding-top: 12px;
 padding-bottom: 12px;
}

.analytics-page .forecast-methodology-compact details {
 border-top: 1px solid var(--border);
}

.analytics-page .forecast-methodology-compact details > summary {
 min-height: 40px;
 display: flex;
 align-items: center;
 gap: 7px;
 padding: 0 14px;
 color: var(--interactive);
 font-size: 10.5px;
 font-weight: 700;
 cursor: pointer;
 list-style: none;
}

.analytics-page .forecast-methodology-compact details > summary::-webkit-details-marker {
 display: none;
}

.analytics-page .forecast-methodology-compact details > summary::after {
 content: '⌄';
 margin-left: 2px;
 color: var(--text-muted);
 transition: transform 160ms ease;
}

.analytics-page .forecast-methodology-compact details[open] > summary::after {
 transform: rotate(180deg);
}

.analytics-page .forecast-methodology-compact details > :not(summary) {
 margin-left: 14px;
 margin-right: 14px;
}

@media (max-width: 720px) {
 .analytics-page .forecast-methodology-compact {
 margin-top: 0;
 }
}
</style>

<style>
/* FORECAST_READINESS_V2 */

.forecast-readiness-v2 {
 display: grid;
 gap: 12px;
}

.forecast-readiness-v2-item {
 display: grid;
 gap: 8px;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 11px;
 background: var(--surface-subtle);
}

.forecast-readiness-v2-top {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 14px;
}

.forecast-readiness-v2-name {
 display: flex;
 align-items: center;
 gap: 8px;
 min-width: 0;
 color: var(--heading);
 font-size: 11px;
 font-weight: 750;
}

.forecast-readiness-v2-mark {
 display: grid;
 place-items: center;
 width: 23px;
 height: 23px;
 flex: 0 0 23px;
 border-radius: 50%;
}

.forecast-readiness-v2-mark.is-met {
 color: var(--success);
 background: color-mix(
 in srgb,
 var(--success) 10%,
 transparent
 );
}

.forecast-readiness-v2-mark.is-unmet {
 color: var(--warning);
 background: color-mix(
 in srgb,
 var(--warning) 10%,
 transparent
 );
}

.forecast-readiness-v2-value {
 color: var(--heading);
 font-size: 11px;
 font-weight: 800;
 white-space: nowrap;
 font-variant-numeric: tabular-nums;
}

.forecast-readiness-v2-track {
 position: relative;
 width: 100%;
 height: 8px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-muted);
}

.forecast-readiness-v2-fill {
 display: block;
 height: 100%;
 min-width: 2px;
 border-radius: inherit;
 transition: width 180ms ease;
}

.forecast-readiness-v2-fill.is-met {
 background: var(--success);
}

.forecast-readiness-v2-fill.is-unmet {
 background: var(--warning);
}

.forecast-readiness-v2-meta {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 color: var(--text-muted);
 font-size: 9.5px;
 line-height: 1.3;
}

.forecast-readiness-v2-meta .is-met {
 color: var(--success);
 font-weight: 700;
}

.forecast-readiness-v2-meta .is-unmet {
 color: var(--warning);
 font-weight: 700;
}

.forecast-readiness-v2-state {
 display: grid;
 grid-template-columns: auto minmax(0, 1fr);
 align-items: start;
 gap: 9px;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 11px;
}

.forecast-readiness-v2-state.is-ready {
 border-color: color-mix(
 in srgb,
 var(--success) 35%,
 var(--border)
 );

 background: color-mix(
 in srgb,
 var(--success) 6%,
 var(--surface-elevated)
 );
}

.forecast-readiness-v2-state.is-waiting {
 border-color: color-mix(
 in srgb,
 var(--warning) 35%,
 var(--border)
 );

 background: color-mix(
 in srgb,
 var(--warning) 6%,
 var(--surface-elevated)
 );
}

.forecast-readiness-v2-state-icon {
 display: grid;
 place-items: center;
 margin-top: 1px;
}

.forecast-readiness-v2-state.is-ready
.forecast-readiness-v2-state-icon {
 color: var(--success);
}

.forecast-readiness-v2-state.is-waiting
.forecast-readiness-v2-state-icon {
 color: var(--warning);
}

.forecast-readiness-v2-state strong {
 display: block;
 color: var(--heading);
 font-size: 11px;
 font-weight: 800;
}

.forecast-readiness-v2-state p {
 margin: 3px 0 0;
 color: var(--text-secondary);
 font-size: 10px;
 line-height: 1.4;
}

@media (max-width: 560px) {
 .forecast-readiness-v2-top,
 .forecast-readiness-v2-meta {
 align-items: flex-start;
 flex-direction: column;
 gap: 5px;
 }
}
</style>

{{-- FORECAST VERTICAL COLUMN CHARTS START --}}

@php
    /*
     * Division forecast rows.
     *
     * These values come directly from ForecastService output.
     * No forecast is recalculated in the view.
     */
    $forecastDivisionColumnRows = collect(
        $divisionForecast['groups'] ?? []
    )
        ->map(static function (array $row): array {
            return [
                'label' => (string) (
                    $row['short_label']
                    ?? $row['label']
                    ?? 'Division'
                ),

                'value' => (int) (
                    $row['forecast']
                    ?? 0
                ),
            ];
        })
        ->sortByDesc('value')
        ->take(5)
        ->values();


    /*
     * Scheduled unit rows.
     *
     * scheduled.units.columns may itself be a Collection, so
     * everything is normalized through collect() before flattening.
     */
    $forecastScheduledColumns = collect(
        data_get(
            $scheduled ?? [],
            'units.columns',
            []
        )
    );

    $forecastScheduledColumnRows =
        $forecastScheduledColumns
            ->flatMap(static function ($column) {

                if (
                    $column instanceof
                    \Illuminate\Contracts\Support\Arrayable
                ) {
                    $column = $column->toArray();
                }

                if (!is_array($column)) {
                    return [];
                }

                return collect(
                    $column['units'] ?? []
                );
            })
            ->map(static function ($row): array {

                if (
                    $row instanceof
                    \Illuminate\Contracts\Support\Arrayable
                ) {
                    $row = $row->toArray();
                }

                $row = is_array($row)
                    ? $row
                    : [];

                return [
                    'label' => (string) (
                        $row['name']
                        ?? $row['unit']
                        ?? 'Unit'
                    ),

                    'value' => (int) (
                        $row['count']
                        ?? 0
                    ),
                ];
            })
            ->filter(
                static fn (array $row): bool =>
                    trim($row['label']) !== ''
            )
            ->sortByDesc('value')
            ->take(5)
            ->values();
@endphp


<style>
/* ==========================================================
   FORECAST VERTICAL COLUMN CHART
   ========================================================== */

.analytics-page .forecast-column-chart {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr);
    gap: 8px;
    width: 100%;
    min-height: 210px;
    padding: 16px 14px 12px;
}

.analytics-page .forecast-column-y {
    position: relative;
    height: 158px;
    color: var(--text-muted);
    font-size: 9px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.analytics-page .forecast-column-y span {
    position: absolute;
    right: 0;
}

.analytics-page .forecast-column-y .is-top {
    top: 0;
}

.analytics-page .forecast-column-y .is-middle {
    top: 50%;
    transform: translateY(-50%);
}

.analytics-page .forecast-column-y .is-bottom {
    bottom: 0;
}

.analytics-page .forecast-column-main {
    min-width: 0;
}

.analytics-page .forecast-column-plot {
    position: relative;
    height: 158px;
    min-width: 0;
}

.analytics-page .forecast-column-gridline {
    position: absolute;
    left: 0;
    right: 0;
    border-top: 1px solid var(--border);
    pointer-events: none;
}

.analytics-page .forecast-column-gridline.is-top {
    top: 0;
}

.analytics-page .forecast-column-gridline.is-middle {
    top: 50%;
}

.analytics-page .forecast-column-gridline.is-bottom {
    bottom: 0;
}

.analytics-page .forecast-column-items {
    position: absolute;
    inset: 0 8px;
    display: flex;
    align-items: stretch;
    justify-content: space-around;
    gap: clamp(12px, 4%, 28px);
}

.analytics-page .forecast-column-item {
    flex: 1 1 0;
    min-width: 48px;
    max-width: 100px;
    display: grid;
    grid-template-rows: 20px minmax(0, 1fr);
    gap: 4px;
}

.analytics-page .forecast-column-value {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heading);
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.analytics-page .forecast-column-bar-area {
    position: relative;
    min-height: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.analytics-page .forecast-column-bar {
    width: clamp(28px, 58%, 52px);
    min-height: 3px;
    border-radius: 5px 5px 0 0;
    background:
        linear-gradient(
            180deg,
            #3b82f6 0%,
            #2563eb 100%
        );
    box-shadow:
        0 1px 1px rgba(37, 99, 235, 0.12);
    transition:
        height 180ms ease,
        opacity 180ms ease;
}

.analytics-page .forecast-column-bar.is-zero {
    height: 3px !important;
    opacity: 0.25;
}

.analytics-page .forecast-column-labels {
    display: flex;
    justify-content: space-around;
    gap: clamp(12px, 4%, 28px);
    padding: 7px 8px 0;
}

.analytics-page .forecast-column-label {
    flex: 1 1 0;
    min-width: 48px;
    max-width: 100px;
    color: var(--text-secondary);
    font-size: 9.5px;
    font-weight: 650;
    line-height: 1.25;
    text-align: center;
    overflow-wrap: anywhere;
}

.analytics-page .forecast-column-axis-title {
    margin-top: 9px;
    color: var(--text-muted);
    font-size: 9.5px;
    text-align: center;
}

.analytics-page .forecast-column-body {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
}

.analytics-page .forecast-column-inner {
    min-width: 360px;
}


/* ----------------------------------------------------------
   Dark mode
   ---------------------------------------------------------- */

html[data-theme="dark"]
.analytics-page
.forecast-column-bar {
    background:
        linear-gradient(
            180deg,
            #60a5fa 0%,
            #3b82f6 100%
        );
}


/* ----------------------------------------------------------
   Responsive
   ---------------------------------------------------------- */

@media (max-width: 720px) {

    .analytics-page .forecast-column-chart {
        min-height: 195px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .analytics-page .forecast-column-plot,
    .analytics-page .forecast-column-y {
        height: 145px;
    }

    .analytics-page .forecast-column-label {
        font-size: 9px;
    }
}
</style>


<script>
(() => {

    const divisionRows =
        @json($forecastDivisionColumnRows);

    const scheduledRows =
        @json($forecastScheduledColumnRows);


    const normalizeRows = (rows) => {

        if (!Array.isArray(rows)) {
            return [];
        }

        return rows
            .map((row) => ({
                label:
                    String(
                        row?.label
                        ?? ''
                    ).trim(),

                value:
                    Math.max(
                        0,
                        Number.parseInt(
                            row?.value
                            ?? 0,
                            10
                        ) || 0
                    ),
            }))
            .filter(
                (row) =>
                    row.label !== ''
            );
    };


    const findCard = (title) => {

        const heading =
            Array.from(
                document.querySelectorAll(
                    '.analytics-page h2'
                )
            )
            .find(
                (element) =>
                    element.textContent
                        .trim()
                        .toLowerCase()
                    ===
                    title.toLowerCase()
            );

        if (!heading) {
            return null;
        }

        return heading.closest(
            'section.analytics-card, .analytics-card'
        );
    };


    const makeChart = (
        rows,
        axisTitle
    ) => {

        rows = normalizeRows(rows);

        if (rows.length === 0) {
            return null;
        }

        const maxValue =
            Math.max(
                1,
                ...rows.map(
                    (row) => row.value
                )
            );

        const middleValue =
            Math.ceil(
                maxValue / 2
            );


        const wrapper =
            document.createElement('div');

        wrapper.className =
            'forecast-column-body';


        const inner =
            document.createElement('div');

        inner.className =
            'forecast-column-inner';


        const chart =
            document.createElement('div');

        chart.className =
            'forecast-column-chart';

        chart.setAttribute(
            'role',
            'img'
        );

        chart.setAttribute(
            'aria-label',
            axisTitle
        );


        const yAxis =
            document.createElement('div');

        yAxis.className =
            'forecast-column-y';

        yAxis.innerHTML = `
            <span class="is-top"></span>
            <span class="is-middle"></span>
            <span class="is-bottom">0</span>
        `;

        yAxis.querySelector(
            '.is-top'
        ).textContent =
            String(maxValue);

        yAxis.querySelector(
            '.is-middle'
        ).textContent =
            String(middleValue);


        const main =
            document.createElement('div');

        main.className =
            'forecast-column-main';


        const plot =
            document.createElement('div');

        plot.className =
            'forecast-column-plot';

        plot.innerHTML = `
            <span class="forecast-column-gridline is-top"></span>
            <span class="forecast-column-gridline is-middle"></span>
            <span class="forecast-column-gridline is-bottom"></span>
        `;


        const items =
            document.createElement('div');

        items.className =
            'forecast-column-items';


        rows.forEach((row) => {

            const item =
                document.createElement('div');

            item.className =
                'forecast-column-item';


            const value =
                document.createElement('strong');

            value.className =
                'forecast-column-value';

            value.textContent =
                String(row.value);


            const barArea =
                document.createElement('div');

            barArea.className =
                'forecast-column-bar-area';


            const bar =
                document.createElement('span');

            bar.className =
                'forecast-column-bar'
                + (
                    row.value === 0
                        ? ' is-zero'
                        : ''
                );


            const height =
                row.value === 0
                    ? 0
                    : Math.max(
                        6,
                        Math.round(
                            (
                                row.value
                                / maxValue
                            )
                            * 100
                        )
                    );


            bar.style.height =
                `${height}%`;

            bar.title =
                `${row.label}: ${row.value} `
                + (
                    row.value === 1
                        ? 'request'
                        : 'requests'
                );


            barArea.appendChild(
                bar
            );

            item.appendChild(
                value
            );

            item.appendChild(
                barArea
            );

            items.appendChild(
                item
            );
        });


        plot.appendChild(
            items
        );


        const labels =
            document.createElement('div');

        labels.className =
            'forecast-column-labels';


        rows.forEach((row) => {

            const label =
                document.createElement('div');

            label.className =
                'forecast-column-label';

            label.textContent =
                row.label;

            labels.appendChild(
                label
            );
        });


        const axis =
            document.createElement('div');

        axis.className =
            'forecast-column-axis-title';

        axis.textContent =
            axisTitle;


        main.appendChild(
            plot
        );

        main.appendChild(
            labels
        );

        main.appendChild(
            axis
        );


        chart.appendChild(
            yAxis
        );

        chart.appendChild(
            main
        );


        inner.appendChild(
            chart
        );

        wrapper.appendChild(
            inner
        );


        return wrapper;
    };


    const replaceCardBody = (
        title,
        rows,
        axisTitle
    ) => {

        const card =
            findCard(title);

        if (!card) {
            return;
        }

        /*
         * Never touch the header, detail link, or card itself.
         * Only the existing body content is replaced.
         */
        const body =
            card.querySelector(
                ':scope > .analytics-card-body'
            )
            ||
            card.querySelector(
                '.analytics-card-body'
            );

        if (!body) {
            return;
        }


        const chart =
            makeChart(
                rows,
                axisTitle
            );


        /*
         * No data:
         * preserve the original empty state already rendered by Laravel.
         */
        if (!chart) {

            body.dataset.forecastColumnEmpty =
                '1';

            return;
        }


        body.replaceChildren(
            chart
        );

        body.dataset.forecastColumnChart =
            '1';
    };


    const render = () => {

        replaceCardBody(
            'Forecasted Demand by Division',
            divisionRows,
            'Projected requests'
        );


        replaceCardBody(
            'Scheduled Demand by Unit',
            scheduledRows,
            'Scheduled requests'
        );
    };


    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            render,
            { once: true }
        );

    } else {

        render();
    }

})();
</script>

{{-- FORECAST VERTICAL COLUMN CHARTS END --}}

{{-- EQUIPMENT CAPACITY BULLET V1 START --}}

@php
    /*
     * Presentation rows only.
     * Forecast calculations remain in ForecastService.
     */
    $equipmentCapacityRows = collect($equipmentRows ?? [])
        ->map(static function ($row): array {

            if (
                $row instanceof
                \Illuminate\Contracts\Support\Arrayable
            ) {
                $row = $row->toArray();
            }

            $row = is_array($row)
                ? $row
                : [];

            return [
                'name' => (string) (
                    $row['name']
                    ?? 'Equipment'
                ),

                'demand' => max(
                    0,
                    (int) (
                        $row['demand']
                        ?? 0
                    )
                ),

                'available' => max(
                    0,
                    (int) (
                        $row['expected_available']
                        ?? $row['available']
                        ?? 0
                    )
                ),
            ];
        })
        ->values();
@endphp


@if($equipmentCapacityRows->isNotEmpty())

<style>
/* ==========================================================
   EQUIPMENT CAPACITY / BULLET CHART
   ========================================================== */

.analytics-page .equipment-capacity-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 10px;
    padding: 14px;
}

.analytics-page .equipment-capacity-item {
    display: grid;
    gap: 10px;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: var(--surface-subtle, #f8fafc);
}

.analytics-page .equipment-capacity-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.analytics-page .equipment-capacity-name {
    min-width: 0;
    margin: 0;
    color: var(--heading);
    font-size: 12px;
    font-weight: 800;
    line-height: 1.3;
}

.analytics-page .equipment-capacity-status {
    flex: 0 0 auto;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0.025em;
    text-transform: uppercase;
}

.analytics-page .equipment-capacity-status.is-sufficient {
    color: #047857;
    border: 1px solid #a7dfc5;
    background: #ecfdf5;
}

.analytics-page .equipment-capacity-status.is-capacity {
    color: #a16207;
    border: 1px solid #f1d48d;
    background: #fffbeb;
}

.analytics-page .equipment-capacity-status.is-shortage {
    color: #b42318;
    border: 1px solid #f2b8b5;
    background: #fff1f0;
}


/* ----------------------------------------------------------
   Main reading
   ---------------------------------------------------------- */

.analytics-page .equipment-capacity-reading {
    display: flex;
    align-items: baseline;
    gap: 5px;
    color: var(--text-secondary);
    font-size: 10px;
}

.analytics-page .equipment-capacity-reading strong {
    color: var(--heading);
    font-size: 12px;
    font-weight: 800;
}


/* ----------------------------------------------------------
   Capacity track
   ---------------------------------------------------------- */

.analytics-page .equipment-capacity-track {
    position: relative;
    width: 100%;
    height: 11px;
    overflow: hidden;
    border-radius: 999px;
    background: #e8eef5;
}

.analytics-page .equipment-capacity-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    transition: width 180ms ease;
}

.analytics-page .equipment-capacity-fill.is-sufficient {
    background: #11896f;
}

.analytics-page .equipment-capacity-fill.is-capacity {
    background: #d79a16;
}

.analytics-page .equipment-capacity-fill.is-shortage {
    background: #dc4c45;
}


/* ----------------------------------------------------------
   Detail line
   ---------------------------------------------------------- */

.analytics-page .equipment-capacity-meta {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    color: var(--text-muted);
    font-size: 9.5px;
    line-height: 1.35;
}

.analytics-page .equipment-capacity-meta strong {
    flex: 0 0 auto;
    color: var(--text-secondary);
    font-size: 9.5px;
    font-weight: 750;
    text-align: right;
}

.analytics-page
.equipment-capacity-meta
strong.is-shortage {
    color: #b42318;
}

.analytics-page
.equipment-capacity-meta
strong.is-capacity {
    color: #a16207;
}


/* ----------------------------------------------------------
   Summary strip
   ---------------------------------------------------------- */

.analytics-page .equipment-capacity-summary {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 14px 14px;
    padding: 10px 12px;
    border-radius: 9px;
    font-size: 10px;
    line-height: 1.4;
}

.analytics-page
.equipment-capacity-summary.is-covered {
    color: #075f4e;
    background: #edf9f5;
    border: 1px solid #bce5d8;
}

.analytics-page
.equipment-capacity-summary.is-warning {
    color: #9f261f;
    background: #fff3f2;
    border: 1px solid #f1c0bd;
}

.analytics-page .equipment-capacity-summary-icon {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 900;
}

.analytics-page
.equipment-capacity-summary.is-covered
.equipment-capacity-summary-icon {
    color: #047857;
    background: #d9f4ea;
}

.analytics-page
.equipment-capacity-summary.is-warning
.equipment-capacity-summary-icon {
    color: #b42318;
    background: #ffe0dd;
}


/* ----------------------------------------------------------
   Dark mode
   ---------------------------------------------------------- */

html[data-theme="dark"]
.analytics-page
.equipment-capacity-item {
    background: var(--surface-subtle);
}

html[data-theme="dark"]
.analytics-page
.equipment-capacity-track {
    background: rgba(148, 163, 184, 0.18);
}


/* ----------------------------------------------------------
   Responsive
   ---------------------------------------------------------- */

@media (max-width: 760px) {

    .analytics-page .equipment-capacity-grid {
        grid-template-columns: 1fr;
    }

    .analytics-page .equipment-capacity-meta {
        flex-direction: column;
        gap: 4px;
    }

    .analytics-page .equipment-capacity-meta strong {
        text-align: left;
    }
}
</style>


<script>
(() => {

    const rows =
        @json($equipmentCapacityRows);


    const findEquipmentCard = () => {

        const heading =
            Array.from(
                document.querySelectorAll(
                    '.analytics-page h2'
                )
            )
            .find(
                (node) =>
                    node.textContent
                        .trim()
                        .toLowerCase()
                    ===
                    'equipment demand & availability'
                        .toLowerCase()
            );

        if (!heading) {
            return null;
        }

        return heading.closest(
            'section.analytics-card, .analytics-card'
        );
    };


    const plural = (
        value,
        singular,
        pluralWord
    ) => {

        return Number(value) === 1
            ? singular
            : pluralWord;
    };


    const render = () => {

        if (!Array.isArray(rows) || rows.length === 0) {
            return;
        }


        const card =
            findEquipmentCard();

        if (!card) {
            return;
        }


        const body =
            card.querySelector(
                ':scope > .analytics-card-body'
            )
            ||
            card.querySelector(
                '.analytics-card-body'
            );


        if (!body) {
            return;
        }


        const grid =
            document.createElement('div');

        grid.className =
            'equipment-capacity-grid';


        let shortageCount = 0;
        let capacityCount = 0;


        rows.forEach((raw) => {

            const name =
                String(
                    raw?.name
                    ?? 'Equipment'
                );


            const demand =
                Math.max(
                    0,
                    Number.parseInt(
                        raw?.demand
                        ?? 0,
                        10
                    ) || 0
                );


            const available =
                Math.max(
                    0,
                    Number.parseInt(
                        raw?.available
                        ?? 0,
                        10
                    ) || 0
                );


            const actualPercent =
                available > 0
                    ? Math.round(
                        (
                            demand
                            / available
                        ) * 100
                    )
                    : (
                        demand > 0
                            ? 100
                            : 0
                    );


            const barPercent =
                Math.min(
                    100,
                    actualPercent
                );


            let state =
                'sufficient';

            let status =
                'Sufficient';


            if (demand > available) {

                state = 'shortage';
                status = 'Shortage';
                shortageCount++;

            } else if (
                demand > 0
                &&
                demand === available
            ) {

                state = 'capacity';
                status = 'At Capacity';
                capacityCount++;
            }


            const remaining =
                available - demand;


            const item =
                document.createElement('article');

            item.className =
                'equipment-capacity-item';


            item.innerHTML = `
                <div class="equipment-capacity-head">

                    <h3 class="equipment-capacity-name"></h3>

                    <span
                        class="equipment-capacity-status is-${state}"
                    ></span>

                </div>

                <div class="equipment-capacity-reading">

                    <strong></strong>

                    <span>
                        forecasted /
                    </span>

                    <strong></strong>

                    <span>
                        available
                    </span>

                </div>

                <div
                    class="equipment-capacity-track"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <span
                        class="equipment-capacity-fill is-${state}"
                    ></span>
                </div>

                <div class="equipment-capacity-meta">

                    <span
                        class="equipment-capacity-percentage"
                    ></span>

                    <strong
                        class="equipment-capacity-balance is-${state}"
                    ></strong>

                </div>
            `;


            item
                .querySelector(
                    '.equipment-capacity-name'
                )
                .textContent =
                    name;


            item
                .querySelector(
                    '.equipment-capacity-status'
                )
                .textContent =
                    status;


            const readingValues =
                item.querySelectorAll(
                    '.equipment-capacity-reading strong'
                );


            readingValues[0].textContent =
                String(demand);

            readingValues[1].textContent =
                String(available);


            const track =
                item.querySelector(
                    '.equipment-capacity-track'
                );

            track.setAttribute(
                'aria-valuenow',
                String(
                    Math.min(
                        actualPercent,
                        100
                    )
                )
            );


            const fill =
                item.querySelector(
                    '.equipment-capacity-fill'
                );

            fill.style.width =
                `${barPercent}%`;


            const percentage =
                item.querySelector(
                    '.equipment-capacity-percentage'
                );


            percentage.textContent =
                available > 0
                    ? `${actualPercent}% of available stock expected to be needed`
                    : (
                        demand > 0
                            ? 'Demand exists but no expected availability is recorded'
                            : 'No forecasted demand'
                    );


            const balance =
                item.querySelector(
                    '.equipment-capacity-balance'
                );


            if (remaining < 0) {

                const shortage =
                    Math.abs(remaining);

                balance.textContent =
                    `Short by ${shortage} `
                    +
                    plural(
                        shortage,
                        'unit',
                        'units'
                    );

            } else {

                balance.textContent =
                    `${remaining} `
                    +
                    plural(
                        remaining,
                        'unit remaining',
                        'units remaining'
                    );
            }


            grid.appendChild(
                item
            );
        });



        /* ====================================================
           Bottom planning message
           ==================================================== */

        const summary =
            document.createElement('div');


        if (shortageCount > 0) {

            summary.className =
                'equipment-capacity-summary is-warning';

            summary.innerHTML = `
                <span
                    class="equipment-capacity-summary-icon"
                    aria-hidden="true"
                >
                    !
                </span>

                <span></span>
            `;


            summary
                .querySelector(
                    'span:last-child'
                )
                .textContent =
                    `${shortageCount} `
                    +
                    plural(
                        shortageCount,
                        'equipment type may',
                        'equipment types may'
                    )
                    +
                    ' have insufficient availability for the forecasted demand.';

        } else {

            summary.className =
                'equipment-capacity-summary is-covered';

            summary.innerHTML = `
                <span
                    class="equipment-capacity-summary-icon"
                    aria-hidden="true"
                >
                    ✓
                </span>

                <span>
                    All forecasted equipment demand is currently covered by expected availability.
                </span>
            `;
        }


        /*
         * Replace only the body of this exact card.
         * Header, title, subtitle and drilldown arrow remain untouched.
         */
        body.replaceChildren(
            grid,
            summary
        );


        body.dataset.equipmentCapacity =
            '1';
    };


    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            render,
            { once: true }
        );

    } else {

        render();
    }

})();
</script>

@endif

{{-- EQUIPMENT CAPACITY BULLET V1 END --}}

{{-- EXPECTED BUSY PERIOD COLUMN V1 START --}}

@php
    /*
     * Presentation only.
     * Uses the same forecast buckets already produced for
     * Expected Busy Period.
     */
    $busyColumnRows = collect(
        $busyPeriod['buckets'] ?? []
    )
        ->map(static function ($bucket): array {

            if (
                $bucket instanceof
                \Illuminate\Contracts\Support\Arrayable
            ) {
                $bucket = $bucket->toArray();
            }

            $bucket = is_array($bucket)
                ? $bucket
                : [];

            return [
                'label' => (string) (
                    $bucket['label']
                    ?? 'Period'
                ),

                'range' => (string) (
                    $bucket['range']
                    ?? ''
                ),

                'expected' => max(
                    0,
                    (int) (
                        $bucket['expected']
                        ?? 0
                    )
                ),
            ];
        })
        ->values();

    $busyColumnPeak = (int) (
        $busyColumnRows->max('expected')
        ?? 0
    );

    $busyColumnPeakRows =
        $busyColumnPeak > 0
            ? $busyColumnRows
                ->filter(
                    static fn (array $row): bool =>
                        $row['expected'] === $busyColumnPeak
                )
                ->values()
            : collect();

    $busyColumnPrimaryPeak =
        $busyColumnPeakRows->first();
@endphp


<style>
/* ==========================================================
   EXPECTED BUSY PERIOD - VERTICAL COLUMN CHART
   ========================================================== */

.analytics-page .busy-column-chart {
    display: grid;
    grid-template-columns: 28px minmax(0, 1fr);
    gap: 9px;

    padding: 16px 16px 8px;
}

.analytics-page .busy-column-yaxis {
    position: relative;
    height: 165px;

    color: var(--text-muted);
    font-size: 9px;
    line-height: 1;

    font-variant-numeric: tabular-nums;
}

.analytics-page .busy-column-yaxis span {
    position: absolute;
    right: 0;
}

.analytics-page .busy-column-yaxis .is-top {
    top: 0;
}

.analytics-page .busy-column-yaxis .is-middle {
    top: 50%;
    transform: translateY(-50%);
}

.analytics-page .busy-column-yaxis .is-bottom {
    bottom: 0;
}

.analytics-page .busy-column-main {
    min-width: 0;
}

.analytics-page .busy-column-plot {
    position: relative;
    height: 165px;
}

.analytics-page .busy-column-gridline {
    position: absolute;
    left: 0;
    right: 0;

    border-top: 1px solid var(--border);

    pointer-events: none;
}

.analytics-page .busy-column-gridline.is-top {
    top: 0;
}

.analytics-page .busy-column-gridline.is-middle {
    top: 50%;
}

.analytics-page .busy-column-gridline.is-bottom {
    bottom: 0;
}

.analytics-page .busy-column-columns {
    position: absolute;
    inset: 0 8px;

    display: flex;
    align-items: stretch;
    justify-content: space-around;

    gap: clamp(10px, 3.5%, 24px);
}

.analytics-page .busy-column-item {
    position: relative;

    flex: 1 1 0;
    min-width: 44px;
    max-width: 82px;

    display: grid;
    grid-template-rows:
        20px
        minmax(0, 1fr);
}

.analytics-page .busy-column-value {
    display: flex;
    align-items: center;
    justify-content: center;

    color: var(--heading);

    font-size: 11px;
    font-weight: 800;
    line-height: 1;

    font-variant-numeric: tabular-nums;
}

.analytics-page .busy-column-bar-area {
    position: relative;

    display: flex;
    align-items: flex-end;
    justify-content: center;

    min-height: 0;
}

.analytics-page .busy-column-bar {
    width: clamp(26px, 58%, 48px);

    min-height: 0;

    border-radius: 5px 5px 0 0;

    background:
        linear-gradient(
            180deg,
            #3b82f6 0%,
            #2563eb 100%
        );

    transition:
        height 180ms ease,
        transform 150ms ease;
}

.analytics-page .busy-column-item:hover
.busy-column-bar {
    transform: translateY(-2px);
}

.analytics-page .busy-column-item.is-peak
.busy-column-bar {
    background:
        linear-gradient(
            180deg,
            #16a085 0%,
            #0f8a6a 100%
        );
}

.analytics-page .busy-column-item.is-zero
.busy-column-bar {
    height: 0 !important;
    min-height: 0;
    background: transparent;
}

.analytics-page .busy-column-peak {
    position: absolute;

    left: 50%;
    bottom: 7px;

    transform: translateX(-50%);

    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 36px;

    padding: 3px 6px;

    border-radius: 999px;

    color: #047857;
    background: #e5f8f1;
    border: 1px solid #b9e8d7;

    font-size: 8px;
    font-weight: 800;
    line-height: 1;

    text-transform: uppercase;
}

.analytics-page .busy-column-labels {
    display: flex;
    justify-content: space-around;

    gap: clamp(10px, 3.5%, 24px);

    padding: 8px 8px 0;
}

.analytics-page .busy-column-label {
    flex: 1 1 0;

    min-width: 44px;
    max-width: 82px;

    color: var(--text-secondary);

    font-size: 9.5px;
    font-weight: 650;
    line-height: 1.25;

    text-align: center;
}

.analytics-page .busy-column-axis-title {
    margin-top: 9px;

    color: var(--text-muted);

    font-size: 9.5px;
    text-align: center;
}


/* ==========================================================
   BUSY PERIOD INSIGHT
   ========================================================== */

.analytics-page .busy-column-insight {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 9px;
    align-items: start;

    margin: 8px 14px 14px;
    padding: 10px 12px;

    border-radius: 9px;

    color: #075f4e;
    background: #edf9f5;
    border: 1px solid #bce5d8;
}

.analytics-page .busy-column-insight-icon {
    display: grid;
    place-items: center;

    width: 22px;
    height: 22px;

    border-radius: 50%;

    color: #047857;
    background: #d9f4ea;

    font-size: 11px;
    font-weight: 900;
}

.analytics-page .busy-column-insight strong {
    display: block;

    color: var(--heading);

    font-size: 10.5px;
    font-weight: 800;
    line-height: 1.35;
}

.analytics-page .busy-column-insight span {
    display: block;

    margin-top: 2px;

    color: var(--text-secondary);

    font-size: 9.5px;
    line-height: 1.4;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 680px) {

    .analytics-page .busy-column-chart {
        padding-left: 10px;
        padding-right: 10px;
    }

    .analytics-page .busy-column-plot,
    .analytics-page .busy-column-yaxis {
        height: 145px;
    }

    .analytics-page .busy-column-columns,
    .analytics-page .busy-column-labels {
        gap: 7px;
    }

    .analytics-page .busy-column-item,
    .analytics-page .busy-column-label {
        min-width: 38px;
    }

    .analytics-page .busy-column-label {
        font-size: 8.5px;
    }
}


/* ==========================================================
   DARK MODE
   ========================================================== */

html[data-theme="dark"]
.analytics-page
.busy-column-item:not(.is-peak)
.busy-column-bar {
    background:
        linear-gradient(
            180deg,
            #60a5fa 0%,
            #3b82f6 100%
        );
}

html[data-theme="dark"]
.analytics-page
.busy-column-insight {
    background:
        rgba(
            4,
            120,
            87,
            0.08
        );
}
</style>


<script>
(() => {

    const rows =
        @json($busyColumnRows);

    const peak =
        Number(
            @json($busyColumnPeak)
        ) || 0;

    const primaryPeak =
        @json($busyColumnPrimaryPeak);


    const findBusyCard = () => {

        const heading =
            Array.from(
                document.querySelectorAll(
                    '.analytics-page h2'
                )
            )
            .find((element) => {

                return (
                    element.textContent
                        .trim()
                        .toLowerCase()
                    ===
                    'expected busy period'
                );
            });


        if (!heading) {
            return null;
        }


        return heading.closest(
            'section.analytics-card, .analytics-card'
        );
    };


    const render = () => {

        if (
            !Array.isArray(rows)
            ||
            rows.length === 0
        ) {
            return;
        }


        const card =
            findBusyCard();


        if (!card) {
            return;
        }


        const body =
            card.querySelector(
                ':scope > .analytics-card-body'
            )
            ||
            card.querySelector(
                '.analytics-card-body'
            );


        if (!body) {
            return;
        }


        /*
         * Do not replace the existing unavailable state.
         */
        if (peak <= 0) {
            return;
        }


        const maxValue =
            Math.max(
                1,
                peak,
                ...rows.map(
                    (row) =>
                        Number(
                            row?.expected
                            ?? 0
                        ) || 0
                )
            );


        const middleValue =
            Math.ceil(
                maxValue / 2
            );


        const chart =
            document.createElement(
                'div'
            );


        chart.className =
            'busy-column-chart';


        chart.setAttribute(
            'role',
            'img'
        );


        chart.setAttribute(
            'aria-label',
            'Projected borrowing demand by week'
        );


        /* ----------------------------------------------
           Y axis
           ---------------------------------------------- */

        const yAxis =
            document.createElement(
                'div'
            );


        yAxis.className =
            'busy-column-yaxis';


        yAxis.innerHTML = `
            <span class="is-top"></span>
            <span class="is-middle"></span>
            <span class="is-bottom">0</span>
        `;


        yAxis
            .querySelector(
                '.is-top'
            )
            .textContent =
                String(
                    maxValue
                );


        yAxis
            .querySelector(
                '.is-middle'
            )
            .textContent =
                String(
                    middleValue
                );


        /* ----------------------------------------------
           Main chart
           ---------------------------------------------- */

        const main =
            document.createElement(
                'div'
            );


        main.className =
            'busy-column-main';


        const plot =
            document.createElement(
                'div'
            );


        plot.className =
            'busy-column-plot';


        plot.innerHTML = `
            <span class="busy-column-gridline is-top"></span>
            <span class="busy-column-gridline is-middle"></span>
            <span class="busy-column-gridline is-bottom"></span>
        `;


        const columns =
            document.createElement(
                'div'
            );


        columns.className =
            'busy-column-columns';


        rows.forEach((row) => {

            const expected =
                Math.max(
                    0,
                    Number(
                        row?.expected
                        ?? 0
                    ) || 0
                );


            const isPeak =
                peak > 0
                &&
                expected === peak;


            const item =
                document.createElement(
                    'div'
                );


            item.className =
                'busy-column-item'
                +
                (
                    isPeak
                        ? ' is-peak'
                        : ''
                )
                +
                (
                    expected === 0
                        ? ' is-zero'
                        : ''
                );


            item.tabIndex =
                0;


            const label =
                String(
                    row?.label
                    ?? 'Period'
                );


            const range =
                String(
                    row?.range
                    ?? ''
                );


            item.title =
                `${label}`
                +
                (
                    range
                        ? ` (${range})`
                        : ''
                )
                +
                `: ${expected} projected `
                +
                (
                    expected === 1
                        ? 'request'
                        : 'requests'
                );


            const value =
                document.createElement(
                    'strong'
                );


            value.className =
                'busy-column-value';


            value.textContent =
                String(
                    expected
                );


            const barArea =
                document.createElement(
                    'div'
                );


            barArea.className =
                'busy-column-bar-area';


            const bar =
                document.createElement(
                    'span'
                );


            bar.className =
                'busy-column-bar';


            const height =
                expected > 0
                    ? Math.max(
                        8,
                        Math.round(
                            (
                                expected
                                / maxValue
                            )
                            * 100
                        )
                    )
                    : 0;


            bar.style.height =
                `${height}%`;


            barArea.appendChild(
                bar
            );


            if (isPeak) {

                const peakBadge =
                    document.createElement(
                        'span'
                    );


                peakBadge.className =
                    'busy-column-peak';


                peakBadge.textContent =
                    'Peak';


                barArea.appendChild(
                    peakBadge
                );
            }


            item.appendChild(
                value
            );


            item.appendChild(
                barArea
            );


            columns.appendChild(
                item
            );
        });


        plot.appendChild(
            columns
        );


        /* ----------------------------------------------
           Week labels
           ---------------------------------------------- */

        const labels =
            document.createElement(
                'div'
            );


        labels.className =
            'busy-column-labels';


        rows.forEach((row) => {

            const label =
                document.createElement(
                    'div'
                );


            label.className =
                'busy-column-label';


            label.textContent =
                String(
                    row?.label
                    ?? 'Period'
                );


            labels.appendChild(
                label
            );
        });


        const axisTitle =
            document.createElement(
                'div'
            );


        axisTitle.className =
            'busy-column-axis-title';


        axisTitle.textContent =
            'Projected requests';


        main.appendChild(
            plot
        );


        main.appendChild(
            labels
        );


        main.appendChild(
            axisTitle
        );


        chart.appendChild(
            yAxis
        );


        chart.appendChild(
            main
        );


        /* ----------------------------------------------
           Insight
           ---------------------------------------------- */

        const insight =
            document.createElement(
                'div'
            );


        insight.className =
            'busy-column-insight';


        insight.innerHTML = `
            <span
                class="busy-column-insight-icon"
                aria-hidden="true"
            >
                ↑
            </span>

            <div>
                <strong></strong>
                <span></span>
            </div>
        `;


        const peakLabel =
            String(
                primaryPeak?.label
                ?? 'the peak period'
            );


        const peakRange =
            String(
                primaryPeak?.range
                ?? ''
            );


        insight
            .querySelector(
                'strong'
            )
            .textContent =
                `Higher borrowing activity is expected during ${peakLabel}.`;


        insight
            .querySelector(
                'span:last-child'
            )
            .textContent =
                peakRange
                    ? `${peakRange} · ${peak} projected ${peak === 1 ? 'request' : 'requests'}`
                    : `${peak} projected ${peak === 1 ? 'request' : 'requests'}`;


        /*
         * Replace only this card's body.
         * Header and drilldown arrow remain untouched.
         */
        body.replaceChildren(
            chart,
            insight
        );


        body.dataset.busyColumnChart =
            '1';
    };


    if (
        document.readyState
        ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            render,
            {
                once: true
            }
        );

    } else {

        render();
    }

})();
</script>

{{-- EXPECTED BUSY PERIOD COLUMN V1 END --}}

{{-- EXPECTED BUSY PERIOD LABEL FIX V2 START --}}

<style>
/* ==========================================================
   EXPECTED BUSY PERIOD
   VALUE LABEL POSITIONING
   ========================================================== */

/*
 * Keep the original 20px space above the plotting area so
 * non-zero labels still have room above their columns.
 */
.analytics-page .busy-column-item {
    position: relative;
    grid-template-rows:
        20px
        minmax(0, 1fr);
}

/*
 * The value is positioned relative to the actual bar height
 * instead of always staying in the top row.
 */
.analytics-page .busy-column-value {
    position: absolute;

    left: 50%;
    top: auto;
    bottom: 3px;

    z-index: 4;

    min-width: 24px;

    transform: translateX(-50%);

    display: flex;
    align-items: center;
    justify-content: center;

    pointer-events: none;
}

/*
 * Since the value is now absolutely positioned,
 * force the bar area to stay in the second grid row.
 */
.analytics-page .busy-column-bar-area {
    grid-row: 2;
}


/*
 * Zero values sit just above the baseline.
 */
.analytics-page
.busy-column-item.is-zero
.busy-column-value {
    bottom: 3px;
}


/*
 * Make zero labels easy to read without creating a fake bar.
 */
.analytics-page
.busy-column-item.is-zero
.busy-column-value {
    color: var(--heading);
    opacity: 1;
}


/*
 * Preserve the zero-bar behavior.
 */
.analytics-page
.busy-column-item.is-zero
.busy-column-bar {
    height: 0 !important;
    min-height: 0 !important;
    background: transparent !important;
}
</style>


<script>
(() => {

    const positionBusyPeriodValues = () => {

        const chart =
            document.querySelector(
                '.analytics-page .busy-column-chart'
            );

        if (!chart) {
            return;
        }


        chart
            .querySelectorAll(
                '.busy-column-item'
            )
            .forEach((item) => {

                const value =
                    item.querySelector(
                        '.busy-column-value'
                    );

                const bar =
                    item.querySelector(
                        '.busy-column-bar'
                    );

                const barArea =
                    item.querySelector(
                        '.busy-column-bar-area'
                    );


                if (
                    !value
                    ||
                    !bar
                    ||
                    !barArea
                ) {
                    return;
                }


                const amount =
                    Math.max(
                        0,
                        Number.parseInt(
                            String(
                                value.textContent
                                || '0'
                            ).trim(),
                            10
                        )
                        || 0
                    );


                /*
                 * Zero:
                 * keep the number at the chart baseline.
                 */
                if (amount === 0) {

                    value.style.bottom =
                        '3px';

                    return;
                }


                /*
                 * Non-zero:
                 * measure the actual rendered column and place
                 * the number a few pixels above its top edge.
                 */
                const barHeight =
                    bar.getBoundingClientRect()
                        .height;


                const itemHeight =
                    item.getBoundingClientRect()
                        .height;


                const valueHeight =
                    value.getBoundingClientRect()
                        .height;


                const desiredBottom =
                    barHeight + 5;


                /*
                 * Prevent the tallest value from leaving the
                 * available chart area.
                 */
                const maximumBottom =
                    Math.max(
                        4,
                        itemHeight
                        - valueHeight
                        - 2
                    );


                value.style.bottom =
                    `${
                        Math.min(
                            desiredBottom,
                            maximumBottom
                        )
                    }px`;
            });
    };


    const run = () => {

        /*
         * First frame:
         * let the original busy-period renderer create the bars.
         */
        requestAnimationFrame(() => {

            positionBusyPeriodValues();


            /*
             * Second frame:
             * handles final computed dimensions.
             */
            requestAnimationFrame(
                positionBusyPeriodValues
            );
        });


        /*
         * Extra check after browser layout settles.
         */
        setTimeout(
            positionBusyPeriodValues,
            100
        );
    };


    if (
        document.readyState
        ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            run,
            {
                once: true
            }
        );

    } else {

        run();
    }


    /*
     * Keep labels aligned if the Analytics card changes width.
     */
    window.addEventListener(
        'resize',
        positionBusyPeriodValues,
        {
            passive: true
        }
    );

})();
</script>

{{-- EXPECTED BUSY PERIOD LABEL FIX V2 END --}}

{{-- EXPECTED BUSY PERIOD POLISH V3 START --}}

<style>
/* ==========================================================
   EXPECTED BUSY PERIOD - FINAL POLISH
   ========================================================== */

/*
 * Raise the PEAK badge slightly so it does not sit directly
 * on the baseline.
 */
.analytics-page .busy-column-peak {
    bottom: 13px !important;

    min-width: 38px;

    padding: 3px 7px;

    box-shadow:
        0 1px 2px rgba(15, 118, 110, 0.12);
}


/*
 * Cleaner insight icon container.
 */
.analytics-page .busy-column-insight-icon {
    width: 24px;
    height: 24px;

    flex: 0 0 24px;

    color: #047857;
    background: #d9f4ea;

    border: 1px solid #bce5d8;
}


/*
 * SVG icon sizing.
 */
.analytics-page .busy-column-insight-icon svg {
    display: block;

    width: 13px;
    height: 13px;

    stroke: currentColor;

    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;

    fill: none;
}


/*
 * Slightly improve alignment of the insight copy.
 */
.analytics-page .busy-column-insight {
    align-items: center;
}

.analytics-page .busy-column-insight strong {
    line-height: 1.3;
}

.analytics-page .busy-column-insight span {
    line-height: 1.35;
}
</style>


<script>
(() => {

    const polishBusyPeriod = () => {

        const chart =
            document.querySelector(
                '.analytics-page .busy-column-chart'
            );

        if (!chart) {
            return;
        }


        /* ----------------------------------------------------
           1. Ensure PEAK badge stays raised
           ---------------------------------------------------- */

        chart
            .querySelectorAll(
                '.busy-column-peak'
            )
            .forEach((badge) => {

                badge.style.bottom =
                    '13px';
            });


        /* ----------------------------------------------------
           2. Replace the old text icon with a lightbulb
           ---------------------------------------------------- */

        const insightIcon =
            document.querySelector(
                '.analytics-page .busy-column-insight-icon'
            );


        if (insightIcon) {

            insightIcon.innerHTML = `
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        d="M9 18h6"
                    ></path>

                    <path
                        d="M10 22h4"
                    ></path>

                    <path
                        d="M8.5 14.5
                           C7.2 13.5 6.5 12 6.5 10.2
                           C6.5 7.1 8.9 4.5 12 4.5
                           C15.1 4.5 17.5 7.1 17.5 10.2
                           C17.5 12 16.8 13.5 15.5 14.5
                           C14.7 15.1 14.2 15.8 14 17
                           H10
                           C9.8 15.8 9.3 15.1 8.5 14.5Z"
                    ></path>
                </svg>
            `;


            insightIcon.setAttribute(
                'aria-label',
                'Planning insight'
            );
        }
    };


    const run = () => {

        polishBusyPeriod();

        requestAnimationFrame(
            polishBusyPeriod
        );

        setTimeout(
            polishBusyPeriod,
            100
        );
    };


    if (
        document.readyState
        ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            run,
            {
                once: true
            }
        );

    } else {

        run();
    }

})();
</script>

{{-- EXPECTED BUSY PERIOD POLISH V3 END --}}

{{-- EXPECTED BUSY PERIOD PEAK V4 START --}}

<style>
/* ==========================================================
   EXPECTED BUSY PERIOD
   FINAL PEAK BADGE POSITION
   ========================================================== */

/*
 * The badge now belongs to the actual green column,
 * not to the whole plotting area.
 */
.analytics-page .busy-column-bar {
    position: relative;
    overflow: visible;
}


/*
 * PEAK sits near the upper part of the peak bar.
 */
.analytics-page .busy-column-item.is-peak
.busy-column-peak {
    position: absolute !important;

    top: 12px !important;
    bottom: auto !important;

    left: 50% !important;

    transform:
        translateX(-50%) !important;

    z-index: 6;

    min-width: 40px;

    padding: 4px 7px;

    border-radius: 999px;

    color: #047857;

    background:
        rgba(
            255,
            255,
            255,
            0.92
        );

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.75
        );

    box-shadow:
        0 1px 3px
        rgba(
            15,
            118,
            110,
            0.14
        );

    font-size: 8px;
    font-weight: 900;
    line-height: 1;

    letter-spacing: 0.02em;

    white-space: nowrap;
}


/*
 * Keep the projected value clearly above the column.
 */
.analytics-page
.busy-column-item.is-peak
.busy-column-value {
    z-index: 8;
}


/*
 * Slight visual refinement for the peak column.
 */
.analytics-page
.busy-column-item.is-peak
.busy-column-bar {
    box-shadow:
        0 2px 5px
        rgba(
            15,
            138,
            106,
            0.12
        );
}


/*
 * Keep the normal Week 5 bar visually secondary.
 */
.analytics-page
.busy-column-item:not(.is-peak)
.busy-column-bar {
    box-shadow:
        0 2px 4px
        rgba(
            37,
            99,
            235,
            0.08
        );
}
</style>


<script>
(() => {

    const finalizePeakBadge = () => {

        const peakItem =
            document.querySelector(
                '.analytics-page .busy-column-item.is-peak'
            );

        if (!peakItem) {
            return;
        }


        const bar =
            peakItem.querySelector(
                '.busy-column-bar'
            );


        const badge =
            peakItem.querySelector(
                '.busy-column-peak'
            );


        if (!bar || !badge) {
            return;
        }


        /*
         * Previous renderer puts PEAK inside the bar area.
         *
         * Move it into the actual green bar so its position
         * follows the height of the Week 4 column.
         */
        if (badge.parentElement !== bar) {

            bar.appendChild(
                badge
            );
        }


        badge.style.top =
            '12px';

        badge.style.bottom =
            'auto';

        badge.style.left =
            '50%';

        badge.style.transform =
            'translateX(-50%)';


        bar.style.position =
            'relative';

        bar.style.overflow =
            'visible';


        peakItem.dataset.peakFinalized =
            '1';
    };


    const run = () => {

        /*
         * Wait for the existing vertical chart renderer
         * and value-position renderer to finish first.
         */
        requestAnimationFrame(() => {

            finalizePeakBadge();

            requestAnimationFrame(
                finalizePeakBadge
            );
        });


        setTimeout(
            finalizePeakBadge,
            100
        );


        setTimeout(
            finalizePeakBadge,
            300
        );
    };


    if (
        document.readyState
        ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            run,
            {
                once: true
            }
        );

    } else {

        run();
    }

})();
</script>

{{-- EXPECTED BUSY PERIOD PEAK V4 END --}}













{{-- PLANNING NOTES FINAL ALIGNMENT V7 START --}}

<style>
/* ==========================================================
   EXPECTED BUSY PERIOD + PLANNING NOTES
   FINAL TWO-CARD ALIGNMENT
   ========================================================== */

/*
 * The actual source places both cards directly inside
 * .analytics-forecast-pair.
 *
 * Force one equal-height grid row.
 */
.analytics-page
.analytics-forecast-pair {
    display: grid !important;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        ) !important;

    align-items: stretch !important;
}


/*
 * Both cards must stretch to the grid row.
 */
.analytics-page
.analytics-forecast-pair
> .analytics-card {
    align-self: stretch !important;
}


/* ==========================================================
   PLANNING NOTES CARD
   ========================================================== */

.analytics-page
.analytics-forecast-pair
> .analytics-card.analytics-planning-final {
    display: flex !important;

    flex-direction: column !important;

    align-self: stretch !important;

    height: 100% !important;
    min-height: 100% !important;
}


/* Header should stay the same height as the neighboring card. */
.analytics-page
.analytics-planning-final
.analytics-card-head {
    flex:
        0
        0
        auto !important;
}


/* ==========================================================
   PLANNING NOTES BODY
   ========================================================== */

.analytics-page
.analytics-planning-final
.analytics-planning-final-body {
    display: grid !important;

    flex:
        1
        1
        auto !important;

    min-height: 0 !important;

    margin: 0 !important;
    padding: 0 !important;
}


/* ==========================================================
   NOTE ROW
   ========================================================== */

.analytics-page
.analytics-planning-final
.analytics-planning-final-row {
    display: grid !important;

    grid-template-columns:
        34px
        minmax(0, 1fr);

    align-items: center !important;

    gap: 13px;

    min-height: 0 !important;

    padding:
        18px
        16px !important;

    border-bottom:
        1px solid
        var(--border);
}


.analytics-page
.analytics-planning-final
.analytics-planning-final-row:last-child {
    border-bottom: 0;
}


/* ==========================================================
   ICON
   ========================================================== */

.analytics-page
.analytics-planning-final-icon {
    display: grid;

    place-items: center;

    width: 30px;
    height: 30px;

    border-radius: 9px;

    color: #1677c8;

    background: #edf6fd;
}


.analytics-page
.analytics-planning-final-icon.is-success {
    color: #087f68;

    background: #e4f7f1;
}


.analytics-page
.analytics-planning-final-icon.is-neutral {
    color: #64748b;

    background: #f1f5f9;
}


.analytics-page
.analytics-planning-final-icon.is-warning {
    color: #b45309;

    background: #fff6e7;
}


.analytics-page
.analytics-planning-final-icon svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke: currentColor;
    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/* ==========================================================
   TEXT
   ========================================================== */

.analytics-page
.analytics-planning-final-copy {
    display: flex;

    flex-direction: column;

    justify-content: center;

    min-width: 0;
}


.analytics-page
.analytics-planning-final-title {
    display: block;

    margin: 0;

    color:
        var(--heading);

    font-size: 10.5px;
    font-weight: 800;
    line-height: 1.25;
}


.analytics-page
.analytics-planning-final-text {
    display: block;

    margin-top: 4px;

    color:
        var(--text-secondary);

    font-size: 9.5px;
    line-height: 1.45;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 900px) {

    .analytics-page
    .analytics-forecast-pair {
        grid-template-columns:
            1fr !important;
    }


    .analytics-page
    .analytics-forecast-pair
    > .analytics-card.analytics-planning-final {
        height: auto !important;
        min-height: 0 !important;
    }


    .analytics-page
    .analytics-planning-final
    .analytics-planning-final-row {
        min-height: 76px !important;
    }
}

</style>


<script>
(() => {

    const notes =
        @json(
            collect($notes ?? [])
                ->map(
                    static fn (array $note): array => [
                        'text' =>
                            trim(
                                (string) (
                                    $note['text']
                                    ?? ''
                                )
                            ),

                        'tone' =>
                            (string) (
                                $note['tone']
                                ?? 'neutral'
                            ),
                    ]
                )
                ->filter(
                    static fn (array $note): bool =>
                        $note['text'] !== ''
                )
                ->values()
        );


    const normalize = (value) =>
        String(value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();


    /* ======================================================
       CATEGORY
       ====================================================== */

    const classify = (text) => {

        const value =
            normalize(text);


        if (
            value.includes('availability')
            ||
            value.includes('equipment type')
            ||
            value.includes('coverage')
        ) {

            if (
                value.includes('shortage')
                ||
                value.includes('risk')
                ||
                value.includes('insufficient')
            ) {

                return {
                    title: 'Capacity Risk',
                    icon: 'warning',
                    tone: 'warning'
                };
            }


            return {
                title: 'Capacity Coverage',
                icon: 'check',
                tone: 'success'
            };
        }


        if (
            value.includes('next forecast period')
            &&
            (
                value.includes('recorded')
                ||
                value.includes('filed')
            )
        ) {

            return {
                title: 'Scheduled Demand',
                icon: 'calendar',
                tone: 'neutral'
            };
        }


        if (
            value.includes('largest borrowing')
            ||
            value.includes('division')
        ) {

            return {
                title: 'Demand Concentration',
                icon: 'users',
                tone: 'default'
            };
        }


        if (
            value.includes('historical')
            ||
            value.includes('history')
            ||
            value.includes('forecast unavailable')
            ||
            value.includes('not enough')
        ) {

            return {
                title: 'Forecast Readiness',
                icon: 'warning',
                tone: 'warning'
            };
        }


        return {
            title: 'Planning Signal',
            icon: 'info',
            tone: 'default'
        };
    };


    /* ======================================================
       SVG ICONS
       ====================================================== */

    const svgFor = (icon) => {

        if (icon === 'check') {

            return `
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="8"></circle>
                    <path d="m8.5 12 2.2 2.2 4.8-5"></path>
                </svg>
            `;
        }


        if (icon === 'calendar') {

            return `
                <svg viewBox="0 0 24 24">
                    <rect x="4" y="6" width="16" height="14" rx="2"></rect>
                    <path d="M8 3v4"></path>
                    <path d="M16 3v4"></path>
                    <path d="M4 10h16"></path>
                </svg>
            `;
        }


        if (icon === 'users') {

            return `
                <svg viewBox="0 0 24 24">
                    <circle cx="9" cy="8" r="3"></circle>
                    <path d="M4 19v-1c0-3 2-5 5-5s5 2 5 5v1"></path>
                    <path d="M16 11c2 .3 4 1.9 4 4.5V19"></path>
                    <path d="M15 5.3a3 3 0 0 1 0 5.4"></path>
                </svg>
            `;
        }


        if (icon === 'warning') {

            return `
                <svg viewBox="0 0 24 24">
                    <path d="M12 4 3.5 19h17L12 4Z"></path>
                    <path d="M12 9v4"></path>
                    <path d="M12 16h.01"></path>
                </svg>
            `;
        }


        return `
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="8"></circle>
                <path d="M12 11v5"></path>
                <path d="M12 8h.01"></path>
            </svg>
        `;
    };


    /* ======================================================
       FIND PLANNING NOTES
       ====================================================== */

    const heading =
        Array.from(
            document.querySelectorAll(
                '.analytics-page h2, .analytics-page h3'
            )
        )
        .find(
            (element) =>
                normalize(
                    element.textContent
                )
                ===
                'planning notes'
        );


    if (!heading) {
        return;
    }


    const planningCard =
        heading.closest(
            '.analytics-card'
        );


    if (!planningCard) {
        return;
    }


    planningCard.classList.add(
        'analytics-planning-final'
    );


    /* ======================================================
       HEADER
       ====================================================== */

    const header =
        planningCard.querySelector(
            '.analytics-card-head'
        );


    if (header) {

        const subtitle =
            header.querySelector('p');


        if (subtitle) {

            subtitle.textContent =
                'Key signals for the next forecast period.';
        }
    }


    /* ======================================================
       BODY
       ====================================================== */

    const body =
        planningCard.querySelector(
            '.analytics-card-body'
        );


    if (!body) {
        return;
    }


    /*
     * Build deterministic markup.
     * No leftover old row structure can affect spacing.
     */
    body.replaceChildren();

    body.className =
        'analytics-card-body analytics-planning-final-body';


    notes.forEach((note) => {

        const config =
            classify(
                note.text
            );


        const row =
            document.createElement(
                'div'
            );


        row.className =
            'analytics-planning-final-row';


        const icon =
            document.createElement(
                'span'
            );


        icon.className =
            'analytics-planning-final-icon'
            +
            (
                config.tone === 'success'
                    ? ' is-success'
                    : config.tone === 'neutral'
                        ? ' is-neutral'
                        : config.tone === 'warning'
                            ? ' is-warning'
                            : ''
            );


        icon.setAttribute(
            'aria-hidden',
            'true'
        );


        icon.innerHTML =
            svgFor(
                config.icon
            );


        const copy =
            document.createElement(
                'div'
            );


        copy.className =
            'analytics-planning-final-copy';


        const title =
            document.createElement(
                'strong'
            );


        title.className =
            'analytics-planning-final-title';


        title.textContent =
            config.title;


        const description =
            document.createElement(
                'span'
            );


        description.className =
            'analytics-planning-final-text';


        description.textContent =
            note.text;


        copy.append(
            title,
            description
        );


        row.append(
            icon,
            copy
        );


        body.appendChild(
            row
        );
    });


    /*
     * The rows divide the whole available body equally.
     */
    body.style.setProperty(
        'grid-template-rows',
        `repeat(${Math.max(notes.length, 1)}, minmax(0, 1fr))`,
        'important'
    );


    /*
     * Clear any old inline height values left by V5/V6.
     * Grid stretch now handles alignment natively.
     */
    planningCard.style.removeProperty(
        'height'
    );

    planningCard.style.removeProperty(
        'min-height'
    );

})();
</script>

{{-- PLANNING NOTES FINAL ALIGNMENT V7 END --}}

<style>
/* PLANNING NOTES TITLE SIZE POLISH */
.analytics-page
.analytics-planning-final-title {
    font-size: 11.5px !important;
    font-weight: 800 !important;
    line-height: 1.3 !important;
}
</style>


{{-- FORECAST DEMAND PAIR EQUAL HEIGHT START --}}

<style>
/*
|--------------------------------------------------------------------------
| Forecast demand pair
|--------------------------------------------------------------------------
| Forecasted Demand by Division and Scheduled Demand by Unit should share
| the same row height on desktop. The content-rich card determines the row
| height while the shorter/empty card stretches to match it.
*/

.analytics-forecast-demand-pair {
    align-items: stretch !important;
}

.analytics-forecast-demand-pair > .analytics-forecast-equal-card {
    align-self: stretch !important;
    height: 100% !important;
}


/*
 * Empty scheduled-demand body fills the remaining vertical space so the
 * empty-state icon/message stays visually centered.
 */
.analytics-forecast-equal-card .analytics-forecast-equal-empty {
    flex: 1 1 auto !important;
    min-height: 0 !important;

    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
}


/*
 * When the Analytics layout stacks, let each card return to its natural
 * content height.
 */
@media (max-width: 900px) {
    .analytics-forecast-demand-pair > .analytics-forecast-equal-card {
        height: auto !important;
        min-height: 0 !important;
    }

    .analytics-forecast-equal-card .analytics-forecast-equal-empty {
        flex: initial !important;
    }
}
</style>


<script>
(() => {
    const LEFT_TITLE =
        'Forecasted Demand by Division';

    const RIGHT_TITLE =
        'Scheduled Demand by Unit';

    const EMPTY_TEXT =
        'No unit demand is recorded for the next period yet.';


    const normalized = (value) =>
        String(value || '')
            .replace(/\s+/g, ' ')
            .trim();


    const findHeading = (title) =>
        Array.from(
            document.querySelectorAll(
                'h1, h2, h3, h4, h5, h6'
            )
        ).find(
            (heading) =>
                normalized(heading.textContent) === title
        );


    const findCard = (heading) => {
        if (!heading) {
            return null;
        }

        return heading.closest(
            'section, article, .analytics-card, .school-panel'
        );
    };


    const commonParent = (first, second) => {
        if (!first || !second) {
            return null;
        }

        let current = first.parentElement;

        while (current) {
            if (current.contains(second)) {
                return current;
            }

            current = current.parentElement;
        }

        return null;
    };


    const findEmptyRegion = (card) => {
        if (!card) {
            return null;
        }

        const candidates =
            Array.from(
                card.querySelectorAll('*')
            );

        const message =
            candidates.find(
                (element) =>
                    normalized(element.textContent)
                    === EMPTY_TEXT
            );

        if (!message) {
            return null;
        }


        /*
         * Prefer an existing semantic empty/body wrapper.
         */
        const semantic =
            message.closest(
                [
                    '.analytics-empty-state',
                    '.analytics-empty',
                    '.empty-state',
                    '.analytics-card-body',
                    '.school-empty',
                    '.school-panel-body'
                ].join(',')
            );

        if (semantic && semantic !== card) {
            return semantic;
        }


        /*
         * Otherwise use the highest child below the card that owns the
         * empty message. This keeps the header outside the centering area.
         */
        let region = message;

        while (
            region.parentElement
            &&
            region.parentElement !== card
        ) {
            region = region.parentElement;
        }

        return region !== card
            ? region
            : null;
    };


    let applying = false;


    const setup = () => {
        if (applying) {
            return;
        }

        applying = true;

        try {
            const leftHeading =
                findHeading(LEFT_TITLE);

            const rightHeading =
                findHeading(RIGHT_TITLE);

            const leftCard =
                findCard(leftHeading);

            const rightCard =
                findCard(rightHeading);

            if (!leftCard || !rightCard) {
                return;
            }


            leftCard.classList.add(
                'analytics-forecast-equal-card'
            );

            rightCard.classList.add(
                'analytics-forecast-equal-card'
            );


            const row =
                commonParent(
                    leftCard,
                    rightCard
                );

            if (row) {
                row.classList.add(
                    'analytics-forecast-demand-pair'
                );
            }


            const emptyRegion =
                findEmptyRegion(rightCard);

            if (emptyRegion) {
                emptyRegion.classList.add(
                    'analytics-forecast-equal-empty'
                );
            }


            /*
             * Most Analytics rows are CSS Grid and stretch correctly once
             * align-items is normalized above.
             *
             * This small fallback only activates when the existing parent
             * layout still leaves the two cards at different heights.
             */
            const syncHeight = () => {
                const desktop =
                    window.matchMedia(
                        '(min-width: 901px)'
                    ).matches;

                leftCard.style.removeProperty(
                    'min-height'
                );

                rightCard.style.removeProperty(
                    'min-height'
                );

                if (!desktop) {
                    return;
                }

                requestAnimationFrame(() => {
                    const leftHeight =
                        leftCard
                            .getBoundingClientRect()
                            .height;

                    const rightHeight =
                        rightCard
                            .getBoundingClientRect()
                            .height;

                    const difference =
                        Math.abs(
                            leftHeight - rightHeight
                        );

                    if (difference < 1) {
                        return;
                    }

                    const target =
                        Math.ceil(
                            Math.max(
                                leftHeight,
                                rightHeight
                            )
                        );

                    leftCard.style.setProperty(
                        'min-height',
                        `${target}px`,
                        'important'
                    );

                    rightCard.style.setProperty(
                        'min-height',
                        `${target}px`,
                        'important'
                    );
                });
            };


            syncHeight();


            /*
             * Re-check on responsive resize only.
             */
            if (
                !window.__forecastDemandPairResizeBound
            ) {
                window.__forecastDemandPairResizeBound =
                    true;

                let resizeFrame = null;

                window.addEventListener(
                    'resize',
                    () => {
                        if (resizeFrame) {
                            cancelAnimationFrame(
                                resizeFrame
                            );
                        }

                        resizeFrame =
                            requestAnimationFrame(
                                syncHeight
                            );
                    },
                    { passive: true }
                );
            }

        } finally {
            applying = false;
        }
    };


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            setup,
            { once: true }
        );
    } else {
        setup();
    }


    /*
     * Analytics may finish small presentation scripts after DOM ready.
     */
    requestAnimationFrame(setup);

    setTimeout(
        setup,
        150
    );

})();
</script>

{{-- FORECAST DEMAND PAIR EQUAL HEIGHT END --}}
