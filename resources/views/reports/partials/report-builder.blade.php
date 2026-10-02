@php
    /*
    | The report builder.
    |
    | Visible by default: Report Type, Reporting Period, the resolved date range,
    | the report-specific filters, and the preview action. Filters stay visible
    | so the operator can understand the report scope without opening a hidden
    | accordion. The catalogue remains the authority on which filters apply.
    */
    use App\Reports\ReportCatalogue;
    use App\Reports\ReportFilters;

    $reportFilterDefinitions = ReportCatalogue::filtersFor($selectedReport);
    $appliedFilterValues = $reportFilters?->all() ?? [];
    $selectedDivision = $appliedFilterValues['division'] ?? null;
    $selectedBorrower = $appliedFilterValues['borrower'] ?? '';
    $selectedBorrowerLabel = 'All borrowers';

    if ($selectedBorrower !== '' && isset($reportFilterDefinitions['borrower'])) {
        $borrowerOptionsForLabel = ReportFilters::optionsFor(
            $reportFilterDefinitions['borrower'],
            $selectedDivision
        );
        $rawBorrowerLabel = $borrowerOptionsForLabel[$selectedBorrower] ?? 'Selected borrower';
        $selectedBorrowerLabel = trim((string) preg_replace('/\s*Â·\s*.+$/u', '', $rawBorrowerLabel));
    }

    $hasAppliedFilters = count($appliedFilterValues) > 0;
    $clearFiltersUrl = route('reports.index', array_filter([
        'report' => $selectedReport,
        'academic_period' => $periodSelection,
        ...($periodSelection === 'custom' ? ['start_date' => $from->toDateString(), 'end_date' => $to->toDateString()] : []),
        'generated' => ($showPreview ?? false) ? 1 : null,
    ], static fn ($value) => $value !== null));

    /*
     * Three across is the layout the builder is designed for; a report that
     * declares fewer filters narrows the row rather than leaving a dead
     * column, and one that declares more wraps onto a second line.
     */
    $filterColumns = max(1, min(count($reportFilterDefinitions), 3));
    /*
     * REPORTING PERIOD DISPLAY CONFIG START
     */
    $reportPeriodOrder = [
                'week',
'month',
        'last_month',
        'semester',
        'previous_semester',
        'academic_year',
        'year',
        'custom',
    ];

    $reportPeriodLabels = [
                'week' => 'This Week',
'month' => 'This Month',
        'last_month' => 'Last Month',
        'semester' => 'Current Semester',
        'previous_semester' => 'Previous Semester',
        'academic_year' => 'Current Academic Year',
        'year' => 'This Calendar Year',
        'custom' => 'Custom Range',
    ];

    $displayReportingPeriods = [];

    foreach ($reportPeriodOrder as $periodKey) {
        if (isset($reportingPeriods[$periodKey])) {
            $displayReportingPeriods[$periodKey] = array_merge(
                $reportingPeriods[$periodKey],
                [
                    'label' => $reportPeriodLabels[$periodKey],
                    'disabled' => false,
                    'note' => '',
                ]
            );
        }
    }

    /*
     * Only use Previous Semester if the backend actually provides it.
     * Otherwise keep it visible but disabled instead of inventing dates.
     */
    if (! isset($displayReportingPeriods['previous_semester'])) {
        $displayReportingPeriods['previous_semester'] = [
            'label' => 'Previous Semester',
            'start_date' => '',
            'end_date' => '',
            'disabled' => true,
            'note' => '',
        ];
    }

    /*
     * Do not silently convert academic periods into the current month.
     */
    if (! $activeAcademicPeriod) {

        if (isset($displayReportingPeriods['semester'])) {
            $displayReportingPeriods['semester']['label'] =
                'Current Semester';

            $displayReportingPeriods['semester']['disabled'] = true;
            $displayReportingPeriods['semester']['note'] = '';
        }

        if (isset($displayReportingPeriods['academic_year'])) {
            $displayReportingPeriods['academic_year']['label'] =
                'Current Academic Year';

            $displayReportingPeriods['academic_year']['disabled'] = true;
            $displayReportingPeriods['academic_year']['note'] = '';
        }

        if (isset($displayReportingPeriods['previous_semester'])) {
            $displayReportingPeriods['previous_semester']['disabled'] = true;
        }

    } else {

        if (isset($displayReportingPeriods['semester'])) {
            $displayReportingPeriods['semester']['note'] =
                trim(
                    ($activeAcademicPeriod->academic_year ?? '') .
                    ' · ' .
                    ($activeAcademicPeriod->term_name ?? '')
                );
        }

        if (isset($displayReportingPeriods['academic_year'])) {
            $displayReportingPeriods['academic_year']['note'] =
                $activeAcademicPeriod->academic_year ?? '';
        }
    }

    /*
     * Rebuild the exact intended dropdown order.
     */
    $orderedDisplayReportingPeriods = [];

    foreach ($reportPeriodOrder as $periodKey) {
        if (isset($displayReportingPeriods[$periodKey])) {
            $orderedDisplayReportingPeriods[$periodKey] =
                $displayReportingPeriods[$periodKey];
        }
    }

    $displayReportingPeriods = $orderedDisplayReportingPeriods;

    /*
     * If an old URL selected an unavailable academic period,
     * visually fall back to This Month.
     */
    $displayPeriodSelection = $periodSelection;

    if (
        isset($displayReportingPeriods[$displayPeriodSelection]) &&
        ($displayReportingPeriods[$displayPeriodSelection]['disabled'] ?? false)
    ) {
        $displayPeriodSelection = 'month';
    }

    /* REPORTING PERIOD DISPLAY CONFIG END */
    /*
     * ACADEMIC PERIOD SELECTABLE OVERRIDE START
     *
     * Academic periods remain selectable even when they are not yet
     * configured. Availability is communicated through the report state
     * instead of disabling the dropdown option.
     */

    $academicPeriodAvailable = [
        'semester' => (bool) $activeAcademicPeriod,

        'previous_semester' =>
            isset($reportingPeriods['previous_semester'])
            && ! empty($reportingPeriods['previous_semester']['start_date'] ?? null)
            && ! empty($reportingPeriods['previous_semester']['end_date'] ?? null),

        'academic_year' => (bool) $activeAcademicPeriod,
    ];

    $cleanAcademicPeriodLabels = [
        'semester' => 'Current Semester',
        'previous_semester' => 'Previous Semester',
        'academic_year' => 'Current Academic Year',
    ];

    foreach ($cleanAcademicPeriodLabels as $periodKey => $periodLabel) {

        if (! isset($displayReportingPeriods[$periodKey])) {
            $displayReportingPeriods[$periodKey] = [
                'label' => $periodLabel,
                'start_date' => '',
                'end_date' => '',
                'disabled' => false,
                'note' => '',
            ];
        }

        $displayReportingPeriods[$periodKey]['label'] =
            $periodLabel;

        $displayReportingPeriods[$periodKey]['disabled'] =
            false;

        $displayReportingPeriods[$periodKey]['available'] =
            $academicPeriodAvailable[$periodKey] ?? false;

        if (! ($academicPeriodAvailable[$periodKey] ?? false)) {
            $displayReportingPeriods[$periodKey]['note'] =
                match ($periodKey) {
                    'semester' =>
                        'No active semester is configured.',

                    'previous_semester' =>
                        'No previous semester is configured.',

                    'academic_year' =>
                        'No active academic year is configured.',

                    default => '',
                };
        }
    }

    /*
     * Keep the selected academic option selected.
     * Do not visually fall back to This Month.
     */
    $displayPeriodSelection =
        $periodSelection;

    /* ACADEMIC PERIOD SELECTABLE OVERRIDE END */
    /*
     * PRESERVE REQUESTED REPORTING PERIOD START
     *
     * The reporting service may resolve an unavailable academic period
     * to a fallback date range. That resolved range must not change the
     * option selected by the user in the Reporting Period dropdown.
     */
    $requestedReportingPeriod =
        (string) request()->query(
            'academic_period',
            $periodSelection
        );

    if (
        $requestedReportingPeriod !== ''
        && isset($displayReportingPeriods[$requestedReportingPeriod])
    ) {
        $displayPeriodSelection =
            $requestedReportingPeriod;
    }

    /* PRESERVE REQUESTED REPORTING PERIOD END */

    $periodNotes = [
        'semester' => $activeAcademicPeriod
            ? $activeAcademicPeriod->academic_year.' Â· '.$activeAcademicPeriod->term_name
            : 'No active academic period is configured; the current month is used.',
        'academic_year' => $activeAcademicPeriod?->academic_year
            ?? 'No active academic period is configured; the current month is used.',
    ];
