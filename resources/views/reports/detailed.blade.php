@extends('layouts.app', ['title' => 'Reports'])

@section('content')
@include('reports.partials.workspace-styles')
@include('reports.partials.detail-styles')

@php
    $reportCount = ($showPreview && $dataset !== null) ? $dataset->count() : 0;

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
    $appliedFilters = ($showPreview && $dataset !== null) ? ($dataset->meta['applied_filters'] ?? []) : [];
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

@php
    /*
     * Summary cards describe the whole report scope, exactly as the builder
     * produced it; the generated document carries the same figures.
     */
    $summaryCards = [];

    if ($showPreview && $dataset !== null) {
        foreach ($dataset->summary as $label => $value) {
            $summaryCards[(string) $label] = \App\Reports\ReportSummaryFormatter::display((string) $label, $value);
        }

        if ($summaryCards === []) {
            $summaryCards['Total records'] = number_format($dataset->count());
        }
    }

    $periodText = $showPreview && $dataset !== null && \App\Reports\ReportCatalogue::periodMode($selectedReport) === 'as_of'
        ? 'As of '.($dataset->meta['as_of_long'] ?? $to->format('d F Y'))
        : $from->format('d M Y').' – '.$to->format('d M Y');

    /* Print and export receive the report scope only — never the table's search or sort. */
    $printUrl = route('reports.print', array_merge(['type' => $selectedReport], $reportFilters->toQuery()));
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
        {{-- Rebuilt in place only when the report type changes: each report has its own filters. --}}
        <div data-report-live="builder" style="display: contents;">
            @include('reports.partials.report-builder')
        </div>

        <section class="content-area report-results" data-report-results aria-live="polite">
            <div class="report-results-loading" data-report-results-loading hidden role="status">
                <span class="report-results-spinner" aria-hidden="true"></span>
                <span>Loading report records…</span>
            </div>

            {{-- Replaced in place whenever the builder or the table asks for new records. --}}
            <div data-report-live="results">
            @if($previewFailed)
                <section class="report-no-results report-preview-error" role="alert" aria-labelledby="report-preview-error-title">
                    <span class="report-no-results-icon" aria-hidden="true"><x-icon name="warning" size="20" /></span>
                    <div>
                        <p class="report-preview-label">Report preview</p>
                        <h3 id="report-preview-error-title">The report could not be generated.</h3>
                        <p>Nothing was exported. Try again, or change the reporting period or filters. If the problem continues, contact the SPMU Admin.</p>
                        <div class="report-no-results-actions">
                            <a class="button primary ui-pressable" href="{{ request()->fullUrl() }}">Try Again</a>
                            <a class="button secondary ui-pressable" href="#report-builder" data-focus-report-builder>Change Period or Filters</a>
                        </div>
                    </div>
                </section>
            @else
                <div class="report-preview-bar">
                    <div>
                        <p class="report-preview-label">Report records</p>
                        <h2 class="report-preview-title">{{ $selectedReportMeta['label'] }}</h2>
                        <div class="report-preview-meta">
                            <span>
                                @if($reportPeriodUnavailable)
                                    Reporting period unavailable
                                @else
                                    {{ $periodText }}
                                @endif
                            </span>
                            <span>{{ number_format($reportCount) }} {{ $reportCount === 1 ? 'record' : 'records' }}</span>
                        </div>

                        @if(! empty($scopeRows))
                            <p class="report-preview-filters" aria-label="Selected report scope">
                                @foreach($scopeRows as $scope)
                                    <span><strong>{{ $scope['label'] }}:</strong> {{ $scope['value'] }}</span>
                                @endforeach
                            </p>
                        @endif
                    </div>

                    @if($reportCount > 0)
                        <div class="report-preview-actions">
                            <a class="button secondary ui-pressable" href="{{ $printUrl }}" target="_blank" rel="noopener">
                                <x-icon name="report-document" size="16" />
                                Print
                            </a>
                            <button class="button primary ui-pressable" type="button" data-open-export-options>
                                <x-icon name="upload" size="16" />
                                Generate Report
                            </button>
                        </div>
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
                    <div class="report-system-preview" data-report-system-preview data-report-count="{{ $reportCount }}">


                        @include('reports.partials.system-records', [
                            'table' => $table,
                            'heading' => $selectedReportMeta['label'].' Records',
                        ])

                        <p class="report-output-note">
                            <x-icon name="information" size="16" />
                            <span>
                                Print and every export contain all {{ number_format($reportCount) }} {{ $reportCount === 1 ? 'record' : 'records' }} in this report scope.
                                PDF, Word and Print apply the formal CSPC-SPMU document layout; Excel keeps a structured sheet and CSV contains the data only.
                            </span>
                        </p>
                    </div>
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
            @endif

            @if($reportCount > 0 && ! $previewFailed)
                <p class="report-boundary-note">
                    <x-icon name="information" size="18" />
                    <span>
                        All reports are based on official operational records.
                        For descriptive analysis and scheduled demand, use the Analytics module.
                    </span>
                </p>

                {{-- The export dialog carries the scope on screen, so it is refreshed with the records. --}}
                @include('reports.partials.export-options')
                @include('reports.partials.export-options-script')
            @endif
            </div>
        </section>
    </div>
