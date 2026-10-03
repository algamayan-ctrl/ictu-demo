@php
 /*
 * SPMU_RETURN_BAR_V5
 *
 * Grouped vertical bar chart for completed returns.
 * Uses isolated class names so old Analytics chart CSS cannot override it.
 */
 $points = $returnTrend['points'] ?? [];

 $maxValue = max(
 1,
 ...array_map(
 static fn (array $point): int => max(
 (int) ($point['on_time'] ?? 0),
 (int) ($point['late'] ?? 0)
 ),
 $points
 )
 );

 $axisMax = $maxValue <= 4
 ? $maxValue
 : (int) (ceil($maxValue / 5) * 5);

 $chartHeight = 180;

 $ticks = $axisMax <= 4
 ? range($axisMax, 0)
 : [
 $axisMax,
 (int) round($axisMax * .75),
 (int) round($axisMax * .50),
 (int) round($axisMax * .25),
 0,
 ];

 $ticks = array_values(array_unique($ticks));
@endphp

<style>
/* SPMU_RETURN_BAR_V5 */

.spmu-return-v5 {
 width: 100%;
 display: grid;
 gap: 14px;
}

.spmu-return-v5-legend {
 display: flex;
 align-items: center;
 gap: 18px;
 min-height: 20px;
 color: var(--text-secondary);
 font-size: 11px;
 font-weight: 700;
}

.spmu-return-v5-legend-item {
 display: inline-flex;
 align-items: center;
 gap: 6px;
}

.spmu-return-v5-dot {
 display: inline-block;
 width: 11px;
 height: 8px;
 border-radius: 3px;
}

.spmu-return-v5-dot.on-time {
 background: #047857;
}

.spmu-return-v5-dot.late {
 background: #c2410c;
}

.spmu-return-v5-chart {
 display: grid;
 grid-template-columns: 32px minmax(0, 1fr);
 gap: 10px;
 align-items: start;
}

.spmu-return-v5-axis {
 position: relative;
 height: 180px;
 color: var(--text-muted);
 font-size: 9px;
 font-weight: 600;
 font-variant-numeric: tabular-nums;
}

.spmu-return-v5-axis-label {
 position: absolute;
 right: 0;
 line-height: 1;
 transform: translateY(50%);
}

.spmu-return-v5-content {
 min-width: 0;
}

.spmu-return-v5-plot {
 position: relative;
 height: 180px;
 border-bottom: 1px solid var(--border);
}

.spmu-return-v5-gridline {
 position: absolute;
 left: 0;
 right: 0;
 border-top: 1px solid var(--border);
 pointer-events: none;
}

.spmu-return-v5-groups {
 position: absolute;
 inset: 0;
 display: grid;
 grid-template-columns: repeat({{ max(1, count($points)) }}, minmax(80px, 1fr));
 gap: 12px;
}

.spmu-return-v5-group {
 min-width: 0;
 height: 180px;
 display: flex;
 justify-content: center;
 align-items: flex-end;
 gap: 8px;
 padding: 0 8px;
}

.spmu-return-v5-bar {
 position: relative;
 display: block !important;
 width: 26px !important;
 min-width: 26px !important;
 max-width: 26px !important;
 min-height: 0;
 flex: 0 0 26px;
 border-radius: 7px 7px 0 0;
}

.spmu-return-v5-bar.on-time {
 background: #047857 !important;
}

.spmu-return-v5-bar.late {
 background: #c2410c !important;
}

.spmu-return-v5-value {
 position: absolute;
 left: 50%;
 bottom: calc(100% + 6px);
 transform: translateX(-50%);
 color: var(--heading);
 font-size: 10px;
 font-weight: 800;
 line-height: 1;
}

.spmu-return-v5-labels {
 display: grid;
 grid-template-columns: repeat({{ max(1, count($points)) }}, minmax(80px, 1fr));
 gap: 12px;
 padding-top: 9px;
}

.spmu-return-v5-label {
 min-width: 0;
 color: var(--text-secondary);
 font-size: 9.5px;
 font-weight: 600;
 line-height: 1.3;
 text-align: center;
}