@endphp

<section class="content-area">
    <form method="get" class="card report-builder-card" aria-labelledby="report-builder-heading" id="report-builder" data-report-preview-form>
        <p class="eyebrow" id="report-builder-heading">Report builder</p>

        <div class="report-builder-primary">
            <label class="report-builder-field" for="report-type">
                <span>Report Type</span>
                <select id="report-type" name="report" data-report-type>
                    @foreach($reportGroups as $group => $reports)
                        <optgroup label="{{ $group }}">
                            @foreach($reports as $key => $label)
                                <option value="{{ $key }}" @selected($selectedReport === $key)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            <label class="report-builder-field" for="reports-academic-period">
                <span>Reporting Period</span>
                <select id="reports-academic-period" name="academic_period">
                    @foreach($displayReportingPeriods as $value => $reportingPeriod)
                        <option
                            value="{{ $value }}"
                            data-start-date="{{ $reportingPeriod['start_date'] ?? '' }}"
                            data-end-date="{{ $reportingPeriod['end_date'] ?? '' }}"
                            data-period-note="{{ $reportingPeriod['note'] ?? '' }}"
                            data-period-available="{{
                                $value === 'semester'
                                    ? ($activeAcademicPeriod ? '1' : '0')
                                    : (
                                        $value === 'academic_year'
                                            ? ($activeAcademicPeriod ? '1' : '0')
                                            : (
                                                $value === 'previous_semester'
                                                    ? (
                                                        ! empty($reportingPeriod['start_date'] ?? null)
                                                        && ! empty($reportingPeriod['end_date'] ?? null)
                                                            ? '1'
                                                            : '0'
                                                    )
                                                    : '1'
                                            )
                                    )
                            }}"
                            
                            @selected($displayPeriodSelection === $value)
                        >
                            {{ $reportingPeriod['label'] }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="report-builder-field report-resolved-period">
                <span id="report-date-range-label" class="report-date-range-heading">Date Range</span>

                <input type="hidden"
                    name="start_date"
                    value="{{ $from->toDateString() }}"
                    data-applied-start-date>

                <input type="hidden"
                    name="end_date"
                    value="{{ $to->toDateString() }}"
                    data-applied-end-date>

                <div class="report-date-fields"
                    role="group"
                    aria-labelledby="report-date-range-label">

                    <div class="report-date-field">
                        <span class="report-date-field-label">From</span>

                        <button type="button"
                            class="report-date-input"
                            data-report-date-trigger="start"
                            tabindex="{{ $periodSelection === 'custom' ? '0' : '-1' }}"
                            aria-disabled="{{ $periodSelection === 'custom' ? 'false' : 'true' }}"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="report-date-picker">

                            <x-icon name="calendar" size="15" />

                            <strong data-applied-start-label>{{ $from->format('d M Y') }}</strong>
                        </button>
                    </div>

                    <div class="report-date-field">
                        <span class="report-date-field-label">To</span>

                        <button type="button"
                            class="report-date-input"
                            data-report-date-trigger="end"
                            tabindex="{{ $periodSelection === 'custom' ? '0' : '-1' }}"
                            aria-disabled="{{ $periodSelection === 'custom' ? 'false' : 'true' }}"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="report-date-picker">

                            <x-icon name="calendar" size="15" />

                            <strong data-applied-end-label>{{ $to->format('d M Y') }}</strong>
                        </button>
                    </div>
                </div>

                <small
                    data-report-period-note
                    @if(empty($displayReportingPeriods[$displayPeriodSelection]['note'] ?? ''))
                        hidden
                    @endif
                >{{ $displayReportingPeriods[$displayPeriodSelection]['note'] ?? '' }}</small>

                <small data-report-date-error role="alert" hidden></small>

                <div id="report-date-picker"
                    class="report-date-picker"
                    role="dialog"
                    aria-labelledby="report-date-picker-title"
                    hidden>

                    <div class="report-date-picker-heading">
                        <strong id="report-date-picker-title">Select Date Range</strong>
                    </div>

                    <div class="report-calendar-header">

                        <button type="button"
                            class="icon-button report-calendar-nav"
                            data-date-month-step="-1"
                            aria-label="Previous month">&lsaquo;</button>

                        <strong data-calendar-current-month
                            class="report-calendar-current-month"></strong>

                        <button type="button"
                            class="icon-button report-calendar-nav"
                            data-date-month-step="1"
                            aria-label="Next month">&rsaquo;</button>

                    </div>

                    <div class="report-date-months"
                        data-date-months></div>

                </div>
            </div>
{{--
                The empty span keeps the button on the same baseline as the
                controls beside it, which all sit one label-line down.
            --}}
            <div class="report-builder-field report-builder-submit">
                <span aria-hidden="true"></span>
                <button
                    class="button primary report-generate-button"
                    type="submit"
                    name="generated"
                    value="1"
                >
                    <x-icon name="report-document" size="17" />
                    <span data-preview-button-label>Preview Report</span>
                </button>
            </div>
        </div>

        @if(count($reportFilterDefinitions) > 0)
            <section class="report-filters-panel" aria-labelledby="report-filters-heading">
                <div class="report-filters-heading">
                    <div>
                        <p class="report-filters-title" id="report-filters-heading">
                            <x-icon name="settings" size="15" />
                            <span>Filters</span>
                            <span class="report-filter-optional">Optional</span>
                        </p>
                        <p class="report-filters-help">Narrow the report when needed. Leave a filter blank to include all matching records.</p>
                    </div>

                    @if($hasAppliedFilters)
                        <div class="report-filter-actions">
                            <span class="report-filter-count">{{ count($appliedFilterValues) }} applied</span>
                            <a class="report-clear-filters" href="{{ $clearFiltersUrl }}">Clear filters</a>
                        </div>
                    @endif
                </div>

                <div class="report-filter-grid" style="--report-filter-columns: {{ $filterColumns }};">
                    @foreach($reportFilterDefinitions as $filterKey => $definition)
                        @php
                            $current = $appliedFilterValues[$filterKey] ?? '';
                        @endphp

                        @if($filterKey === 'borrower')
                            <div class="report-builder-field report-borrower-field" data-borrower-picker>
                                <span>{{ $definition['label'] }}</span>
                                <input type="hidden" name="borrower" value="{{ $current }}" data-borrower-value>

                                <button
                                    type="button"
                                    class="report-borrower-trigger"
                                    data-borrower-trigger
                                    aria-haspopup="listbox"
                                    aria-expanded="false"
                                >
                                    <span data-borrower-current>{{ $selectedBorrowerLabel }}</span>
                                    <svg
                                        class="report-borrower-chevron"
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                        focusable="false"
                                    >
                                        <path d="m7 10 5 5 5-5" />
                                    </svg>
                                </button>

                                <div class="report-borrower-menu" data-borrower-menu hidden>
                                    <label class="report-borrower-search-wrap">
                                        <span class="sr-only">Search borrower</span>
                                        <input
                                            type="search"
                                            class="report-borrower-search"
                                            placeholder="Search borrower by name or email..."
                                            autocomplete="off"
                                            data-borrower-search
                                        >
                                    </label>

                                    <div class="report-borrower-options" role="listbox" data-borrower-options>
                                        <button
                                            type="button"
                                            class="report-borrower-option is-selected"
                                            data-borrower-option
                                            data-value=""
                                            data-name="All borrowers"
                                            role="option"
                                            aria-selected="{{ $current === '' ? 'true' : 'false' }}"
                                        >
                                            <strong>All borrowers</strong>
                                        </button>
                                    </div>

                                    <p class="report-borrower-message" data-borrower-message hidden></p>
                                </div>
                            </div>
                        @else
                            @php
                                /*
                                 * Render every Office / Unit option so the browser can
                                 * switch between divisions without a page reload. Server
                                 * validation still enforces the selected Division.
                                 */
                                $options = $filterKey === 'unit'
                                    ? ReportFilters::optionsFor($definition, null)
                                    : ReportFilters::optionsFor($definition, $selectedDivision);
                            @endphp

                            <label class="report-builder-field" for="report-filter-{{ $filterKey }}">
                                <span>{{ $definition['label'] }}</span>
                                <select
                                    id="report-filter-{{ $filterKey }}"
                                    name="{{ $filterKey }}"
                                    @if($filterKey === 'division') data-filter-division @endif
                                    @if(($definition['depends_on'] ?? null) === 'division') data-filter-depends-on-division @endif
                                >
                                    <option value="">{{ $definition['placeholder'] ?? 'All' }}</option>
                                    @foreach($options as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected((string) $current === (string) $value)
                                            @if(($definition['depends_on'] ?? null) === 'division')
                                                data-division="{{ App\Support\OrganizationalStructure::divisionAndUnitFor($label)[0] }}"
                                            @endif
                                        >{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    @endforeach
                </div>
            </section>
        @else
            <p class="report-no-filters">No additional filters are needed for this report.</p>
        @endif

        <p class="report-description">{{ $selectedReportMeta['description'] }}</p>
    </form>
</section>


<style>
.report-resolved-period {
    position: relative;
}

.report-resolved-period [hidden] {
    display: none !important;
}

.report-date-range-heading {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.report-date-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    width: 100%;
}

.report-date-field {
    min-width: 0;
}

.report-date-field-label {
    display: block;
    margin-bottom: 7px;
    color: var(--heading);
    font-size: 11px;
    font-weight: 500;
}

.report-date-input {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    height: 52px;
    min-height: 52px;
    margin: 0;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-elevated);
    color: var(--heading);
    font: inherit;
    text-align: left;
    box-shadow: none;
    transform: none !important;
}

.report-date-input strong {
    overflow: hidden;
    min-width: 0;
    font-size: 12px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.report-date-input[aria-disabled="false"] {
    cursor: pointer;
}

.report-date-input[aria-disabled="true"] {
    cursor: default;
}

.report-date-input[aria-disabled="false"]:hover {
    border-color: var(--border-strong, var(--interactive));
}

.report-date-input.is-active,
.report-date-input:focus-visible {
    border-color: var(--primary-action);
    outline: 2px solid color-mix(
        in srgb,
        var(--primary-action) 28%,
        transparent
    );
    outline-offset: 1px;
}

.report-date-picker {
    position: absolute;
    z-index: 80;
    top: calc(100% + 6px);
    left: 0;
    width: min(340px, calc(100vw - 32px));
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--surface-elevated);
    box-shadow: 0 12px 30px rgba(15, 35, 58, .16);
}

.report-date-picker-heading strong {
    color: var(--heading);
    font-size: 13px;
    font-weight: 750;
}

.report-calendar-header {
    display: grid;
    grid-template-columns: 32px 1fr 32px;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
}

.report-calendar-current-month {
    color: var(--heading);
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.report-date-months {
    display: grid;
    grid-template-columns: 1fr;
    margin-top: 10px;
}

.report-calendar-month h3 {
    display: none;
}

.report-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    row-gap: 3px;
}

.report-calendar-weekday {
    padding: 5px 0;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
    text-align: center;
}

.report-date-picker .report-calendar-day,
.report-date-picker .report-calendar-nav {
    display: grid;
    place-items: center;
    width: 100%;
    height: 32px;
    min-height: 32px;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--heading);
    font: inherit;
    font-weight: 500;
    box-shadow: none;
    cursor: pointer;
}

.report-date-picker .report-calendar-nav {
    width: 32px;
    border: 1px solid var(--border);
    font-size: 18px;
}

.report-date-picker .report-calendar-day:hover:not(:disabled),
.report-date-picker .report-calendar-nav:hover {
    background: var(--surface-subtle);
}

.report-date-picker .report-calendar-day.is-between {
    border-radius: 0;
    background: var(--info-bg);
}

.report-date-picker .report-calendar-day.is-endpoint,
.report-date-picker .report-calendar-day.is-endpoint:hover {
    background: var(--primary-action);
    color: #fff;
    font-weight: 750;
}

.report-date-picker .report-calendar-day:disabled {
    color: var(--text-muted);
    opacity: .35;
    cursor: not-allowed;
}

[data-report-date-error] {
    color: var(--danger, #b42318);
}

@media (max-width: 620px) {
    .report-date-fields {
        grid-template-columns: 1fr;
    }

    .report-date-picker {
        width: min(320px, calc(100vw - 32px));
    }
}

/*
 * Borrower stays visually aligned with the native Reports selects, but opens
 * a searchable scoped picker. No press/translate animation is allowed on
 * report filter controls so controls do not jump up/down when clicked.
 */
.report-borrower-field { position: relative; min-width: 0; }

.report-builder-card .report-builder-field select,
.report-builder-card .report-borrower-trigger,
.report-builder-card .report-borrower-search,
.report-builder-card .report-borrower-option {
    transform: none !important;
    translate: none !important;
}

.report-builder-card .report-builder-field select:active,
.report-builder-card .report-borrower-trigger:active,
.report-builder-card .report-borrower-option:active,
.report-builder-card button:active,
.report-filters-panel a:active {
    transform: none !important;
    translate: none !important;
    top: auto !important;
}

.report-borrower-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    min-height: 52px;
    padding: 0 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--surface-elevated);
    color: var(--heading);
    font: inherit;
    text-align: left;
    cursor: pointer;
    box-shadow: none;
}

