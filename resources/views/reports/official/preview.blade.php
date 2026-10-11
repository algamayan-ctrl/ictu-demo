@extends('layouts.app', ['title' => 'Official Report Preview'])

@section('content')

@include('reports.partials.workspace-styles')
@include('reports.partials.detail-styles')

@php
    /*
    | System preview of the official Summary of Requests for Equipment.
    |
    | This screen shows the actual records in the application's own design.
    | The institutional layout of the Active form revision — headings, form
    | code, physical-signature spaces, page footer — exists only in the
    | generated document (Print, PDF, Word, Excel). CSV carries the data only.
    */
    $backUrl = $back ?? route('reports.index', [
        'academic_period' => 'custom',
        'start_date' => $from->toDateString(),
        'end_date' => $to->toDateString(),
    ]);

    $template = $report['template'] ?? null;
    $exportUrl = fn (string $format): string => route('reports.official.equipment.export', ['format' => $format] + $exportQuery);

    $summaryCards = $report === null ? [] : [
        'Total requests' => number_format($report['total']),
        'Released' => number_format($report['released_count']),
        'Not yet released' => number_format($report['total'] - $report['released_count']),
        'On-time response' => 'Not evaluated',
    ];
@endphp

<div class="reporting-workspace reporting-detail">
    <section class="page-heading">
        <div>
            <p class="eyebrow">Formal reporting · Institutional form</p>
            <h1>Summary of Requests for Equipment</h1>
            <p>Review the actual request records first. The official CSPC-SPMU form layout is applied only when you print or export.</p>
        </div>
        <div class="reporting-heading-actions">
            <a class="button secondary ui-pressable" href="{{ $backUrl }}">
                <x-icon name="arrow-left" size="16" />
                Back to Reports
            </a>
        </div>
    </section>

    <div class="reports-detail-page">
        <form method="get" action="{{ route('reports.official.equipment') }}" class="card report-builder-card official-period-card" id="report-builder" data-report-preview-form>
            @if($back)
                <input type="hidden" name="back" value="{{ $back }}">
            @endif
            <div class="official-period-grid">
                <label class="report-builder-field" for="official-from">
                    <span>From</span>
                    <input id="official-from" type="date" name="from" value="{{ old('from', $from->toDateString()) }}" required>
                </label>
                <label class="report-builder-field" for="official-to">
                    <span>To</span>
                    <input id="official-to" type="date" name="to" value="{{ old('to', $to->toDateString()) }}" required>
                </label>
                <div class="report-builder-field report-builder-submit">
                    <span aria-hidden="true"></span>
                    <button class="button primary report-generate-button" type="submit">
                        <x-icon name="report-document" size="17" />
                        <span>Preview Report</span>
                    </button>
                </div>
            </div>
            @if($errors->has('from') || $errors->has('to'))
                <p class="report-filter-warning" role="alert">
                    <x-icon name="warning" size="16" />
                    <span>{{ $errors->first('to') ?: $errors->first('from') }}</span>
                </p>
            @endif
            <p class="report-period-helper">The reporting period printed on the official form is taken from these dates.</p>
        </form>

        <section class="content-area report-results" data-report-results aria-live="polite">
            <div class="report-results-loading" data-report-results-loading hidden role="status">
                <span class="report-results-spinner" aria-hidden="true"></span>
                <span>Loading report records…</span>
            </div>

            @if($failure === 'no_active_revision')
                <section class="report-no-results report-preview-error" role="alert" aria-labelledby="official-error-title">
                    <span class="report-no-results-icon" aria-hidden="true"><x-icon name="warning" size="20" /></span>
                    <div>
                        <p class="report-preview-label">Report preview</p>
                        <h3 id="official-error-title">No Active form revision.</h3>
                        <p>The official form can only be generated from an Active Report Form Configuration revision. Activate a reviewed Draft first.</p>
                        <div class="report-no-results-actions">
                            <a class="button secondary ui-pressable" href="{{ route('policies.report-forms.index') }}">Open Report Form Configuration</a>
                        </div>
                    </div>
                </section>
            @elseif($failure !== null)
                <section class="report-no-results report-preview-error" role="alert" aria-labelledby="official-error-title">
                    <span class="report-no-results-icon" aria-hidden="true"><x-icon name="warning" size="20" /></span>
                    <div>
                        <p class="report-preview-label">Report preview</p>
                        <h3 id="official-error-title">The report could not be generated.</h3>
                        <p>Nothing was exported. Try again, or choose a different period. If the problem continues, contact ICTU.</p>
                        <div class="report-no-results-actions">
                            <a class="button primary ui-pressable" href="{{ request()->fullUrl() }}">Try Again</a>
                        </div>
                    </div>
                </section>
            @else
                <div class="report-preview-bar">
                    <div>
                        <p class="report-preview-label">Report preview</p>
                        <h2 class="report-preview-title">{{ $report['title'] }}</h2>
                        <div class="report-preview-meta">
                            <span>{{ $report['coverage'] }}</span>
                            <span>{{ number_format($report['total']) }} {{ $report['total'] === 1 ? 'request' : 'requests' }}</span>
                        </div>
                    </div>

                    <div class="report-preview-actions">
                        <a class="button secondary ui-pressable" href="{{ $exportUrl('print') }}" target="_blank" rel="noopener">
                            <x-icon name="report-document" size="16" />
                            Print Official Form
                        </a>
                        <button class="button primary ui-pressable" type="button" data-open-official-export>
                            <x-icon name="upload" size="16" />
                            Generate Official Report
                        </button>
                    </div>
                </div>

                <div class="report-system-preview">
                    @include('reports.partials.system-summary', ['cards' => $summaryCards])

                    @if($report['total'] > 0)
                        @include('reports.partials.system-records', [
                            'table' => $table,
                            'heading' => 'Submitted Equipment Requests',
                            'recordNoun' => 'requests',
                        ])
                    @else
                        <section class="report-no-results" aria-labelledby="official-empty-title">
                            <span class="report-no-results-icon" aria-hidden="true"><x-icon name="report-document" size="20" /></span>
                            <div>
                                <p class="report-preview-label">No matching records</p>
                                <h3 id="official-empty-title">No submitted equipment requests in this period.</h3>
                                <p>The official form can still be generated; it will state that no requests were recorded for {{ $report['coverage'] }}.</p>
                            </div>
                        </section>
                    @endif

                    <p class="report-output-note">
                        <x-icon name="information" size="16" />
                        <span>
                            Print, PDF and Word use the Active form revision
                            <strong>{{ $template->form_code }} · {{ $template->revision_label }}</strong>@if($template->effectivity_date), effective {{ $template->effectivity_date->format('F Y') }}@endif,
                            with its approved headings, layout and physical-signature spaces. Excel and CSV are data exports that identify the report, period and form revision.
                            Every output contains all {{ number_format($report['total']) }} {{ $report['total'] === 1 ? 'request' : 'requests' }} above.
                        </span>
                    </p>
                </div>
            @endif
        </section>
    </div>