</div>

<script>
(() => {













    const page = document.querySelector('.reports-detail-page');
    const results = document.querySelector('[data-report-results]');
    const loading = results?.querySelector('[data-report-results-loading]');
    const builderForm = () => document.querySelector('[data-report-preview-form]');

    if (!page || !results || !builderForm()) return;

    const normalise = (url) => {
        const parsed = new URL(url, window.location.href);
        return parsed.pathname + parsed.search;
    };

    let currentUrl = normalise(window.location.href);
    let pendingUrl = null;
    let controller = null;
    let timer = null;

    const setBusy = (busy) => {
        results.classList.toggle('is-loading', busy);
        if (busy) results.setAttribute('aria-busy', 'true');
        else results.removeAttribute('aria-busy');
        if (loading) loading.hidden = !busy;
    };


    const runScripts = (region) => {
        region.querySelectorAll('script').forEach((stale) => {
            const fresh = document.createElement('script');
            fresh.textContent = stale.textContent;
            stale.replaceWith(fresh);
        });
    };

    const load = async (url, { force = false, rebuild = false } = {}) => {
        const target = normalise(url);

        if (!force && target === (pendingUrl ?? currentUrl)) return;

        controller?.abort();
        controller = new AbortController();
        const request = controller;
        pendingUrl = target;
        setBusy(true);

        try {
            const response = await fetch(target, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
                signal: request.signal,
            });

            if (!response.ok) throw new Error(`Report preview request failed (${response.status})`);

            const fetched = new DOMParser().parseFromString(await response.text(), 'text/html');

            if (!fetched.querySelector('[data-report-live="results"]')) {
                throw new Error('Report preview response had no records region');
            }

            if (rebuild && !fetched.querySelector('[data-report-live="builder"]')) {
                throw new Error('Report preview response had no builder region');
            }

            document.querySelectorAll('[data-report-live]').forEach((region) => {

                if (region.dataset.reportLive === 'builder' && !rebuild) return;

                if (!region.isConnected) return;
                const next = fetched.querySelector(`[data-report-live="${region.dataset.reportLive}"]`);
                if (!next) return;
                region.replaceChildren(...Array.from(next.childNodes).map((node) => document.importNode(node, true)));
                runScripts(region);
            });

            currentUrl = target;
            window.history.replaceState(window.history.state, '', target);
            if (rebuild) document.querySelector('[data-report-type]')?.focus({ preventScroll: true });
        } catch (error) {
            if (error.name === 'AbortError') return;

            window.location.assign(target);
            return;
        } finally {
            if (controller === request) {
                controller = null;
                pendingUrl = null;
                setBusy(false);
            }
        }
    };


    const builderUrl = () => {
        const form = builderForm();
        const params = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '') params.set(key, value);
        });
        if (params.get('academic_period') !== 'custom') {
            params.delete('start_date');
            params.delete('end_date');
        }
        params.set('generated', '1');

        return `${window.location.pathname}?${params.toString()}`;
    };

    const validRange = () => {
        const form = builderForm();
        if (form.elements.academic_period?.value !== 'custom') return true;
        const start = form.elements.start_date?.value || '';
        const end = form.elements.end_date?.value || '';
        return /^\d{4}-\d{2}-\d{2}$/.test(start) && /^\d{4}-\d{2}-\d{2}$/.test(end) && end >= start;
    };


    const schedule = ({ force = false } = {}) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            if (validRange()) load(builderUrl(), { force });
        }, force ? 0 : 200);
    };

    page.addEventListener('change', (event) => {
        if (!event.target.closest('[data-report-preview-form]')) return;

        if (event.target.matches('[data-report-type], [data-borrower-search]')) return;
        schedule();
    });
    page.addEventListener('report:scope-change', () => schedule());
    page.addEventListener('report:refresh', () => schedule({ force: true }));
    page.addEventListener('report:type-change', (event) => {
        window.clearTimeout(timer);
        load(event.detail.url, { rebuild: true });
    });


    results.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-report-table-nav], .report-pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        load(link.href);
    });

    results.addEventListener('submit', (event) => {
        const tableForm = event.target.closest('form[data-report-table-nav]');
        if (!tableForm) return;
        event.preventDefault();
        const params = new URLSearchParams();
        new FormData(tableForm).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '') params.set(key, value);
        });
        load(`${tableForm.getAttribute('action') || window.location.pathname}?${params.toString()}`);
    });


    results.addEventListener('click', (event) => {
        if (!event.target.closest('[data-focus-report-builder]')) return;
        const builder = document.getElementById('report-builder');
        if (!builder) return;
        event.preventDefault();
        builder.scrollIntoView({ behavior: 'smooth', block: 'start' });
        builder.querySelector('select, input, button')?.focus({ preventScroll: true });
    });
})();
</script>

<style>
{{-- /* ACADEMIC PERIOD EMPTY STATE ICON FINAL */ --}}

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

{{-- /*
 * When the problem is the academic-period configuration,
 * use the academic/calendar icon instead of the report-type icon.
 */ --}}
.report-no-results.is-period-unavailable
.report-no-results-illustration,

.report-no-results.is-period-unavailable
.report-no-results-icon {
    display: none !important;
}

</style>
@endsection