.report-borrower-trigger:hover { border-color: var(--border-strong, var(--border)); }

.report-borrower-trigger:hover .report-borrower-chevron,
.report-borrower-trigger:focus .report-borrower-chevron,
.report-borrower-trigger:active .report-borrower-chevron,
.report-borrower-field.is-open .report-borrower-chevron {
    transform: none !important;
    translate: none !important;
}

.report-borrower-trigger:focus-visible,
.report-borrower-field.is-open .report-borrower-trigger {
    outline: 2px solid color-mix(in srgb, var(--primary-action) 28%, transparent);
    outline-offset: 1px;
    border-color: var(--primary-action);
}

.report-borrower-chevron {
    flex: 0 0 16px;
    width: 16px;
    height: 16px;
    display: block;
    pointer-events: none;
    transform: none !important;
    translate: none !important;
}

.report-borrower-menu {
    position: absolute;
    z-index: 80;
    top: calc(100% + 6px);
    left: 0;
    width: 100%;
    min-width: 320px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-elevated);
    box-shadow: 0 12px 30px rgba(15, 35, 58, .16);
    overflow: hidden;
}

.report-borrower-search-wrap {
    display: block;
    margin: 0 0 7px;
}

.report-borrower-search {
    width: 100%;
    min-height: 40px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface-elevated);
    color: var(--heading);
    font: inherit;
    font-size: 12.5px;
    box-shadow: none;
}