.spmu-return-v5-empty {
 position: absolute;
 inset: 0;
 display: grid;
 place-items: center;
 color: var(--text-muted);
 font-size: 11px;
}

@media (hover: hover) and (pointer: fine) {
 .spmu-return-v5-bar:hover {
 opacity: .85;
 }
}

@media (max-width: 720px) {
 .spmu-return-v5-scroll {
 overflow-x: auto;
 padding-bottom: 4px;
 }

 .spmu-return-v5-content {
 min-width: 560px;
 }
}

html[data-theme="dark"] .spmu-return-v5-bar.on-time,
html[data-theme="dark"] .spmu-return-v5-dot.on-time {
 background: #34d399 !important;
}

html[data-theme="dark"] .spmu-return-v5-bar.late,
html[data-theme="dark"] .spmu-return-v5-dot.late {
 background: #f59e0b !important;
}
</style>

<div
 class="spmu-return-v5"
 role="img"
 aria-label="Return performance by {{ $returnTrend['granularity'] }}"
>
 <div class="spmu-return-v5-legend">
 <span class="spmu-return-v5-legend-item">
 <span class="spmu-return-v5-dot on-time"></span>
 On time
 </span>

 <span class="spmu-return-v5-legend-item">
 <span class="spmu-return-v5-dot late"></span>
 Late
 </span>
 </div>

 <div class="spmu-return-v5-scroll">
 <div class="spmu-return-v5-chart">

 <div class="spmu-return-v5-axis" aria-hidden="true">
 @foreach($ticks as $tick)
 @php
 $position = $axisMax > 0
 ? ($tick / $axisMax) * $chartHeight
 : 0;
 @endphp

 <span
 class="spmu-return-v5-axis-label"
 style="bottom: {{ $position }}px"
 >
 {{ $tick }}
 </span>
 @endforeach
 </div>

 <div class="spmu-return-v5-content">

 <div class="spmu-return-v5-plot">

 @foreach($ticks as $tick)
 @php
 $position = $axisMax > 0
 ? ($tick / $axisMax) * $chartHeight
 : 0;
 @endphp

 <span
 class="spmu-return-v5-gridline"
 style="bottom: {{ $position }}px"
 aria-hidden="true"
 ></span>
 @endforeach

 @if(count($points) === 0)
 <div class="spmu-return-v5-empty">
 No completed return data for this period.
 </div>
 @else
 <div class="spmu-return-v5-groups">
 @foreach($points as $point)
 @php
 $onTime = (int) ($point['on_time'] ?? 0);
 $late = (int) ($point['late'] ?? 0);

 $onTimePixels = $onTime > 0
 ? max(
 14,
 (int) round(
 ($onTime / max(1, $axisMax))
 * $chartHeight
 )
 )
 : 0;

 $latePixels = $late > 0
 ? max(
 14,
 (int) round(
 ($late / max(1, $axisMax))
 * $chartHeight
 )
 )
 : 0;
 @endphp

 <div
 class="spmu-return-v5-group"
 tabindex="0"
 data-chart-tip
 data-tip-title="{{ $point['label'] }}"
 data-tip-rows="{{ json_encode([
 ['Returned on time', (string) $onTime],
 ['Returned late', (string) $late],
 ['Completed returns', (string) ($onTime + $late)],
 ]) }}"
 >
 @if($onTime > 0)
 <span
 class="spmu-return-v5-bar on-time"
 style="height: {{ $onTimePixels }}px !important;"
 >
 <span class="spmu-return-v5-value">
 {{ $onTime }}
 </span>
 </span>
 @endif

 @if($late > 0)
 <span
 class="spmu-return-v5-bar late"
 style="height: {{ $latePixels }}px !important;"
 >
 <span class="spmu-return-v5-value">
 {{ $late }}
 </span>
 </span>
 @endif
 </div>
 @endforeach
 </div>
 @endif
 </div>

 <div class="spmu-return-v5-labels">
 @foreach($points as $point)
 <div class="spmu-return-v5-label">
 {{ $point['label'] }}
 </div>
 @endforeach
 </div>

 </div>
 </div>
 </div>
</div>
