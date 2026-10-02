@extends('layouts.app', ['title' => 'Analytics Records'])

@section('content')
@include('reports.partials.workspace-styles')

@php
    $table = $detail['table'] ?? null;
    $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
    $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
    $bars = is_array($detail['bars'] ?? null) ? $detail['bars'] : [];

    $emptyIcon = match ($detailKey) {
        'requests', 'trend', 'outcomes' => 'requests',
        'currently-out', 'equipment', 'low-availability', 'coverage' => 'inventory',
        'returns', 'follow-up', 'overdue-aging', 'late-rate' => 'returns',
        'division', 'unit' => 'organization',
        'borrower' => 'borrower',
        'forecast' => 'forecast',
        default => 'records',
    };

    $emptyTitle = match ($detailKey) {
        'requests' => 'No request records yet.',
        'currently-out' => 'No active custody records.',
        'follow-up' => 'No follow-up records.',
        'low-availability' => 'No low-availability records.',
        'equipment', 'coverage' => 'No asset records yet.',
        'returns', 'late-rate' => 'No return records yet.',
        'overdue-aging' => 'No overdue records yet.',
        'division', 'unit' => 'No organizational records yet.',
        'borrower' => 'No borrower records yet.',
        'forecast' => 'No forecast data yet.',
        'outcomes' => 'No request outcome records yet.',
        'trend' => 'No activity records yet.',
        default => 'No records yet.',
    };
@endphp

<style>
.analytics-records-page { display: grid; gap: 18px; }
.analytics-records-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
.analytics-records-heading h1 { margin: 3px 0 5px; font-size: 25px; color: var(--heading); }
.analytics-records-heading p { margin: 0; color: var(--text-muted); font-size: 12px; line-height: 1.55; }
.analytics-records-eyebrow { margin: 0 !important; color: var(--text-muted) !important; font-size: 10px !important; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.analytics-records-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.analytics-records-context { display: flex; flex-wrap: wrap; gap: 8px; }
.analytics-records-chip { display: inline-flex; align-items: center; min-height: 30px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface-elevated); color: var(--text-secondary); font-size: 11px; font-weight: 650; }
.analytics-records-card { overflow: hidden; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-elevated); }
.analytics-records-card-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 17px 18px; border-bottom: 1px solid var(--border); }
.analytics-records-card-head h2 { margin: 0; color: var(--heading); font-size: 15px; }
.analytics-records-search { width: min(300px, 100%); min-height: 38px; }
.analytics-records-table-wrap { overflow-x: auto; }
.analytics-records-table { width: 100%; min-width: 880px; border-collapse: collapse; }
.analytics-records-table th { padding: 12px 13px; background: var(--surface-subtle); border-bottom: 1px solid var(--border); color: var(--text-secondary); font-size: 10px; font-weight: 800; letter-spacing: .05em; text-align: left; text-transform: uppercase; white-space: nowrap; }
.analytics-records-table td { padding: 12px 13px; border-bottom: 1px solid var(--border); color: var(--heading); font-size: 12px; line-height: 1.5; vertical-align: top; }
.analytics-records-table tr:last-child td { border-bottom: 0; }
.analytics-records-table tbody tr[hidden] { display: none; }
.analytics-records-footer { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 18px; border-top: 1px solid var(--border); color: var(--text-muted); font-size: 11px; }
.analytics-records-empty {
    display: grid;
    place-items: center;
    min-height: 270px;
    padding: 34px 24px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface-elevated);
    text-align: center;
}

.analytics-records-empty-inner {
    display: grid;
    justify-items: center;
    gap: 9px;
    width: min(440px, 100%);
}

.analytics-records-empty-icon {
    display: grid;
    place-items: center;
    width: 66px;
    height: 66px;
    margin-bottom: 4px;
    color: color-mix(in srgb, var(--primary-action) 56%, var(--text-muted));
}

.analytics-records-empty-icon svg {
    width: 58px;
    height: 58px;
    overflow: visible;
}

.analytics-records-empty-icon .empty-icon-soft {
    fill: color-mix(in srgb, var(--primary-action) 8%, transparent);
    stroke: none;
}

