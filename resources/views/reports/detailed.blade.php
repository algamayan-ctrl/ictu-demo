@extends('layouts.app', ['title' => 'Reports'])

@section('content')

@include('reports.partials.workspace-styles')
@include('reports.partials.detail-styles')
@include('reports.document.styles')

@php
    $reportCount = $showPreview ? $dataset->count() : 0;

    /*
     * Keep the period explicitly chosen by the user.
     * The backend may resolve an unavailable academic period
     * to another date range, but the UI must still show the
     * correct configuration state.
     */
    $requestedPeriodSelection =
        (string) request()->query(
            'academic_period',
            $periodSelection
        );
    /*
     * REPORT PERIOD AVAILABILITY STATE START
     */
    $reportPeriodUnavailable = false;
    $reportPeriodUnavailableTitle = '';
    $reportPeriodUnavailableMessage = '';

    if ($requestedPeriodSelection === 'semester' && ! $activeAcademicPeriod) {

        $reportPeriodUnavailable = true;

        $reportPeriodUnavailableTitle =
            'Current semester is not configured yet.';

        $reportPeriodUnavailableMessage =
            'Configure an active semester before previewing records for this period.';

    } elseif ($requestedPeriodSelection === 'previous_semester') {

        $previousSemesterConfigured =
            isset($reportingPeriods['previous_semester'])
            && ! empty($reportingPeriods['previous_semester']['start_date'] ?? null)
            && ! empty($reportingPeriods['previous_semester']['end_date'] ?? null);

        if (! $previousSemesterConfigured) {

            $reportPeriodUnavailable = true;

            $reportPeriodUnavailableTitle =
                'Previous semester is not configured yet.';

            $reportPeriodUnavailableMessage =
                'Configure the previous semester before previewing records for this period.';
        }

    } elseif (
        $requestedPeriodSelection === 'academic_year'
        && ! $activeAcademicPeriod
    ) {

        $reportPeriodUnavailable = true;

        $reportPeriodUnavailableTitle =
            'Current academic year is not configured yet.';

        $reportPeriodUnavailableMessage =
            'Configure an active academic year before previewing records for this period.';
    }

    /*
     * Never display records from the current-month fallback when the
     * selected academic period itself is unavailable.
     */
    if ($reportPeriodUnavailable) {
        $reportCount = 0;
    }

    /* REPORT PERIOD AVAILABILITY STATE END */
    $appliedFilters = $showPreview ? ($dataset->meta['applied_filters'] ?? []) : [];
    $scopeRows = $showPreview
        ? \App\Reports\ReportScopeFormatter::rows($appliedFilters)
        : [];
    $clearFiltersUrl = route('reports.index', [
        'report' => $selectedReport,
        'academic_period' => $periodSelection,
        ...($periodSelection === 'custom' ? ['start_date' => $from->toDateString(), 'end_date' => $to->toDateString()] : []),
        'generated' => 1,
    ]);
@endphp