.report-borrower-options {
    display: block;
    max-height: 280px;
    overflow-y: auto;
    overscroll-behavior: contain;
    border-top: 1px solid var(--border);
}

.report-borrower-menu .report-borrower-option {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 10px 8px !important;
    border: 0 !important;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 72%, transparent) !important;
    border-radius: 0 !important;
    background: transparent !important;
    color: var(--heading);
    text-align: left;
    cursor: pointer;
    box-shadow: none !important;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
    transform: none !important;
    translate: none !important;
}

.report-borrower-menu .report-borrower-option:last-child {
    border-bottom: 0 !important;
}

.report-borrower-menu .report-borrower-option:hover,
.report-borrower-menu .report-borrower-option:focus-visible {
    background: var(--surface-subtle) !important;
    outline: none;
}

.report-borrower-menu .report-borrower-option.is-selected {
    background: color-mix(in srgb, var(--info-bg) 55%, transparent) !important;
}

.report-borrower-option strong {
    min-width: 0;
    font-size: 12.5px;
    font-weight: 700;
    line-height: 1.35;
}

.report-borrower-option small {
    flex: 0 1 auto;
    max-width: 55%;
    color: var(--text-muted);
    font-size: 10.5px;
    line-height: 1.35;
    text-align: right;
    overflow-wrap: anywhere;
}

.report-borrower-message {
    margin: 7px 4px 2px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.4;
}

.report-borrower-field[aria-busy="true"] .report-borrower-trigger {
    cursor: progress;
}

@media (max-width: 620px) {
    .report-borrower-menu {
        min-width: 0;
        width: 100%;
    }
}


/* BORROWER_SELECT_VISUAL_MATCH_V1
   Make the closed Borrower picker visually match Division / Office selects.
   Search/cascade logic is untouched.
*/
.report-builder-card .report-builder-field select,
.report-builder-card .report-borrower-trigger {
    width: 100% !important;
    height: 52px !important;
    min-height: 52px !important;
    padding: 0 16px !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    background: var(--surface-elevated) !important;
    color: var(--heading) !important;
    font: inherit !important;
    font-size: 16px !important;
    font-weight: 400 !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
}

.report-builder-card .report-borrower-trigger {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    text-align: left !important;
    cursor: pointer !important;
}

.report-builder-card .report-borrower-current {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    text-align: left !important;
    font-weight: 400 !important;
}

.report-builder-card .report-borrower-chevron {
    flex: 0 0 16px !important;
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    display: block !important;
    pointer-events: none !important;
    transform: none !important;
    translate: none !important;
}

.report-builder-card .report-borrower-trigger:hover,
.report-builder-card .report-borrower-trigger:focus,
.report-builder-card .report-borrower-trigger:active,
.report-builder-card .report-borrower-field.is-open .report-borrower-trigger {
    transform: none !important;
    translate: none !important;
    top: auto !important;
}

.report-builder-card .report-borrower-trigger:hover .report-borrower-chevron,
.report-builder-card .report-borrower-trigger:focus .report-borrower-chevron,
.report-builder-card .report-borrower-trigger:active .report-borrower-chevron,
.report-builder-card .report-borrower-field.is-open .report-borrower-chevron {
    transform: none !important;
    translate: none !important;
}

/* Keep dropdown aligned exactly with the closed Borrower field */
.report-builder-card .report-borrower-menu {
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    min-width: 100% !important;
    box-sizing: border-box !important;
}







/* REPORTS_BUILDER_COMPACT_TEXT_V2
   Match compact typography used across Admin workspaces.
   UI only â€” no report/filter/cascade logic changes.
*/

/* Main field values */
.report-builder-card select,
.report-builder-card input,
.report-builder-card button,
.report-builder-card .report-borrower-trigger,
.report-builder-card .report-borrower-current {
    font-size: 13px !important;
    line-height: 1.3 !important;
}

/* Labels: Report Type, Reporting Period, Division, Office, etc. */
.report-builder-card .report-builder-field > span,
.report-builder-card label > span,
.report-builder-card .report-builder-label {
    font-size: 11px !important;
    line-height: 1.25 !important;
    font-weight: 600 !important;
}

/* Report Type / Period / Division / Office / Borrower / Status */
.report-builder-card .report-builder-field select,
.report-builder-card .report-borrower-trigger {
    font-size: 13px !important;
    font-weight: 400 !important;
}

/* Borrower searchable dropdown */
.report-builder-card .report-borrower-search {
    font-size: 12px !important;
}

.report-builder-card .report-borrower-option strong {
    font-size: 12px !important;
}

.report-builder-card .report-borrower-option small {
    font-size: 10px !important;
}

/* Date range */
.report-builder-card .report-date-range,
.report-builder-card [data-date-range] {
    font-size: 12px !important;
}

/* Preview Report button */
.report-builder-card .report-generate-button,
.report-builder-card [data-preview-button-label] {
    font-size: 12px !important;
}

/* Filters heading/helper text */
.report-builder-card .report-filters-title,
.report-builder-card .report-filter-title {
    font-size: 12px !important;
}

.report-builder-card .report-filters-description,
.report-builder-card .report-description {
    font-size: 11px !important;
}

/* Optional/applied indicators */
.report-builder-card .badge,
.report-builder-card .report-filter-count {
    font-size: 10px !important;
}



/* REPORTS_FILTER_ROW_AND_CHEVRON_V1
   Layout/visual only. No report/filter/cascade logic changes.
*/

/* Maximum 4 filters per row on desktop */
.report-builder-card .report-filter-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 16px !important;
    align-items: end !important;
}

/* Native report selects: restore a stable visible chevron */
.report-builder-card .report-builder-field select {
    width: 100% !important;
    padding-right: 38px !important;

    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;

    background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230f2742' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m7 10 5 5 5-5'/%3E%3C/svg%3E") !important;

    background-repeat: no-repeat !important;
    background-position: right 14px center !important;
    background-size: 14px 14px !important;
}

/* Borrower custom picker arrow matches the native select arrows */
.report-builder-card .report-borrower-chevron {
    width: 14px !important;
    height: 14px !important;
    flex: 0 0 14px !important;
    margin-right: 0 !important;

    transform: none !important;
    translate: none !important;
    rotate: none !important;
}

/* Nothing should jump when clicked/opened */
.report-builder-card .report-builder-field select:active,
.report-builder-card .report-builder-field select:focus,
.report-builder-card .report-borrower-trigger:active,
.report-builder-card .report-borrower-trigger:focus,
.report-builder-card .report-borrower-field.is-open .report-borrower-trigger {
    transform: none !important;
    translate: none !important;
    top: auto !important;
}

