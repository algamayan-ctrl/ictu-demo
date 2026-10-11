@extends('layouts.app', ['title' => 'Reports'])

@section('content')
@include('reports.partials.workspace-styles')
@include('reports.partials.detail-styles')

@php
    $selectedType = $filters['type'] ?? '';
    $selectedFrom = $filters['from'] ?? '';
    $selectedTo = $filters['to'] ?? '';
    $selectedSearch = $filters['search'] ?? '';
@endphp

<div class="reporting-workspace reporting-detail all-transactions-page">
    <section class="page-heading">
        <div>
            <p class="eyebrow">Formal reporting</p>
            <h1>Reports</h1>
            <p>Review recorded operational transactions. Choose a specific report for formal Print, PDF, Word and Excel output.</p>
        </div>
    </section>

    <section class="content-area">
        <form class="card all-transactions-filters" id="all-transactions-filters" method="get" action="{{ route('reports.index') }}">
            <div class="all-transactions-filter-row">
                <label class="all-transactions-field">
                    <span>Report Type</span>
                    <select name="report" id="all-transactions-report-type">
                        <option value="all-transactions" selected>All Transactions</option>
                        @foreach(\App\Reports\ReportCatalogue::grouped() as $group => $types)
                            <optgroup label="{{ $group }}">
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <label class="all-transactions-field">
                    <span>Transaction Type</span>
                    <select name="type">
                        <option value="">All transaction types</option>
                        @foreach(\App\Services\AllTransactionsService::TYPES as $type)
                            <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="all-transactions-field">
                    <span>From</span>
                    <input type="date" name="from" value="{{ $selectedFrom }}" aria-label="From date, optional">
                </label>
                <label class="all-transactions-field">
                    <span>To</span>
                    <input type="date" name="to" value="{{ $selectedTo }}" aria-label="To date, optional">
                </label>
                <div class="all-transactions-filter-actions">
                    <button type="submit" class="button primary">Apply Filters</button>
                    <a href="{{ route('reports.index') }}" class="button secondary">Clear</a>
                </div>
            </div>
            <p class="all-transactions-filter-note">All dates and all transaction types are included by default. Each recorded event appears separately.</p>
        </form>
    </section>

    <section class="content-area all-transactions-results">
        <div class="report-preview-bar">
            <div>
                <h2 class="report-preview-title">All Transactions</h2>
                <div class="report-preview-meta">
                    <span>{{ $selectedFrom || $selectedTo ? (($selectedFrom ?: 'Earliest').' – '.($selectedTo ?: 'Latest')) : 'All dates' }}</span>
                    <span>{{ number_format($records->total()) }} {{ $records->total() === 1 ? 'transaction' : 'transactions' }}</span>
                    <span>Newest first</span>
                </div>
            </div>
        </div>

        <section class="report-system-records-card" aria-labelledby="all-transactions-heading">
            <div class="report-system-records-header">
                <div>
                    <h3 id="all-transactions-heading">Transaction Records</h3>
                </div>
                <form method="get" action="{{ route('reports.index') }}" class="report-system-search" role="search">
                    <input type="hidden" name="report" value="all-transactions">
                    @foreach(['type', 'from', 'to'] as $key)
                        @if(! empty($filters[$key]))
                            <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                        @endif
                    @endforeach
                    <span class="report-system-search-icon" aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ $selectedSearch }}" placeholder="Search transactions..." maxlength="100" aria-label="Search transactions">
                </form>
            </div>

            <div class="report-system-table-scroll">
                <table class="report-system-table all-transactions-table">
                    <thead><tr>
                        <th scope="col">Reference No.</th>
                        <th scope="col">Transaction Type</th>
                        <th scope="col">Borrower / Personnel</th>
                        <th scope="col">Office / Unit</th>
                        <th scope="col">Item(s)</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Transaction Date</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr></thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td class="all-transactions-reference-cell"><span class="report-reference-badge" title="{{ $record->reference_no }}">{{ $record->reference_no }}</span></td>
                                <td class="all-transactions-type-cell"><span class="all-transactions-type">{{ $record->transaction_type }}</span></td>
                                <td><strong class="all-transactions-person" title="{{ $record->person_name }}">{{ $record->person_name }}</strong></td>
                                <td><span title="{{ $record->unit_name }}" class="all-transactions-clip all-transactions-unit">{{ $record->unit_name }}</span></td>
                                <td><span title="{{ $record->items_text }}" class="all-transactions-clip all-transactions-items">{{ $record->items_text }}</span></td>
                                <td class="numeric all-transactions-quantity">{{ $record->quantity === null ? '—' : rtrim(rtrim(number_format((float) $record->quantity, 3, '.', ','), '0'), '.') }}</td>
                                <td class="all-transactions-date">{{ $record->date_label }}</td>
                                <td class="all-transactions-status-cell"><span class="status-badge status-neutral" title="{{ $record->status_label }}">{{ $record->status_label }}</span></td>
                                <td class="report-system-action-column">
                                    @if($record->can_view)
                                        <a class="report-inventory-view-action" href="{{ $record->view_url }}" aria-label="View Record: {{ $record->reference_no }}" title="View Record">
                                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2.2 12s3.6-6 9.8-6 9.8 6 9.8 6-3.6 6-9.8 6-9.8-6-9.8-6Z"/><circle cx="12" cy="12" r="2.8"/></svg>View Record</a>
                                    @elseif($record->request_id)
                                        <span class="report-record-restricted" title="This record cannot be opened under your current permissions.">Not available</span>
                                    @else
                                        <span aria-label="No record detail link">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="report-system-table-empty">No recorded transactions matched the selected criteria.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="report-records-footer" aria-label="Transaction navigation">
                <p class="report-records-summary">
                    @if($records->total())
                        Showing {{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }} of {{ number_format($records->total()) }} transactions
                    @else
                        Showing 0 transactions
                    @endif
                </p>
                <div class="report-records-controls">
                    <form method="get" action="{{ route('reports.index') }}" class="report-per-page-control">
                        <input type="hidden" name="report" value="all-transactions">
                        @foreach($filters as $key => $value)
                            @if($value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                        @endforeach
                        <select name="per_page" aria-label="Transactions per page" onchange="this.form.requestSubmit()">
                            @foreach([10,25,50,100] as $size)
                                <option value="{{ $size }}" @selected($records->perPage() === $size)>{{ $size }} per page</option>
                            @endforeach
                        </select>
                    </form>
                    @if($records->hasPages())
                        {{ $records->onEachSide(1)->links('reports.partials.report-pagination') }}
                    @endif
                </div>
            </div>
        </section>
        <p class="report-boundary-note">
            <x-icon name="information" size="18" />
            <span>This is an operational event overview, not an official document. Select one of the specific report types above to Print or Export a formal CSPC-SPMU report.</span>
        </p>
    </section>
</div>

<script>
(() => {
    const select = document.getElementById('all-transactions-report-type');
    select?.addEventListener('change', () => {
        if (select.value === 'all-transactions') return;
        const url = new URL(@json(route('reports.index')), window.location.origin);
        url.searchParams.set('report', select.value);
        window.location.assign(url.toString());
    });
})();
</script>

<style>
.all-transactions-page .all-transactions-filters { padding: 15px 17px; }
.all-transactions-filter-row { display: grid; grid-template-columns: minmax(190px, 1.5fr) minmax(175px, 1.15fr) minmax(125px, .8fr) minmax(125px, .8fr) auto; gap: 12px; align-items: end; }
.all-transactions-field { display: grid; gap: 7px; min-width: 0; color: var(--heading); font-size: 11px; font-weight: 650; }
.all-transactions-page .all-transactions-field input { background: var(--surface-elevated); color: var(--heading); border: 1px solid var(--border); border-radius: 7px; height: 42px; padding: 8px; width: 100%; font: inherit; }
.all-transactions-page .all-transactions-field select { height: 42px; border-radius: 7px; }
.all-transactions-filter-actions { display: flex; gap: 7px; align-items: center; }
.all-transactions-filter-note { margin: 9px 0 0; color: var(--text-muted); font-size: 11px; }
.all-transactions-results { margin-top: 16px !important; }
.all-transactions-page .report-system-records-card { margin-top: 8px; }
.all-transactions-page .report-system-table th { white-space: nowrap; min-width: 120px; }
.all-transactions-page .report-system-table td { white-space: nowrap; padding: 11px 12px; line-height: 1.35; }
.all-transactions-page .all-transactions-clip { display: inline-block; max-width: 175px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; vertical-align: middle; }
.all-transactions-page .report-system-table td:nth-child(1) { min-width: 158px; }
.all-transactions-page .report-system-table td:nth-child(5) { min-width: 160px; }
.all-transactions-page .report-preview-title { font-size: 19px; }
@media (max-width: 990px) { .all-transactions-filter-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 590px) { .all-transactions-filter-row { grid-template-columns: 1fr; } .all-transactions-filter-actions { justify-content: flex-start; } }
</style>
@endsection