<div class="reporting-workspace reporting-detail">
    <section class="page-heading">
        <div>
            <p class="eyebrow">Formal reporting</p>
            <h1>Reports</h1>
            <p>View operational records, apply filters, then print or export the selected report.</p>
        </div>
    </section>

    <div class="reports-detail-page">
        @include('reports.partials.report-builder')

        <section class="content-area">
            @if($showPreview)
                <div class="report-preview-bar">
                    <div>
                        <p class="report-preview-label">Report records</p>
                        <h2 class="report-preview-title">{{ $selectedReportMeta['label'] }}</h2>
                        <div class="report-preview-meta">
                            <span>
                                @if($reportPeriodUnavailable)
                                    Reporting period unavailable
                                @else
                                    {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                                @endif
                            </span>
                            <span>{{ number_format($reportCount) }} {{ $reportCount === 1 ? 'record' : 'records' }}</span>
                        </div>

                        @if(! empty($scopeRows))
                            <p class="report-preview-filters" aria-label="Selected report scope">
                                @foreach($scopeRows as $scope)
                                    <span>
                                        <strong>{{ $scope['label'] }}:</strong>
                                        {{ $scope['value'] }}
                                    </span>
                                    @unless($loop->last)
                                        
                                    @endunless
                                @endforeach
                            </p>
                        @endif
                    </div>

                    @if($reportCount > 0)
                        <button class="button primary ui-pressable" type="button" data-open-export-options>
                            <x-icon name="upload" size="16" />
                            Export / Print
                        </button>
                    @endif
                </div>

                @if(! empty($dataset->meta['rejected_filters']))
                    <p class="report-filter-warning" role="status">
                        <x-icon name="warning" size="16" />
                        <span>
                            These filters were not recognised and were ignored:
                            {{ implode(', ', array_keys($dataset->meta['rejected_filters'])) }}.
                        </span>
                    </p>
                @endif

                @if($reportCount > 0)
                    {{-- SYSTEM REPORT PREVIEW START --}}

                    <div class="report-system-preview"
                        data-report-system-preview
                        data-report-count="{{ $reportCount }}">

                        <div class="report-system-summary-grid"
                            data-report-system-summary
                            aria-label="Report summary">
                        </div>

                        <section class="report-system-records-card"
                            aria-labelledby="report-system-records-heading">

                            <div class="report-system-records-header">

                                <div>
                                    <p class="report-system-records-eyebrow">
                                        System records
                                    </p>

                                    <h3 id="report-system-records-heading">
                                        {{ $selectedReportMeta['label'] }} Records
                                    </h3>
                                </div>

                                <label class="report-system-search">
                                    <span class="report-system-search-icon"
                                        aria-hidden="true">
                                        ⌕
                                    </span>

                                    <input type="search"
                                        data-report-system-search
                                        placeholder="Search records..."
                                        autocomplete="off"
                                        aria-label="Search report records">
                                </label>

                            </div>

                            <div class="report-system-table-scroll"
                                data-report-system-table>
                            </div>

                            <p class="report-system-record-count"
                                data-report-system-record-count
                                role="status"
                                aria-live="polite">
                            </p>

                        </section>
                    </div>


                    {{-- Formal document: hidden in system view,
                         retained for Export / Print --}}
                    <div class="report-print-view"
                        data-report-print-view
                        aria-hidden="true">

                        <div class="report-preview-sheet report-preview-sheet--{{ App\Reports\ReportCatalogue::orientation($selectedReport) }}">

                            @include('reports.document.sheet', ['rows' => $records, 'options' => [
                                'interactive' => true,
                                'header' => false,
                                'metadata' => false,
                            ]])

                        </div>
                    </div>

                    {{-- SYSTEM REPORT PREVIEW END --}}
                    @if($records->hasPages())
                        @php
                            $recordsTotal = $records->total();
                            $recordsFirst = $records->firstItem() ?? 0;
                            $recordsLast = $records->lastItem() ?? 0;
                            $recordsPerPage = $records->perPage();
                        @endphp

                        <div class="report-records-footer report-records-footer--inventory-style">

                            <p
                                class="report-records-summary"
                                role="status"
                                aria-live="polite"
                            >
                                Showing
                                {{ number_format($recordsFirst) }}–{{ number_format($recordsLast) }}
                                of {{ number_format($recordsTotal) }}
                                {{ $recordsTotal === 1 ? 'record' : 'records' }}
                            </p>

                            <div class="report-records-controls">

                                <label class="report-per-page-control">
                                    <select
                                        aria-label="Records per page"
                                        data-report-per-page
                                    >
                                        @foreach([15, 30, 50] as $pageSize)
                                            <option
                                                value="{{ $pageSize }}"
                                                @selected($recordsPerPage === $pageSize)
                                            >
                                                {{ $pageSize }} records per page
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                {{ $records
                                    ->appends(request()->except('page'))
                                    ->onEachSide(1)
                                    ->links('reports.partials.report-pagination') }}

                            </div>

                        </div>
                    @endif
                @else
                    <section class="report-no-results {{ $reportPeriodUnavailable ? 'is-period-unavailable' : '' }}" aria-labelledby="report-no-results-title">

    @php
        $emptyReportContext = strtolower(
            ($selectedReportMeta['label'] ?? '') . ' ' . ($selectedReport ?? '')
        );

        $emptyReportIcon =
            str_contains($emptyReportContext, 'inventory') ||
            str_contains($emptyReportContext, 'asset') ||
            str_contains($emptyReportContext, 'utilization')
                ? 'inventory'
                : (
                    str_contains($emptyReportContext, 'return') ||
                    str_contains($emptyReportContext, 'overdue')
                        ? 'return'
                        : (
                            str_contains($emptyReportContext, 'borrower') ||
                            str_contains($emptyReportContext, 'accountability')
                                ? 'borrower'
                                : (
                                    str_contains($emptyReportContext, 'audit') ||
                                    str_contains($emptyReportContext, 'history')
                                        ? 'history'
                                        : 'request'
                                )
                        )
                );
    @endphp

    <div class="report-no-results-inner">
        @if($reportPeriodUnavailable)
            <span class="report-period-unavailable-icon" aria-hidden="true">
                <svg viewBox="0 0 72 72">
                    <rect
                        x="14"
                        y="17"
                        width="44"
                        height="40"
                        rx="5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M14 29h44"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M24 12v10M48 12v10"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                    />

                    <path
                        d="M25 38h5M35 38h5M45 38h5M25 47h5M35 47h5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    />

                    <circle
                        cx="51"
                        cy="51"
                        r="9"
                        fill="var(--surface-elevated)"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />

                    <path
                        d="M47.5 51h7"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                    />
                </svg>
            </span>
        @endif

        <span class="report-no-results-illustration" aria-hidden="true">

            @if($emptyReportIcon === 'inventory')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M14 25 36 13l22 12v27L36 64 14 52z"/>
                    <path class="empty-line" d="M13 24 36 12l23 12v27L36 63 13 51z"/>
                    <path class="empty-line" d="M13 24 36 37l23-13M36 37v26"/>
                    <path class="empty-line" d="M25 18 48 31"/>
                    <circle class="empty-line" cx="54" cy="52" r="8"/>
                    <path class="empty-line" d="m60 58 6 6"/>
                </svg>

            @elseif($emptyReportIcon === 'return')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M20 11h31l8 9v39H20z"/>
                    <path class="empty-line" d="M19 10h30l9 9v40H19z"/>
                    <path class="empty-line" d="M49 10v10h9M27 30h22M27 38h17"/>
                    <path class="empty-line" d="M11 52h25M11 52l7-7M11 52l7 7"/>
                </svg>

            @elseif($emptyReportIcon === 'borrower')
                <svg viewBox="0 0 72 72">
                    <circle class="empty-soft" cx="33" cy="28" r="14"/>
                    <circle class="empty-line" cx="33" cy="25" r="10"/>
                    <path class="empty-line" d="M15 60c2-12 9-18 18-18s16 6 18 18"/>
                    <circle class="empty-line" cx="55" cy="50" r="8"/>
                    <path class="empty-line" d="m61 56 6 6"/>
                </svg>

            @elseif($emptyReportIcon === 'history')
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M19 12h31l9 9v39H19z"/>
                    <path class="empty-line" d="M18 10h31l9 10v40H18zM49 10v11h9"/>
                    <path class="empty-line" d="M26 31h22M26 39h18M26 47h13"/>
                    <circle class="empty-line" cx="55" cy="53" r="8"/>
                    <path class="empty-line" d="M55 49v5l4 2"/>
                </svg>

            @else
                <svg viewBox="0 0 72 72">
                    <path class="empty-soft" d="M19 11h31l9 9v39H19z"/>
                    <path class="empty-line" d="M18 10h31l9 10v40H18zM49 10v11h9"/>
                    <path class="empty-line" d="M27 31h22M27 39h19M27 47h12"/>
                    <circle class="empty-line" cx="53" cy="51" r="8"/>
                    <path class="empty-line" d="m59 57 6 6"/>
                    <path class="empty-spark" d="m13 20 1.4 3.2 3.2 1.4-3.2 1.4-1.4 3.2-1.4-3.2-3.2-1.4 3.2-1.4z"/>
                </svg>
            @endif

        </span>

        @if($reportPeriodUnavailable)

                            <h3 id="report-no-results-title">
                                {{ $reportPeriodUnavailableTitle }}
                            </h3>

                            <p>
                                {{ $reportPeriodUnavailableMessage }}
                            </p>

                        @else

                            <h3 id="report-no-results-title">
                                No records yet.
                            </h3>

                            <p>
                                {{ App\Reports\ReportCatalogue::emptyMessage($selectedReport) }}
                            </p>

                        @endif
                        <a class="button secondary small ui-pressable" href="#report-builder">Change Period or Filters</a>

    </div>

</section>
                @endif
            @else
                <section class="report-preview-empty" aria-labelledby="report-preview-empty-heading">
                    <span class="report-preview-empty-icon" aria-hidden="true">
                        <x-icon name="report-document" size="19" />
                    </span>
                    <div>
                        <p class="report-preview-label" id="report-preview-empty-heading">Report preview</p>
                        <p>Choose the report options above, then select Preview Report to review the records here.</p>
                    </div>
                </section>
            @endif
        </section>

        @if($showPreview && $reportCount > 0)
            <section class="content-area">
                <p class="report-boundary-note">
                    <x-icon name="information" size="18" />
                    <span>
                        All reports are based on official operational records.
                        For descriptive analysis and scheduled demand, use the Analytics module.
                    </span>
                </p>
            </section>
        @endif
    </div>
</div>





@if($showPreview && $reportCount > 0)

{{-- SYSTEM REPORT PREVIEW SCRIPT START --}}
<script>
document.addEventListener('DOMContentLoaded', () => {

    const systemPreview =
        document.querySelector('[data-report-system-preview]');

    const printView =
        document.querySelector('[data-report-print-view]');

    if (!systemPreview || !printView) {
        return;
    }


    const summaryContainer =
        systemPreview.querySelector(
            '[data-report-system-summary]'
        );

    const tableContainer =
        systemPreview.querySelector(
            '[data-report-system-table]'
        );

    const searchInput =
        systemPreview.querySelector(
            '[data-report-system-search]'
        );

    const countLabel =
        systemPreview.querySelector(
            '[data-report-system-record-count]'
        );


    const tables = [
        ...printView.querySelectorAll('table')
    ];


    if (!tables.length) {
        return;
    }


    const cleanText = (value) =>
        String(value || '')
            .replace(/\s+/g, ' ')
            .trim();


    // --------------------------------------------------------
    // Find the detailed records table.
    // The detailed table normally has the largest header count.
    // --------------------------------------------------------

    const dataTable = [...tables]
        .sort((a, b) =>
            b.querySelectorAll('thead th').length
            - a.querySelectorAll('thead th').length
        )[0];


    const summaryTable =
        tables.find((table) =>
            table !== dataTable
            && table.querySelectorAll('tr').length
        );


    // --------------------------------------------------------
    // Summary cards
    // --------------------------------------------------------

    let summaryCardCount = 0;


    const addSummaryCard = (label, value) => {

        const card =
            document.createElement('article');

        card.className =
            'report-system-summary-card';
const content =
            document.createElement('div');


        const name =
            document.createElement('span');

        name.className =
            'report-system-summary-label';

        name.textContent =
            label;


        const amount =
            document.createElement('strong');

        amount.className =
            'report-system-summary-value';

        amount.textContent =
            value;


        content.append(
            name,
            amount
        );

        card.append(content);

        summaryContainer.appendChild(card);

        summaryCardCount++;
    };


    if (summaryTable) {

        [
            ...summaryTable.querySelectorAll('tr')
        ].forEach((row) => {

            const cells = [
                ...row.querySelectorAll('th, td')
            ]
                .map((cell) =>
                    cleanText(cell.textContent)
                )
                .filter(Boolean);


            if (cells.length < 2) {
                return;
            }


            const label =
                cells[0];

            const value =
                cells[cells.length - 1];


            if (
                !label
                || !value
                || /report summary/i.test(label)
            ) {
                return;
            }


            addSummaryCard(
                label,
                value
            );
        });
    }


    if (!summaryCardCount) {

        addSummaryCard(
            'Total records',
            systemPreview.dataset.reportCount || '0'
        );
    }


    // --------------------------------------------------------
    // Normal application table
    // --------------------------------------------------------

    if (!dataTable) {
        return;
    }


    const systemTable =
        dataTable.cloneNode(true);


    systemTable.classList.add(
        'report-system-table'
    );


    systemTable.removeAttribute(
        'style'
    );


    systemTable
        .querySelectorAll('[style]')
        .forEach((element) =>
            element.removeAttribute('style')
        );


    systemTable
        .querySelectorAll('[width], [height]')
        .forEach((element) => {

            element.removeAttribute('width');
            element.removeAttribute('height');

        });


    const caption =
        systemTable.querySelector('caption');

    caption?.remove();


    tableContainer.replaceChildren(
        systemTable
    );


    // --------------------------------------------------------
    // Search records
    // --------------------------------------------------------

    const rows = [
        ...systemTable.querySelectorAll('tbody tr')
    ];


    const updateCount = () => {

        const visible =
            rows.filter(
                (row) => !row.hidden
            ).length;

        const total =
            rows.length;


        if (total === 1) {

            countLabel.textContent =
                'Showing 1 record';

            return;
        }


        countLabel.textContent =
            `Showing ${visible} of ${total} records`;
    };


    const filterRows = () => {

        const query =
            cleanText(
                searchInput?.value
            ).toLowerCase();


        rows.forEach((row) => {

            const rowText =
                cleanText(
                    row.textContent
                ).toLowerCase();


            row.hidden =
                Boolean(query)
                && !rowText.includes(query);

        });


        updateCount();
    };


    searchInput?.addEventListener(
        'input',
        filterRows
    );


    updateCount();

});
</script>
{{-- SYSTEM REPORT PREVIEW SCRIPT END --}}

@endif
@if($showPreview && $reportCount > 0)
    @include('reports.partials.export-options')
    @include('reports.partials.export-options-script')
@endif

{{-- UNIVERSAL SYSTEM RECORDS TABLE SCRIPT START --}}
<script>
(() => {
    const classifyReportTable = (table) => {
        if (!table || table.dataset.cleanReportTable === '1') {
            return;
        }

        const headers =
            Array.from(table.querySelectorAll('thead th'));

        if (!headers.length) {
            return;
        }

        const normalise =
            (value) =>
                String(value || '')
                    .toLowerCase()
                    .replace(/\s+/g, ' ')
                    .trim();

        const classifyHeader = (header) => {
            const text = normalise(header.textContent);

            /*
             * IDs / request numbers / references.
             */
            if (
                /\b(request no|request number|transaction no|transaction number|reference|record no|record number|custody no|custody number)\b/.test(text)
            ) {
                return 'report-col-id';
            }

            /*
             * People.
             */
            if (
                /\b(borrower|requested by|prepared by|verified by|decided by|approved by|issued by|returned by|reviewed by|created by|updated by|personnel|custodian)\b/.test(text)
            ) {
                return 'report-col-person';
            }

            /*
             * Organization.
             */
            if (
                /\b(division|office|college|unit|department|organizational unit|organization)\b/.test(text)
            ) {
                return 'report-col-organization';
            }

            /*
             * Long descriptive columns.
             */
            if (
                /\b(purpose|description|remarks|reason|details|item summary|items|location|finding|notes|comment|justification)\b/.test(text)
            ) {
                return 'report-col-wrap';
            }

            /*
             * Status / decision fields.
             */
            if (
                /\b(status|decision|verification|outcome|condition|state|result)\b/.test(text)
            ) {
                return 'report-col-status';
            }

            /*
             * Duration / turnaround.
             */
            if (
                /\b(turnaround|duration|elapsed|processing time|response time)\b/.test(text)
            ) {
                return 'report-col-duration';
            }

            /*
             * Dates and timestamps.
             */
            if (
                /\b(date|submitted|verified at|decided at|approved at|issued at|returned at|created at|updated at|due|deadline|period start|period end)\b/.test(text)
            ) {
                return 'report-col-date';
            }

            /*
             * Compact integer/count fields.
             */
            if (
                /\b(version|quantity|qty|count|days|level|offense no|offense number)\b/.test(text)
            ) {
                return 'report-col-compact';
            }

            /*
             * Monetary / numeric columns.
             */
            if (
                /\b(amount|fee|charge|penalty|total|rate|percentage|percent|utilization)\b/.test(text)
            ) {
                return 'report-col-number';
            }

            return '';
        };

        headers.forEach((header, index) => {
            const columnClass =
                classifyHeader(header);

            if (!columnClass) {
                return;
            }

            header.classList.add(columnClass);

            table
                .querySelectorAll(
                    `tbody tr > :nth-child(${index + 1})`
                )
                .forEach((cell) => {
                    cell.classList.add(columnClass);
                });
        });

        table.dataset.cleanReportTable = '1';
    };


    const applyCleanReportTables = () => {
        document
            .querySelectorAll(
                '[data-report-system-table] table'
            )
            .forEach(classifyReportTable);
    };


    /*
     * Existing report code builds/clones the system table dynamically.
     * Apply once immediately...
     */
    applyCleanReportTables();


    /*
     * ...then observe the table host so every report type receives
     * the exact same cleanup after its table is inserted.
     */
    document
        .querySelectorAll('[data-report-system-table]')
        .forEach((host) => {

            const observer =
                new MutationObserver(() => {
                    applyCleanReportTables();
                });

            observer.observe(
                host,
                {
                    childList: true,
                    subtree: true,
                }
            );
        });
})();
</script>
{{-- UNIVERSAL SYSTEM RECORDS TABLE SCRIPT END --}}

{{-- REPORT PAGINATION PER PAGE SCRIPT START --}}
<script>
(() => {
    document
        .querySelectorAll('[data-report-per-page]')
        .forEach((select) => {

            select.addEventListener('change', () => {
                const url =
                    new URL(window.location.href);

                url.searchParams.set(
                    'per_page',
                    select.value
                );

                url.searchParams.delete('page');

                window.location.assign(
                    url.toString()
                );
            });

        });
})();
</script>
{{-- REPORT PAGINATION PER PAGE SCRIPT END --}}
@endsection

<style>
/* ACADEMIC PERIOD EMPTY STATE ICON FINAL */

.report-period-unavailable-icon {
    display: none;
}

.report-no-results.is-period-unavailable
.report-period-unavailable-icon {
    display: grid;
    place-items: center;

    width: 70px;
    height: 70px;

    margin-bottom: 5px;

    color: color-mix(
        in srgb,
        var(--primary-action) 48%,
        var(--text-muted)
    );
}

.report-no-results.is-period-unavailable
.report-period-unavailable-icon svg {
    width: 62px;
    height: 62px;
}

/*
 * When the problem is the academic-period configuration,
 * use the academic/calendar icon instead of the report-type icon.
 */
.report-no-results.is-period-unavailable
.report-no-results-illustration,

.report-no-results.is-period-unavailable
.report-no-results-icon {
    display: none !important;
}

</style>