/* Tablet */
@media (max-width: 1100px) {
    .report-builder-card .report-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

/* Mobile */
@media (max-width: 650px) {
    .report-builder-card .report-filter-grid {
        grid-template-columns: 1fr !important;
    }
}

</style>

<script>
(() => {
    const reportType = document.querySelector('[data-report-type]');
    const period = document.querySelector('#reports-academic-period');
    const division = document.querySelector('[data-filter-division]');
    const dependent = document.querySelector('[data-filter-depends-on-division]');
    const borrowerPicker = document.querySelector('[data-borrower-picker]');
    const borrowerValue = borrowerPicker?.querySelector('[data-borrower-value]');
    const borrowerTrigger = borrowerPicker?.querySelector('[data-borrower-trigger]');
    const borrowerCurrent = borrowerPicker?.querySelector('[data-borrower-current]');
    const borrowerMenu = borrowerPicker?.querySelector('[data-borrower-menu]');
    const borrowerSearch = borrowerPicker?.querySelector('[data-borrower-search]');
    const borrowerOptions = borrowerPicker?.querySelector('[data-borrower-options]');
    const borrowerMessage = borrowerPicker?.querySelector('[data-borrower-message]');
    const previewForm = document.querySelector('[data-report-preview-form]');
    const previewButton = previewForm?.querySelector('.report-generate-button');
    const previewLabel = previewForm?.querySelector('[data-preview-button-label]');
    const dateField =
        previewForm?.querySelector('.report-resolved-period');

    const datePicker =
        dateField?.querySelector('#report-date-picker');

    const dateTriggers = [
        ...(dateField?.querySelectorAll(
            '[data-report-date-trigger]'
        ) || []),
    ];

    const appliedStartDate =
        dateField?.querySelector(
            '[data-applied-start-date]'
        );

    const appliedEndDate =
        dateField?.querySelector(
            '[data-applied-end-date]'
        );

    const appliedStartLabel =
        dateField?.querySelector(
            '[data-applied-start-label]'
        );

    const appliedEndLabel =
        dateField?.querySelector(
            '[data-applied-end-label]'
        );

    const periodNote =
        dateField?.querySelector(
            '[data-report-period-note]'
        );

    const dateError =
        dateField?.querySelector(
            '[data-report-date-error]'
        );

    const dateMonths =
        datePicker?.querySelector(
            '[data-date-months]'
        );

    const calendarMonthLabel =
        datePicker?.querySelector(
            '[data-calendar-current-month]'
        );


    let activeDateField = null;
    let activeDateTrigger = null;
    let calendarMonth = null;


    const parseDate = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }

        const date =
            new Date(`${value}T00:00:00Z`);

        if (!Number.isFinite(date.getTime())) {
            return null;
        }

        return date.toISOString().slice(0, 10) === value
            ? date
            : null;
    };


    const validRange = (start, end) =>
        Boolean(
            parseDate(start)
            && parseDate(end)
            && end >= start
        );


    const formatDate = (value) => {
        const date = parseDate(value);

        if (!date) {
            return '';
        }

        const months = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];

        return `${
            String(date.getUTCDate()).padStart(2, '0')
        } ${
            months[date.getUTCMonth()]
        } ${
            date.getUTCFullYear()
        }`;
    };


    const isCustomRange =
        () => period?.value === 'custom';


    const commitDateRange = (start, end) => {

        if (!validRange(start, end)) {
            return false;
        }

        appliedStartDate.value = start;
        appliedEndDate.value = end;

        appliedStartLabel.textContent =
            formatDate(start);

        appliedEndLabel.textContent =
            formatDate(end);

        if (dateError) {
            dateError.hidden = true;
        }

        return true;
    };


    const closeDatePicker =
        (restoreFocus = false) => {

        if (!datePicker) {
            return;
        }

        datePicker.hidden = true;

        dateTriggers.forEach((trigger) => {
            trigger.setAttribute(
                'aria-expanded',
                'false'
            );

            trigger.classList.remove(
                'is-active'
            );
        });

        if (restoreFocus) {
            activeDateTrigger?.focus();
        }

        activeDateField = null;
        activeDateTrigger = null;
    };


    const positionDatePicker = () => {

        if (!datePicker || datePicker.hidden) {
            return;
        }

        const fieldLeft =
            dateField.getBoundingClientRect().left;

        datePicker.style.left =
            `${Math.max(
                16 - fieldLeft,
                Math.min(
                    0,
                    window.innerWidth
                    - 16
                    - fieldLeft
                    - datePicker.offsetWidth
                )
            )}px`;
    };


    const renderCalendar = (focusDate = '') => {

        dateMonths.replaceChildren();

        const month =
            new Date(calendarMonth);

        month.setUTCDate(1);


        calendarMonthLabel.textContent =
            new Intl.DateTimeFormat(
                'en-GB',
                {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }
            ).format(month);


        const section =
            document.createElement('section');

        section.className =
            'report-calendar-month';


        const grid =
            document.createElement('div');

        grid.className =
            'report-calendar-grid';


        [
            'Mo',
            'Tu',
            'We',
            'Th',
            'Fr',
            'Sa',
            'Su',
        ].forEach((weekday) => {

            const label =
                document.createElement('span');

            label.className =
                'report-calendar-weekday';

            label.textContent =
                weekday;

            label.setAttribute(
                'aria-hidden',
                'true'
            );

            grid.appendChild(label);
        });


        const blanks =
            (month.getUTCDay() + 6) % 7;

        for (
            let blank = 0;
            blank < blanks;
            blank++
        ) {
            grid.appendChild(
                document.createElement('span')
            );
        }


        const start =
            appliedStartDate.value;

        const end =
            appliedEndDate.value;

        const day =
            new Date(month);


        while (
            day.getUTCMonth()
            === month.getUTCMonth()
        ) {
            const value =
                day.toISOString().slice(0, 10);

            const button =
                document.createElement('button');

            button.type = 'button';

            button.className =
                'icon-button report-calendar-day';

            button.dataset.date =
                value;

            button.textContent =
                day.getUTCDate();


            if (
                activeDateField === 'start'
                && end
                && value > end
            ) {
                button.disabled = true;
            }


            if (
                activeDateField === 'end'
                && start
                && value < start
            ) {
                button.disabled = true;
            }


            const isStart =
                value === start;

            const isEnd =
                value === end;


            button.classList.toggle(
                'is-endpoint',
                activeDateField === 'end'
                    ? isEnd
                    : isStart
            );


            button.setAttribute(
                'aria-pressed',
                (
                    activeDateField === 'end'
                        ? isEnd
                        : isStart
                )
                    ? 'true'
                    : 'false'
            );


            grid.appendChild(button);

            day.setUTCDate(
                day.getUTCDate() + 1
            );
        }


        section.appendChild(grid);
        dateMonths.appendChild(section);


        if (focusDate) {
            dateMonths
                .querySelector(
                    `[data-date="${focusDate}"]`
                )
                ?.focus();
        }
    };


    const openDatePicker =
        (field, trigger) => {

        if (!isCustomRange()) {
            return;
        }


        activeDateField =
            field === 'end'
                ? 'end'
                : 'start';

        activeDateTrigger =
            trigger;


        dateTriggers.forEach((candidate) => {

            const active =
                candidate === trigger;

            candidate.setAttribute(
                'aria-expanded',
                active ? 'true' : 'false'
            );

            candidate.classList.toggle(
                'is-active',
                active
            );
        });


        const anchorDate =
            activeDateField === 'end'
                ? appliedEndDate.value
                : appliedStartDate.value;


        calendarMonth =
            parseDate(anchorDate)
            || parseDate(appliedStartDate.value)
            || new Date();


        calendarMonth.setUTCDate(1);

        datePicker.hidden = false;

        renderCalendar(anchorDate);

        positionDatePicker();
    };


    dateTriggers.forEach((trigger) => {

        trigger.addEventListener(
            'click',
            () => {

                if (
                    trigger.getAttribute(
                        'aria-disabled'
                    ) === 'true'
                ) {
                    return;
                }


                if (
                    !datePicker.hidden
                    && activeDateTrigger === trigger
                ) {
                    closeDatePicker(true);
                    return;
                }


                openDatePicker(
                    trigger.dataset.reportDateTrigger,
                    trigger
                );
            }
        );
    });


    dateMonths?.addEventListener(
        'click',
        (event) => {

            const day =
                event.target.closest(
                    '[data-date]'
                );

            if (
                !day
                || day.disabled
                || !parseDate(day.dataset.date)
            ) {
                return;
            }


            const value =
                day.dataset.date;


            let start =
                appliedStartDate.value;

            let end =
                appliedEndDate.value;


            if (activeDateField === 'start') {
                start = value;
            }

            if (activeDateField === 'end') {
                end = value;
            }


            if (!commitDateRange(start, end)) {
                return;
            }


            closeDatePicker(false);


            if (borrowerSearch) {
                borrowerSearch.value = '';
            }


            refreshBorrowers({
                query: '',
                validateSelection: true,
            });
        }
    );


    datePicker
        ?.querySelectorAll(
            '[data-date-month-step]'
        )
        .forEach((button) => {

            button.addEventListener(
                'click',
                () => {

                    calendarMonth.setUTCMonth(
                        calendarMonth.getUTCMonth()
                        + Number(
                            button.dataset.dateMonthStep
                        )
                    );

                    renderCalendar();
                }
            );
        });


    document.addEventListener(
        'click',
        (event) => {

            if (
                datePicker
                && !datePicker.hidden
                && !event
                    .composedPath()
                    .includes(dateField)
            ) {
                closeDatePicker();
            }
        }
    );


    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape'
                && datePicker
                && !datePicker.hidden
            ) {
                event.preventDefault();

                closeDatePicker(true);
            }
        }
    );


    window.addEventListener(
        'resize',
        positionDatePicker
    );
    reportType?.addEventListener('change', () => reportType.form?.submit());

    previewForm?.addEventListener('submit', (event) => {
        if (!validRange(appliedStartDate?.value, appliedEndDate?.value)) {
            event.preventDefault();
            dateError.textContent = 'Apply a valid Start Date and End Date before previewing the report.';
            dateError.hidden = false;
            return;
        }
        if (previewForm.dataset.previewSubmitting === '1') {
            event.preventDefault();
            return;
        }

        previewForm.dataset.previewSubmitting = '1';

        if (previewButton) {
            /*
             * Keep name="generated" value="1" successful for this GET submit.
             * The dataset flag blocks duplicate submits without disabling the
             * submitter and accidentally dropping the generated parameter.
             */
            previewButton.setAttribute('aria-disabled', 'true');
            previewButton.style.pointerEvents = 'none';
        }

        if (previewLabel) previewLabel.textContent = 'Loading Previewâ€¦';
    });

    /*
     * Cascade:
     * Reporting Period -> Division -> Office / Unit -> Borrower.
     *
     * Borrowers are requested from the server using the current upstream
     * scope. Only the first 50 are returned for a broad scope; typing a name
     * or email performs a server-side scoped search instead of rendering
     * hundreds of borrowers at once.
     */
    const unitOptions = dependent ? Array.from(dependent.options) : [];
    let borrowerRequestController = null;
    let borrowerSearchTimer = null;

    const setBorrower = (value = '', name = 'All borrowers') => {
        if (!borrowerValue || !borrowerCurrent) return;

        borrowerValue.value = value;
        borrowerCurrent.textContent = name;

        borrowerOptions?.querySelectorAll('[data-borrower-option]').forEach((option) => {
            const selected = option.dataset.value === value;
            option.classList.toggle('is-selected', selected);
            option.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
    };

    const closeBorrowerPicker = () => {
        if (borrowerMenu) borrowerMenu.hidden = true;
        borrowerPicker?.classList.remove('is-open');
        borrowerTrigger?.setAttribute('aria-expanded', 'false');
    };

    const applyDivision = () => {
        if (!division || !dependent) return;

        const selectedDivision = division.value;
        let currentStillValid = dependent.value === '';

        unitOptions.forEach((option) => {
            if (!option.value) return;

            const matches = !selectedDivision || option.dataset.division === selectedDivision;
            option.hidden = !matches;
            option.disabled = !matches;

            if (matches && option.value === dependent.value) {
                currentStillValid = true;
            }
        });

        if (!currentStillValid) {
            dependent.value = '';
        }
    };

    const borrowerScopeUrl = (query = '') => {
        if (!previewForm) return null;

        const params = new URLSearchParams();
        params.set('borrower_options', '1');
        params.set('report', reportType?.value || 'borrowing');
        params.set('academic_period', period?.value || 'month');
        if (isCustomRange()) {
            params.set('start_date', appliedStartDate.value);
            params.set('end_date', appliedEndDate.value);
        }

        if (division?.value) params.set('division', division.value);
        if (dependent?.value) params.set('unit', dependent.value);
        if (query.trim() !== '') params.set('q', query.trim());
        if (borrowerValue?.value) params.set('selected_borrower', borrowerValue.value);

        /*
         * Do not concatenate "?..." onto previewForm.action.
         * When a form has no explicit action, HTMLFormElement.action may resolve
         * to the current document URL including its existing query string.
         * Appending another "?" makes borrower_options part of the old query
         * value instead of a real query parameter, so Laravel returns the full
         * HTML report page and response.json() fails.
         *
         * Build a fresh URL from the form's explicit action (if any) or the
         * current pathname, then replace its search parameters atomically.
         */
        const endpoint = new URL(
            previewForm.getAttribute('action') || window.location.pathname,
            window.location.origin
        );
        endpoint.search = params.toString();

        return endpoint.toString();
    };

    const makeBorrowerOption = (item) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'report-borrower-option';
        option.dataset.borrowerOption = '';
        option.dataset.value = String(item.value ?? '');
        option.dataset.name = item.name || 'Borrower';
        option.setAttribute('role', 'option');

        const name = document.createElement('strong');
        name.textContent = item.name || 'Borrower';
        option.appendChild(name);

        if (item.email) {
            const email = document.createElement('small');
            email.textContent = item.email;
            option.appendChild(email);
        }

        return option;
    };

    const renderBorrowers = (
        items,
        payload = {},
        { validateSelection = true, query = '' } = {}
    ) => {
        if (!borrowerOptions) return;

        borrowerOptions.innerHTML = '';

        const all = document.createElement('button');
        all.type = 'button';
        all.className = 'report-borrower-option';
        all.dataset.borrowerOption = '';
        all.dataset.value = '';
        all.dataset.name = 'All borrowers';
        all.setAttribute('role', 'option');

        const allLabel = document.createElement('strong');
        allLabel.textContent = 'All borrowers';
        all.appendChild(allLabel);
        borrowerOptions.appendChild(all);

        items.forEach((item) => borrowerOptions.appendChild(makeBorrowerOption(item)));

        const selected = borrowerValue?.value || '';
        const selectedOption = Array.from(
            borrowerOptions.querySelectorAll('[data-borrower-option]')
        ).find((option) => option.dataset.value === selected);

        if (validateSelection && selected !== '' && !selectedOption) {
            /*
             * Upstream scope changed and the selected borrower no longer
             * exists in that scope. Reset immediately to All borrowers.
             */
            setBorrower('', 'All borrowers');
        } else {
            setBorrower(
                selected,
                selectedOption?.dataset.name
                    || borrowerCurrent?.textContent
                    || 'All borrowers'
            );
        }

        if (!borrowerMessage) return;

        const trimmedQuery = query.trim();

        if (items.length === 0 && trimmedQuery !== '') {
            borrowerMessage.textContent = 'No borrower matches this search in the current report scope.';
            borrowerMessage.hidden = false;
            return;
        }

        if (items.length === 0 && trimmedQuery === '') {
            borrowerMessage.textContent = 'No borrowers are available in the current report scope.';
            borrowerMessage.hidden = false;
            return;
        }

        if (payload.has_more) {
            borrowerMessage.textContent = `Showing the first ${payload.shown || items.length} of ${payload.count} matching borrowers. Search by name or email to find more.`;
            borrowerMessage.hidden = false;
            return;
        }

        borrowerMessage.hidden = true;
        borrowerMessage.textContent = '';
    };

    const refreshBorrowers = async ({
        open = false,
        query = '',
        validateSelection = true,
    } = {}) => {
        if (!borrowerPicker) return;

        const url = borrowerScopeUrl(query);
        if (!url) return;

        borrowerRequestController?.abort();
        borrowerRequestController = new AbortController();
        borrowerPicker.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: borrowerRequestController.signal,
            });

            if (!response.ok) {
                throw new Error(`Borrower scope request failed (${response.status})`);
            }

            const payload = await response.json();

            renderBorrowers(
                Array.isArray(payload.options) ? payload.options : [],
                payload,
                { validateSelection, query }
            );
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderBorrowers([], {}, { validateSelection: false, query });
                if (borrowerMessage) {
                    borrowerMessage.textContent = 'Borrower options could not be loaded. Please try again.';
                    borrowerMessage.hidden = false;
                }
            }
        } finally {
            borrowerPicker.removeAttribute('aria-busy');

            if (open && borrowerMenu) {
                borrowerMenu.hidden = false;
                borrowerPicker.classList.add('is-open');
                borrowerTrigger?.setAttribute('aria-expanded', 'true');
                borrowerSearch?.focus();
            }
        }
    };

    borrowerTrigger?.addEventListener('click', async () => {
        const opening = borrowerMenu?.hidden !== false;

        if (!opening) {
            closeBorrowerPicker();
            return;
        }

        if (borrowerSearch) borrowerSearch.value = '';

        await refreshBorrowers({
            open: true,
            query: '',
            validateSelection: true,
        });
    });

    borrowerSearch?.addEventListener('input', () => {
        window.clearTimeout(borrowerSearchTimer);

        borrowerSearchTimer = window.setTimeout(() => {
            refreshBorrowers({
                query: borrowerSearch.value,
                validateSelection: false,
            });
        }, 250);
    });

    borrowerOptions?.addEventListener('click', (event) => {
        const option = event.target.closest('[data-borrower-option]');
        if (!option) return;

        setBorrower(
            option.dataset.value || '',
            option.dataset.name || 'All borrowers'
        );

        closeBorrowerPicker();
    });

    division?.addEventListener('change', async () => {
        applyDivision();

        if (borrowerSearch) borrowerSearch.value = '';

        await refreshBorrowers({
            query: '',
            validateSelection: true,
        });
    });

    dependent?.addEventListener('change', () => {
        if (borrowerSearch) borrowerSearch.value = '';

        refreshBorrowers({
            query: '',
            validateSelection: true,
        });
    });

    period?.addEventListener('change', () => {
        closeDatePicker();
        const custom = isCustomRange();
        const selected = period.selectedOptions[0];
        dateTriggers.forEach((trigger) => {
            trigger.setAttribute(
                'aria-disabled',
                custom ? 'false' : 'true'
            );

            trigger.tabIndex =
                custom ? 0 : -1;
        });
        periodNote.textContent = selected.dataset.periodNote || '';
        periodNote.hidden = !periodNote.textContent;
        const periodAvailable =
            selected.dataset.periodAvailable !== '0';

        if (!custom && !periodAvailable) {

            // Academic period exists as a choice but has no configured dates.
            appliedStartLabel.textContent = 'Not set';
            appliedEndLabel.textContent = 'Not set';

            // Do not duplicate the configuration message under the fields.
            periodNote.textContent = '';
            periodNote.hidden = true;

        } else if (!custom) {

            commitDateRange(
                selected.dataset.startDate,
                selected.dataset.endDate
            );
        }
        if (borrowerSearch) borrowerSearch.value = '';

        refreshBorrowers({
            query: '',
            validateSelection: true,
        });
    });

    
    /* UNAVAILABLE PERIOD INITIAL DISPLAY START */
    (() => {
        const selected =
            period?.selectedOptions?.[0];

        if (
            selected
            && selected.dataset.periodAvailable === '0'
            && !isCustomRange()
        ) {
            appliedStartLabel.textContent = 'Not set';

            appliedEndLabel.textContent = 'Not set';

            periodNote.textContent = '';
            periodNote.hidden = true;
        }
    })();
    /* UNAVAILABLE PERIOD INITIAL DISPLAY END */