.analytics-records-empty-icon .empty-icon-line {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.65;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.analytics-records-empty-sparkle {
    fill: color-mix(in srgb, var(--primary-action) 26%, transparent);
    stroke: none;
}

.analytics-records-empty h2 {
    margin: 0;
    color: var(--heading);
    font-size: 15px;
    font-weight: 750;
    line-height: 1.35;
}

.analytics-records-empty p {
    margin: 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.55;
}
.analytics-records-breakdown { width: 100%; border-collapse: collapse; }
.analytics-records-breakdown td { padding: 11px 13px; border-bottom: 1px solid var(--border); font-size: 12px; }
.analytics-records-breakdown tr:last-child td { border-bottom: 0; }
.analytics-records-breakdown td:last-child { text-align: right; color: var(--heading); font-weight: 700; }
@media (max-width: 700px) {
    .analytics-records-heading { display: grid; }
    .analytics-records-actions { width: 100%; }
    .analytics-records-actions .button { flex: 1; justify-content: center; }
}
</style>

<div class="reporting-workspace analytics-records-page">
    <section class="analytics-records-heading">
        <div>
            <p class="analytics-records-eyebrow">Analytics records</p>
            <h1>{{ $detail['title'] ?? 'Analytics Records' }}</h1>
            <p>{{ $detail['context'] ?? 'Records supporting the selected analytics result.' }}</p>
        </div>

        <div class="analytics-records-actions">
            <a class="button secondary ui-pressable" href="{{ $backToAnalyticsUrl }}">
                Back to Analytics
            </a>

            @if(! empty($detail['reports_url']))
                <a class="button secondary ui-pressable" href="{{ $detail['reports_url'] }}">
                    Generate Formal Report
                </a>
            @endif
        </div>
    </section>

    <div class="analytics-records-context">
        <span class="analytics-records-chip">{{ $detail['period_label'] ?? ($from->format('d M Y').' – '.$to->format('d M Y')) }}</span>
        @if(! empty($detail['filters']))
            <span class="analytics-records-chip">{{ $detail['filters'] }}</span>
        @endif
        @if(array_key_exists('value', $detail) && $detail['value'] !== null)
            <span class="analytics-records-chip">
                @if($detailKey === 'requests' && empty($detail['value_label']))
                    {{ $detail['value'] }} {{ (int) $detail['value'] === 1 ? 'request' : 'requests' }}
                @else
                    {{ $detail['value'] }}{{ ! empty($detail['value_label']) ? ' · '.$detail['value_label'] : '' }}
                @endif
            </span>
        @endif
    </div>

    @if($table && $rows !== [])
        <section class="analytics-records-card">
            <div class="analytics-records-card-head">
                <div>
                    <p class="analytics-records-eyebrow">Supporting records</p>
                    <h2>{{ $detail['title'] ?? 'Analytics' }} Records</h2>
                </div>

                <input
                    class="analytics-records-search"
                    type="search"
                    placeholder="Search records"
                    aria-label="Search analytics records"
                    data-analytics-record-search
                >
            </div>

            <div class="analytics-records-table-wrap">
                <table class="analytics-records-table" data-analytics-record-table>
                    <thead>
                        <tr>
                            @foreach($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr data-analytics-record-row>
                                @foreach($row as $cell)
                                    <td>{{ is_scalar($cell) || $cell === null ? ($cell ?? '—') : json_encode($cell) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="analytics-records-footer">
                <span><strong data-analytics-visible-count>{{ count($rows) }}</strong> visible</span>
                <span>{{ count($rows) }} supporting {{ count($rows) === 1 ? 'record' : 'records' }} loaded</span>
            </div>
        </section>
    @elseif($bars !== [])
        <section class="analytics-records-card">
            <div class="analytics-records-card-head">
                <div>
                    <p class="analytics-records-eyebrow">Analytics breakdown</p>
                    <h2>This result is an aggregate, not one record type</h2>
                </div>
            </div>

            <table class="analytics-records-breakdown">
                <tbody>
                    @foreach($bars as $bar)
                        <tr>
                            <td>{{ $bar['label'] ?? 'Breakdown' }}</td>
                            <td>{{ $bar['value'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @else
        <section class="analytics-records-empty" aria-labelledby="analytics-empty-title">
            <div class="analytics-records-empty-inner">

                <span class="analytics-records-empty-icon" aria-hidden="true">
                    @switch($emptyIcon)

                        @case('requests')
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M15 10h27l9 9v32H15z"/>
                                <path class="empty-icon-line" d="M16 8h25l9 9v35H16zM41 8v10h9M23 26h20M23 33h20M23 40h13"/>
                                <path class="empty-icon-line" d="M44 44l7 7M40.5 40.5a6 6 0 1 0 0 .1"/>
                                <path class="analytics-records-empty-sparkle" d="M10 18l1.4 3.1L14.5 22l-3.1 1.4L10 26.5l-1.4-3.1L5.5 22l3.1-.9zM53 26l1 2.2 2.2 1-2.2 1-1 2.2-1-2.2-2.2-1 2.2-1z"/>
                            </svg>
                            @break

                        @case('inventory')
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M13 22l19-11 19 11v22L32 55 13 44z"/>
                                <path class="empty-icon-line" d="M12 21l20-11 20 11v22L32 54 12 43zM12 21l20 11 20-11M32 32v22M22 16l20 11"/>
                                <circle class="empty-icon-line" cx="47" cy="46" r="7"/>
                                <path class="empty-icon-line" d="M52 51l5 5"/>
                            </svg>
                            @break

                        @case('returns')
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M18 13h29v39H18z"/>
                                <path class="empty-icon-line" d="M18 10h26l7 7v35H18zM44 10v8h7M24 29h20M24 36h15"/>
                                <path class="empty-icon-line" d="M14 45h19M14 45l5-5M14 45l5 5"/>
                            </svg>
                            @break

                        @case('organization')
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M13 23h38v30H13z"/>
                                <path class="empty-icon-line" d="M11 53h42M16 53V24h32v29M22 31h5M37 31h5M22 38h5M37 38h5M29 53V43h6v10M13 24l19-12 19 12"/>
                                <circle class="empty-icon-line" cx="50" cy="18" r="5"/>
                            </svg>
                            @break

                        @case('borrower')
                            <svg viewBox="0 0 64 64">
                                <circle class="empty-icon-soft" cx="30" cy="25" r="12"/>
                                <circle class="empty-icon-line" cx="30" cy="22" r="9"/>
                                <path class="empty-icon-line" d="M14 52c2-10 8-15 16-15s14 5 16 15"/>
                                <circle class="empty-icon-line" cx="48" cy="43" r="7"/>
                                <path class="empty-icon-line" d="M53 48l5 5"/>
                            </svg>
                            @break

                        @case('forecast')
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M12 12h40v40H12z"/>
                                <path class="empty-icon-line" d="M12 51V13M12 51h41M20 43l9-10 8 5 13-17"/>
                                <path class="empty-icon-line" d="M43 21h7v7"/>
                                <circle class="empty-icon-line" cx="29" cy="33" r="2"/>
                            </svg>
                            @break

                        @default
                            <svg viewBox="0 0 64 64">
                                <path class="empty-icon-soft" d="M15 10h27l9 9v32H15z"/>
                                <path class="empty-icon-line" d="M16 8h25l9 9v35H16zM41 8v10h9M23 27h20M23 35h17"/>
                                <circle class="empty-icon-line" cx="45" cy="45" r="7"/>
                                <path class="empty-icon-line" d="M50 50l5 5"/>
                            </svg>
                    @endswitch
                </span>

                <h2 id="analytics-empty-title">{{ $emptyTitle }}</h2>

                <p>
                    {{ $detail['empty'] ?? 'Records will appear here once data becomes available.' }}
                </p>

            </div>
        </section>
    @endif

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-analytics-record-search]');
    const rows = [...document.querySelectorAll('[data-analytics-record-row]')];
    const count = document.querySelector('[data-analytics-visible-count]');

    if (!input || rows.length === 0) return;

    const applySearch = () => {
        const query = input.value.trim().toLocaleLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const matches = query === '' || row.textContent.toLocaleLowerCase().includes(query);
            row.hidden = !matches;
            if (matches) visible++;
        });

        if (count) count.textContent = String(visible);
    };

    input.addEventListener('input', applySearch);
});
</script>
@endsection
