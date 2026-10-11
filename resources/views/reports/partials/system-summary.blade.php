@php
    /*
    | KPI cards for a report preview.
    |
    | `$cards` is label => already-formatted value. Every figure comes from the
    | same record set as the table below and the generated document, and it
    | always describes the whole report scope, never just a search result.
    */
@endphp

@if(! empty($cards))
    <div class="report-system-summary-grid" aria-label="Report summary values" role="list">
        @foreach($cards as $label => $value)
            <article class="report-system-summary-card" role="listitem">
                <div>
                    <span class="report-system-summary-label">{{ $label }}</span>
                    <strong class="report-system-summary-value">{{ $value }}</strong>
                </div>
            </article>
        @endforeach
    </div>
@endif