document.addEventListener('click', (event) => {
        if (!borrowerPicker || borrowerPicker.contains(event.target)) return;
        closeBorrowerPicker();
    });

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.key !== 'Escape' || !borrowerPicker || borrowerMenu?.hidden) return;

        closeBorrowerPicker();
        borrowerTrigger?.focus();
    });

    applyDivision();
})();
</script>

<style>
/* REMOVE DATE RANGE FILL */
.report-date-picker .report-calendar-day.is-between {
    background: transparent !important;
    border-radius: var(--radius-sm) !important;
}
</style>
<style>
/* ACTIVE FIELD SINGLE DATE FINAL */

.report-date-picker .report-calendar-day.is-between {
    background: transparent !important;
}

.report-date-picker .report-calendar-day.is-endpoint {
    background: var(--primary-action) !important;
    color: #fff !important;
    font-weight: 750 !important;
}

.report-date-picker .report-calendar-day:not(.is-endpoint) {
    background: transparent;
}
</style>


<style>
/* COLLAPSIBLE REPORT FILTERS FINAL */

/*
|--------------------------------------------------------------------------
| Compact Filters Trigger
|--------------------------------------------------------------------------
*/

.report-filter-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    margin-top: 16px;
}

.report-filter-toggle {
    appearance: none;

    display: inline-flex;
    align-items: center;
    gap: 8px;

    min-height: 34px;

    padding: 6px 10px;

    border: 1px solid transparent;
    border-radius: 7px;

    background: transparent;

    color: var(--heading);

    font: inherit;
    font-size: 12.5px;
    font-weight: 750;

    cursor: pointer;

    transition:
        background .15s ease,
        border-color .15s ease;
}

