@php
    $definitions = \App\Reports\ReportCatalogue::filtersFor($selectedReport);
    $secondary = array_intersect_key($definitions, array_flip(['division', 'unit', 'borrower']));
    $primary = array_diff_key($definitions, $secondary);
    if ($selectedReport === 'inventory') unset($primary['equipment']);
    $secondaryCount = collect(array_keys($secondary))->filter(fn ($key) => $reportFilters->has($key))->count();
    $isSummary = $selectedReport === 'summary';
    $summaryCustom = $isSummary && $periodSelection === 'custom';
    $searchPlaceholder = match ($selectedReport) {
        'summary', 'utilization' => null,
        'all-transactions' => 'Control no., borrower or equipment…',
        'inventory' => 'Search item…',
        'accountability-cases' => 'Case no., custody no. or borrower…',
        'billing-settlement' => 'Billing no., receipt no. or borrower…',
        default => 'Control no., borrower or equipment',
    };
    $periodOrder = in_array($selectedReport, ['inventory', 'utilization'], true)
        ? ['month', 'last_month', 'semester', 'previous_semester', 'custom'] : array_keys($reportingPeriods);
    $clearState = array_diff_key($reportFilters->toQuery(), array_flip([...array_keys($definitions), 'search']));
    $clearUrl = route('reports.index', array_merge($clearState, ['generated' => 1]));
    $allUnits = \App\Support\OrganizationalStructure::unitsByDivision();
@endphp
{{-- This is the compact Reports toolbar and the secondary filter popup. --}}
<form method="get" action="{{ route('reports.index') }}" id="reports-compact-form" class="report-compact-toolbar card" data-report-preview-form data-report-compact-builder data-summary="{{ $isSummary ? '1' : '0' }}">
    <input type="hidden" name="generated" value="1">
    <input type="hidden" name="per_page" value="{{ in_array((int) request('per_page'), \App\Reports\ReportRecordTable::PER_PAGE_OPTIONS, true) ? (int) request('per_page') : \App\Reports\ReportRecordTable::DEFAULT_PER_PAGE }}" data-report-page-size>
    <div class="report-compact-field report-type-field">
        <label for="compact-report-type">Report Type</label>
        <select name="report" id="compact-report-type" data-report-type>
            @foreach($reportGroups as $group => $types)<optgroup label="{{ $group }}">
                @foreach($types as $key => $label)<option value="{{ $key }}" @selected($selectedReport === $key)>{{ $label }}</option>@endforeach
            </optgroup>@endforeach
        </select>
    </div>
    @if($isSummary)
        <div class="report-compact-field report-period-field"><label for="compact-report-period">Reporting Period</label><select name="academic_period" id="compact-report-period" data-report-period>
            <option value="month" @selected(!$summaryCustom)>Monthly</option>
            <option value="custom" @selected($summaryCustom)>Custom Range</option>
        </select></div>
        <div class="report-compact-field" data-report-summary-month @if($summaryCustom) hidden @endif><label for="compact-summary-month">Month</label><select id="compact-summary-month" name="summary_month" @disabled($summaryCustom)>
            @for($number = 1; $number <= 12; $number++)<option value="{{ $number }}" @selected($from->month === $number)>{{ \Carbon\Carbon::create(2026, $number, 1)->format('F') }}</option>@endfor
        </select></div>
        <div class="report-compact-field report-year-field" data-report-summary-month @if($summaryCustom) hidden @endif><label for="compact-summary-year">Year</label><input id="compact-summary-year" name="summary_year" type="number" min="1900" max="9999" value="{{ $from->year }}" @disabled($summaryCustom) required></div>
    @else
        <div class="report-compact-field report-period-field"><label for="compact-report-period">Reporting Period</label><select name="academic_period" id="compact-report-period" data-report-period>
            @foreach($periodOrder as $key)<option value="{{ $key }}" @selected($periodSelection === $key)>{{ $key === 'last_month' ? 'Previous Month' : ($reportingPeriods[$key]['label'] ?? $key) }}</option>@endforeach
            @if(!in_array($periodSelection, $periodOrder, true) && isset($reportingPeriods[$periodSelection]))<option value="{{ $periodSelection }}" selected>{{ $reportingPeriods[$periodSelection]['label'] }} (historical filter)</option>@endif
        </select></div>
    @endif
    <div class="report-custom-range" data-report-custom-range @if($periodSelection !== 'custom') hidden @endif>
        <div class="report-compact-field"><label for="compact-from">From</label><input id="compact-from" name="start_date" type="date" value="{{ $from->toDateString() }}" @disabled($periodSelection !== 'custom') required></div>
        <div class="report-compact-field"><label for="compact-to">To</label><input id="compact-to" name="end_date" type="date" value="{{ $to->toDateString() }}" min="{{ $from->toDateString() }}" @disabled($periodSelection !== 'custom') required></div>
    </div>
    @foreach($primary as $filterKey => $definition)@include('reports.partials.compact-filter-field')@endforeach
    @if($selectedReport === 'inventory' && $reportFilters->has('equipment'))<input type="hidden" name="equipment" value="{{ $reportFilters->get('equipment') }}">@endif
    @if($searchPlaceholder !== null)
        <div class="report-compact-field report-record-search"><label for="compact-record-search">Search</label><input id="compact-record-search" name="search" type="search" value="{{ $reportFilters->get('search') }}" maxlength="100" placeholder="{{ $searchPlaceholder }}"></div>
    @endif
    @if($secondary)
        <button class="button secondary report-more-trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="report-more-filters" data-report-more-trigger><x-icon name="filter" size="16" /><span>More filters<span data-report-more-count>{{ $secondaryCount ? ' ('.$secondaryCount.')' : '' }}</span></span></button>
        <div id="report-more-filters" class="report-more-panel" role="dialog" aria-modal="false" aria-labelledby="report-more-title" data-report-more-panel data-form-id="reports-compact-form" hidden>
            <header><h2 id="report-more-title">More filters</h2><button type="button" class="icon-button" aria-label="Close More filters" data-report-more-cancel><x-icon name="close" size="18" /></button></header>
            <div class="report-more-fields">
                @foreach($secondary as $filterKey => $definition)@include('reports.partials.compact-filter-field')@endforeach
            </div>
            <footer><button class="button secondary small" type="button" data-report-more-clear>Clear fields</button><div><button class="button secondary small" type="button" data-report-more-cancel>Cancel</button><button class="button primary small" type="button" data-report-more-apply>Apply filters</button></div></footer>
        </div>
    @endif
    <div class="report-compact-actions"><button class="button primary" type="submit">Apply</button><a class="button secondary" href="{{ $clearUrl }}">Clear</a></div>
    <script type="application/json" data-report-unit-options>{!! json_encode($allUnits, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</form>