</div>

@if($report !== null)
    <dialog class="report-options-dialog" id="official-export-options" aria-labelledby="official-export-title">
        <header class="report-options-header">
            <div>
                <p class="report-options-eyebrow">Report Options</p>
                <h2 id="official-export-title">Generate Official Report</h2>
            </div>
            <button class="icon-button" type="button" aria-label="Close export options" data-official-export-close>
                <x-icon name="close" size="18" />
            </button>
        </header>

        <div class="report-options-body">
            <p class="report-options-scope">
                <span>{{ $report['title'] }}</span>
                <small>{{ $report['coverage'] }} · {{ number_format($report['total']) }} {{ $report['total'] === 1 ? 'request' : 'requests' }} · {{ $template->form_code }} {{ $template->revision_label }}</small>
            </p>

            <ul class="official-format-list">
                @foreach([
                    'pdf' => ['Export PDF', 'Official form layout with the CSPC header, form code, signature spaces and page footer.'],
                    'docx' => ['Export Word', 'The same official layout as an editable Word document.'],
                    'xlsx' => ['Export Excel', 'Data workbook: identifying rows (title, period, form revision), one filterable header row and the records. No letterhead or signatures.'],
                    'csv' => ['Export CSV', 'Data file: identifying lines, one header row and the records. No institutional layout.'],
                    'print' => ['Open Print Preview', 'Opens the official form in a new tab ready to print.'],
                ] as $format => [$label, $help])
                    <li>
                        <a class="official-format-option ui-pressable" href="{{ $exportUrl($format) }}" @if($format === 'print') target="_blank" rel="noopener" @endif data-official-format="{{ $format }}">
                            <strong>{{ $label }}</strong>
                            <span>{{ $help }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <footer class="report-options-footer">
            <button class="button secondary ui-pressable" type="button" data-official-export-close>Cancel</button>
        </footer>
    </dialog>
@endif

<style>
.official-period-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; gap: 18px; align-items: start; }
.official-period-grid input[type="date"] { min-height: 42px; width: 100%; font-size: 13px; }
.official-period-card .report-filter-warning { margin: 12px 0 0; }
.official-format-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.official-format-option { display: grid; gap: 3px; padding: 11px 13px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface-elevated); color: var(--text); text-decoration: none; }
.official-format-option strong { color: var(--heading); font-size: 13px; }
.official-format-option span { color: var(--text-muted); font-size: 11.5px; line-height: 1.4; }
.official-format-option:hover, .official-format-option:focus-visible { border-color: var(--primary-action); background: color-mix(in srgb, var(--info-bg) 40%, var(--surface-elevated)); }
@media (max-width: 620px) {
    .official-period-grid { grid-template-columns: minmax(0, 1fr); }
    .official-period-grid .report-builder-submit > span { display: none; }
}
</style>

<script>
(() => {
    const form = document.querySelector('[data-report-preview-form]');
    const results = document.querySelector('[data-report-results]');
    const loading = results?.querySelector('[data-report-results-loading]');

    form?.addEventListener('submit', () => {
        if (!results) return;
        results.setAttribute('aria-busy', 'true');
        results.classList.add('is-loading');
        if (loading) loading.hidden = false;
    });

    const dialog = document.getElementById('official-export-options');
    if (!dialog) return;

    document.querySelectorAll('[data-open-official-export]').forEach((trigger) => {
        trigger.addEventListener('click', () => dialog.showModal());
    });
    dialog.querySelectorAll('[data-official-export-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    /* A download keeps this page; close the dialog so the preview stays in view. */
    dialog.querySelectorAll('[data-official-format]').forEach((link) => {
        link.addEventListener('click', () => window.setTimeout(() => dialog.close(), 150));
    });
})();
</script>
@endsection