.report-filter-toggle:hover {
    border-color: var(--border);
    background: var(--surface-subtle);
}

.report-filter-toggle:focus-visible {
    outline: 2px solid var(--primary-action);
    outline-offset: 2px;
}

.report-filter-toggle-icon {
    display: grid;
    place-items: center;

    width: 18px;
    height: 18px;

    color: var(--text-soft);
}

.report-filter-toggle-icon svg {
    width: 15px;
    height: 15px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.report-filter-toggle-label {
    line-height: 1;
}

.report-filter-toggle-badge {
    display: inline-flex;
    align-items: center;

    min-height: 19px;

    padding: 1px 7px;

    border: 1px solid var(--border);
    border-radius: 999px;

    background: var(--surface-elevated);

    color: var(--text-muted);

    font-size: 9.5px;
    font-weight: 750;
    letter-spacing: .035em;
    line-height: 1;
    text-transform: uppercase;
}

.report-filter-toggle-badge.is-active {
    border-color:
        color-mix(
            in srgb,
            var(--primary-action) 22%,
            var(--border)
        );

    background:
        color-mix(
            in srgb,
            var(--primary-action) 8%,
            var(--surface-elevated)
        );

    color: var(--primary-action);

    text-transform: none;
}

.report-filter-toggle-chevron {
    display: inline-flex;

    margin-left: 1px;

    color: var(--text-muted);

    transition: transform .18s ease;
}

.report-filter-toggle[aria-expanded="true"]
.report-filter-toggle-chevron {
    transform: rotate(180deg);
}


/*
|--------------------------------------------------------------------------
| Clear Filters
|--------------------------------------------------------------------------
*/

.report-filter-clear {
    appearance: none;

    padding: 5px 7px;

    border: 0;

    background: transparent;

    color: var(--primary-action);

    font: inherit;
    font-size: 11px;
    font-weight: 700;

    cursor: pointer;
}

.report-filter-clear:hover {
    text-decoration: underline;
}

.report-filter-clear[hidden] {
    display: none !important;
}


/*
|--------------------------------------------------------------------------
| Existing Filter Panel
|--------------------------------------------------------------------------
*/

/*
 * The toggle above now serves as the Filters heading,
 * so remove the duplicate heading inside the old panel.
 */
.report-filters-panel .report-filters-heading {
    display: none !important;
}

.report-filters-panel {
    margin-top: 8px !important;
}

/*
 * Browser's hidden attribute must override the panel's
 * existing display:grid declaration.
 */
.report-filters-panel[hidden] {
    display: none !important;
}


/*
|--------------------------------------------------------------------------
| Expanded State
|--------------------------------------------------------------------------
*/

.report-filters-panel:not([hidden]) {
    animation: reportFiltersReveal .14s ease-out;
}

@keyframes reportFiltersReveal {
    from {
        opacity: 0;
        transform: translateY(-3px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


@media (prefers-reduced-motion: reduce) {
    .report-filter-toggle-chevron,
    .report-filters-panel:not([hidden]) {
        transition: none;
        animation: none;
    }
}


@media (max-width: 620px) {
    .report-filter-toggle-row {
        align-items: center;
    }

    .report-filter-toggle {
        padding-left: 4px;
    }
}

/* COLLAPSIBLE REPORT FILTERS FINAL END */
</style>


<script>
/* COLLAPSIBLE REPORT FILTERS SCRIPT FINAL */

(() => {
    const initReportFilters = () => {

        const panel =
            document.querySelector('.report-filters-panel');

        if (!panel) return;

        /*
         * Prevent duplicate initialization.
         */
        if (panel.dataset.collapsibleFiltersReady === '1') {
            return;
        }

        panel.dataset.collapsibleFiltersReady = '1';


        /* --------------------------------------------------------
           Build compact trigger row
        -------------------------------------------------------- */

        const row = document.createElement('div');

        row.className = 'report-filter-toggle-row';

        const toggle = document.createElement('button');

        toggle.type = 'button';
        toggle.className = 'report-filter-toggle';
        toggle.setAttribute(
            'aria-controls',
            'report-filter-panel-collapsible'
        );

        panel.id =
            panel.id || 'report-filter-panel-collapsible';


        toggle.innerHTML = `
            <span
                class="report-filter-toggle-icon"
                aria-hidden="true"
            >
                <svg viewBox="0 0 24 24">
                    <path d="M4 6h16"></path>
                    <path d="M7 12h10"></path>
                    <path d="M10 18h4"></path>
                </svg>
            </span>

            <span class="report-filter-toggle-label">
                Filters
            </span>

            <span
                class="report-filter-toggle-badge"
                data-report-filter-badge
            >
                Optional
            </span>

            <span
                class="report-filter-toggle-chevron"
                aria-hidden="true"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="m6 9 6 6 6-6"></path>
                </svg>
            </span>
        `;


        const clear = document.createElement('button');

        clear.type = 'button';
        clear.className = 'report-filter-clear';
        clear.textContent = 'Clear all';
        clear.hidden = true;


        row.append(toggle, clear);

        panel.before(row);


        const badge =
            toggle.querySelector(
                '[data-report-filter-badge]'
            );


        /* --------------------------------------------------------
           Identify actual active filters
        -------------------------------------------------------- */

        const filterFields = () =>
            Array.from(
                panel.querySelectorAll(
                    'select[name], input[name], textarea[name]'
                )
            )
            .filter((field) => {

                if (field.disabled) return false;

                /*
                 * Ignore internal search/display controls that
                 * do not represent submitted filter values.
                 */
                if (
                    field.hasAttribute('data-borrower-search') ||
                    field.type === 'search'
                ) {
                    return false;
                }

                return true;
            });


        const fieldIsActive = (field) => {

            if (
                field.type === 'checkbox' ||
                field.type === 'radio'
            ) {
                return field.checked;
            }

            const value =
                String(field.value ?? '').trim();

            if (!value) return false;


            /*
             * Treat "All divisions", "All units",
             * "All decisions", etc. as unfiltered even if
             * a backend option happens to use a non-empty value.
             */
            if (field.tagName === 'SELECT') {

                const option =
                    field.options[
                        field.selectedIndex
                    ];

                const label =
                    String(
                        option?.textContent ?? ''
                    ).trim();

                if (/^all\b/i.test(label)) {
                    return false;
                }
            }

            return true;
        };


        const activeFields = () => {

            /*
             * Count unique submitted filter names rather than
             * duplicate UI controls representing the same filter.
             */
            const activeNames = new Set();

            filterFields().forEach((field) => {

                if (
                    field.name &&
                    fieldIsActive(field)
                ) {
                    activeNames.add(field.name);
                }
            });

            return activeNames;
        };


        /* --------------------------------------------------------
           State
        -------------------------------------------------------- */

        const setExpanded = (expanded) => {

            toggle.setAttribute(
                'aria-expanded',
                expanded ? 'true' : 'false'
            );

            panel.hidden = !expanded;
        };


        const refreshState = ({
            preserveExpanded = true
        } = {}) => {

            const count = activeFields().size;

            if (count > 0) {

                badge.textContent =
                    `${count} ${
                        count === 1
                            ? 'applied'
                            : 'applied'
                    }`;

                badge.classList.add('is-active');

                clear.hidden = false;

                /*
                 * Active filters always remain visible so users
                 * immediately understand why results are narrowed.
                 */
                setExpanded(true);

                return;
            }


            badge.textContent = 'Optional';
            badge.classList.remove('is-active');

            clear.hidden = true;


            if (!preserveExpanded) {
                setExpanded(false);
            }
        };


        /*
         * Initial state:
         * - no filters => collapsed
         * - active filters => expanded
         */
        const initialCount = activeFields().size;

        setExpanded(initialCount > 0);

        refreshState();


        /* --------------------------------------------------------
           Toggle
        -------------------------------------------------------- */

        toggle.addEventListener('click', () => {

            const expanded =
                toggle.getAttribute(
                    'aria-expanded'
                ) === 'true';

            setExpanded(!expanded);
        });


        /* --------------------------------------------------------
           Update applied count when fields change
        -------------------------------------------------------- */

        panel.addEventListener('change', () => {
            requestAnimationFrame(() => {
                refreshState();
            });
        });

        panel.addEventListener('input', () => {
            requestAnimationFrame(() => {
                refreshState();
            });
        });


        /* --------------------------------------------------------
           Clear all
        -------------------------------------------------------- */

        clear.addEventListener('click', () => {

            const fields = filterFields();


            /*
             * Clear normal filter controls.
             */
            fields.forEach((field) => {

                if (
                    field.type === 'checkbox' ||
                    field.type === 'radio'
                ) {
                    field.checked = false;
                    return;
                }

                if (field.tagName === 'SELECT') {

                    /*
                     * Prefer the blank/All option.
                     */
                    const blankOption =
                        Array.from(field.options)
                            .find((option) =>
                                String(
                                    option.value
                                ).trim() === ''
                            );

                    if (blankOption) {
                        field.value =
                            blankOption.value;
                    }
                    else {
                        field.selectedIndex = 0;
                    }

                    return;
                }

                field.value = '';
            });


            /*
             * Notify existing report-builder logic, including
             * dependent Office/Unit and Borrower selectors.
             */
            fields.forEach((field) => {

                field.dispatchEvent(
                    new Event(
                        'change',
                        {
                            bubbles: true
                        }
                    )
                );
            });


            requestAnimationFrame(() => {

                refreshState({
                    preserveExpanded: false
                });

                setExpanded(false);
            });
        });
    };


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initReportFilters,
            {
                once: true
            }
        );
    }
    else {
        initReportFilters();
    }
})();

/* COLLAPSIBLE REPORT FILTERS SCRIPT FINAL END */
</script>