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
            <p>Preview official operational reports, then print or export the exact records you reviewed.</p>
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
                        <p>Nothing was exported. Try again, or change the reporting period or filters. If the problem continues, contact ICTU.</p>
                        <div class="report-no-results-actions">
                            <a class="button primary ui-pressable" href="{{ request()->fullUrl() }}">Try Again</a>
                            <a class="button secondary ui-pressable" href="#report-builder" data-focus-report-builder>Change Period or Filters</a>
                        </div>
                    </div>
                </section>
            @else
                <div class="report-preview-bar">
                    <div>
                        <p class="report-preview-label">Report preview</p>
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
                    @include('reports.partials.preview-empty')
                @endif
            @endif

            @if($reportCount > 0 && ! $previewFailed)
                <p class="report-boundary-note">
                    <x-icon name="information" size="18" />
                    <span>
                        All reports are based on official operational records.
                        For analysis, insights, and forecasting, use the Analytics module.
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
    /*
     * Live report preview.
     *
     * The records load with the page. When the builder's period or filters
     * change, or the table is searched, sorted or paged, only the live
     * regions are fetched and replaced: one request at a time, the newest
     * wins, and the address bar keeps the state so a reload, Print and the
     * exports all describe the records on screen.
     *
     * A new report type also replaces the builder, because its filters are
     * its own. The builder is therefore looked up when needed and its events
     * are heard on the page, never on a form that may have been replaced.
     */
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

    /* Inline scripts do not run when inserted as markup, so they are re-created. */
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
                /* The builder keeps its controls (and the operator's focus) unless the report type changed. */
                if (region.dataset.reportLive === 'builder' && !rebuild) return;
                /* A region inside a builder that was just replaced is already current. */
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
            /* Anything unexpected (expired session, server error) falls back to a normal page load. */
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

    /* The builder's scope as a Reports URL; blank filters are left out. */
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

    /* Several controls can change together (Clear all, cascading filters): refresh once. */
    const schedule = ({ force = false } = {}) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            if (validRange()) load(builderUrl(), { force });
        }, force ? 0 : 200);
    };

    page.addEventListener('change', (event) => {
        if (!event.target.closest('[data-report-preview-form]')) return;
        /* Report Type announces itself below; the picker's search box is not a filter. */
        if (event.target.matches('[data-report-type], [data-borrower-search]')) return;
        schedule();
    });
    page.addEventListener('report:scope-change', () => schedule());
    page.addEventListener('report:refresh', () => schedule({ force: true }));
    page.addEventListener('report:type-change', (event) => {
        window.clearTimeout(timer);
        load(event.detail.url, { rebuild: true });
    });

    /* Table navigation stays on the page. */
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

    /* "Change Period or Filters" returns focus to the builder. */
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
@endsection
